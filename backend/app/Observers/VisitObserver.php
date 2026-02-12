<?php

namespace App\Observers;

use App\Models\Visit;
use App\Services\LoggerService;
use App\Enums\EnumVisitStatuses;

/**
 * Observer para logging automático de eventos de Visit
 * 
 * Responsabilidades:
 * - Registrar automáticamente cambios importantes en visitas
 * - Proporcionar contexto de negocio en los logs
 * - Integrar con sistemas de monitoreo y alertas
 * - Mantener trazabilidad completa de operaciones
 */
class VisitObserver
{
    public function __construct(
        protected LoggerService $logger
    ) {}

    /**
     * Handle the Visit "created" event.
     */
    public function created(Visit $visit): void
    {
        $this->logger->visit('created', $visit->id, [
            'visitor_count' => $visit->visitors()->count(),
            'department' => $visit->department,
            'reason' => $visit->reason,
            'has_vehicle' => !empty($visit->vehicle_plate),
            'created_by_role' => $visit->user?->role?->name,
        ]);

        $this->logger->business('Visit created', [
            'visit_id' => $visit->id,
            'department' => $visit->department,
            'visitor_count' => $visit->visitors()->count(),
            'created_by' => $visit->user_id,
        ]);

        // Métricas de negocio
        $this->logger->metric('visits_created_total', 1, [
            'department' => $visit->department,
            'user_role' => $visit->user?->role?->name,
        ]);
    }

    /**
     * Handle the Visit "updated" event.
     */
    public function updated(Visit $visit): void
    {
        $changes = $visit->getChanges();
        $originalValues = $visit->getOriginal();

        $this->logger->visit('updated', $visit->id, [
            'changed_fields' => array_keys($changes),
            'changes' => $this->formatChanges($changes, $originalValues),
        ]);

        // Log específico para cambios importantes
        if (isset($changes['status_id'])) {
            $this->logStatusChange($visit, $originalValues['status_id'], $changes['status_id']);
        }

        // Eliminado: 'is_active' es un accessor no persistente; el cierre se detecta por status_id

        // Asignación de carnet: el campo real en BD es 'assigned_carnet'
        if (isset($changes['assigned_carnet'])) {
            $this->logCarnetAssignment($visit, $changes['assigned_carnet']);
        }
    }

    /**
     * Handle the Visit "deleted" event.
     */
    public function deleted(Visit $visit): void
    {
        $this->logger->visit('deleted', $visit->id, [
            'department' => $visit->department,
            'was_active' => $visit->is_active,
            'visitor_count' => $visit->visitors()->count(),
        ]);

        $this->logger->business('Visit deleted', [
            'visit_id' => $visit->id,
            'department' => $visit->department,
            'was_active' => $visit->is_active,
        ]);

        // Alerta para eliminaciones de visitas activas
        if ($visit->is_active) {
            $this->logger->security('Active visit deleted', [
                'visit_id' => $visit->id,
                'department' => $visit->department,
                'severity' => 'high',
            ], 'warning');
        }
    }

    /**
     * Handle the Visit "restored" event.
     */
    public function restored(Visit $visit): void
    {
        $this->logger->visit('restored', $visit->id, [
            'department' => $visit->department,
            'visitor_count' => $visit->visitors()->count(),
        ]);

        $this->logger->business('Visit restored', [
            'visit_id' => $visit->id,
            'department' => $visit->department,
        ]);
    }

    /**
     * Log status changes with business context
     */
    protected function logStatusChange(Visit $visit, int $oldStatusId, int $newStatusId): void
    {
        $oldStatus = \App\Models\VisitStatus::find($oldStatusId);
        $newStatus = \App\Models\VisitStatus::find($newStatusId);

        $this->logger->business('Visit status changed', [
            'visit_id' => $visit->id,
            'old_status' => $oldStatus?->name,
            'new_status' => $newStatus?->name,
            'department' => $visit->department,
        ]);

        $this->logger->metric('visit_status_changes_total', 1, [
            'from_status' => $oldStatus?->name,
            'to_status' => $newStatus?->name,
            'department' => $visit->department,
        ]);

        // Si el nuevo estado es CERRADO, registrar métrica de cierre con duración
        if ($newStatusId === EnumVisitStatuses::CERRADO->value) {
            $this->logVisitClosure($visit);
        }
    }

    /**
     * Log visit closure with timing metrics
     */
    protected function logVisitClosure(Visit $visit): void
    {
        $duration = $visit->end_at
            ? $visit->created_at->diffInMinutes($visit->end_at)
            : $visit->created_at->diffInMinutes(now());

        $this->logger->business('Visit closed', [
            'visit_id' => $visit->id,
            'duration_minutes' => $duration,
            'department' => $visit->department,
            'closed_by_role' => $visit->closer?->role?->name,
        ]);

        $this->logger->metric('visit_duration_minutes', $duration, [
            'department' => $visit->department,
        ]);

        $this->logger->metric('visits_closed_total', 1, [
            'department' => $visit->department,
        ]);

        // Alerta para visitas muy cortas o muy largas
        if ($duration < 5) {
            $this->logger->business('Very short visit detected', [
                'visit_id' => $visit->id,
                'duration_minutes' => $duration,
            ], 'warning');
        } elseif ($duration > 480) { // > 8 horas
            $this->logger->business('Very long visit detected', [
                'visit_id' => $visit->id,
                'duration_minutes' => $duration,
            ], 'warning');
        }
    }

    /**
     * Log carnet assignment
     */
    protected function logCarnetAssignment(Visit $visit, string|int|null $carnetNumber): void
    {
        $this->logger->business('Carnet assigned', [
            'visit_id' => $visit->id,
            // Mantener nomenclatura consistente con la base de datos
            'assigned_carnet' => $carnetNumber,
            'department' => $visit->department,
            'assigned_by_role' => request()?->user()?->role?->name,
        ]);

        $this->logger->security('Carnet assignment', [
            'visit_id' => $visit->id,
            'assigned_carnet' => $carnetNumber,
        ]);
    }

    /**
     * Format changes for logging
     */
    protected function formatChanges(array $changes, array $original): array
    {
        $formatted = [];
        
        foreach ($changes as $field => $newValue) {
            $formatted[$field] = [
                'from' => $original[$field] ?? null,
                'to' => $newValue,
            ];
        }

        return $formatted;
    }
}