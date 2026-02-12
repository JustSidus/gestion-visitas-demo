<?php

namespace App\Services;

use App\Models\Visit;
use App\Repositories\Contracts\VisitRepositoryInterface;
use App\Services\EmailService;
use App\Enums\EnumVisitStatuses;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;

/**
 * Servicio de lógica de negocio para visitas
 * 
 * Este servicio centraliza toda la lógica de negocio relacionada con visitas,
 * coordinando entre repositorios, otros services y aplicando reglas de negocio.
 */
class VisitService
{
    protected $visitRepository;
    protected $emailService;

    public function __construct(
        VisitRepositoryInterface $visitRepository,
        EmailService $emailService
    ) {
        $this->visitRepository = $visitRepository;
        $this->emailService = $emailService;
    }

    /**
     * Crea una nueva visita con todas las validaciones de negocio
     * 
     * @param array $data Datos validados de la visita
     * @return Visit Visita creada con relaciones cargadas
     * @throws \Exception Si hay errores en la creación
     */
    public function createVisit(array $data): Visit
    {
        return DB::transaction(function () use ($data) {
            // Lock preventivo para visitantes (evitar que tengan otra visita activa simultánea)
            if (!empty($data['visitor_ids'])) {
                foreach ($data['visitor_ids'] as $visitorId) {
                    $hasActiveVisit = Visit::where('status_id', EnumVisitStatuses::ABIERTO->value)
                        ->lockForUpdate()
                        ->whereHas('visitors', fn($q) => $q->where('visitors.id', $visitorId))
                        ->exists();
                        
                    if ($hasActiveVisit) {
                        $visitor = \App\Models\Visitor::find($visitorId);
                        $name = $visitor ? "{$visitor->name} {$visitor->lastName}" : "Visitante #{$visitorId}";
                        throw new \Exception("El visitante {$name} ya tiene una visita activa.");
                    }
                }
            }
            
            // 1. Aplicar reglas de negocio pre-creación
            $this->applyBusinessRules($data);
            
            // 2. Validaciones específicas
            $this->validateVisitCreation($data);
            
            // 3. Preparar datos finales
            $finalData = $this->prepareFinalVisitData($data);
            
            // 4. Crear visita
            $visit = $this->visitRepository->create($finalData);
            
            // 5. Asociar visitantes si se proporcionaron
            if (!empty($data['visitor_ids'])) {
                $visit->visitors()->sync($data['visitor_ids']);
                $visit->load('visitors'); // Recargar relación
            }
            
            // 6. Enviar notificación si corresponde (no bloquear si falla)
                if ($this->shouldSendNotification($data)) {
                    $this->handleEmailNotification($visit, $data);
            }
            
            // 7. Log de auditoría
            $this->logVisitCreation($visit);
            
            // 8. Retornar con relaciones cargadas
            return $visit->load('user', 'status', 'visitors');
        });
    }

    /**
     * Cierra una visita con validaciones de autorización y lock pessimista
     * 
     * PUNTO CIEGO #2: Race condition - Implementa lock para evitar doble cierre
     * PUNTO CIEGO #5: Valida que usuario esté activo
     * 
     * @param int $visitId ID de la visita
     * @param array $data Datos adicionales (placa, etc.)
     * @param mixed $user Usuario que cierra
     * @return Visit Visita cerrada
     * @throws \Exception Si visita no existe o ya fue cerrada
     */
    public function closeVisit(int $visitId, array $data, $user): Visit
    {
        return DB::transaction(function () use ($visitId, $data, $user) {
            // 1. Lock pessimista: obtener visita con cerrojo para lectura/escritura
            $visit = Visit::lockForUpdate()->findOrFail($visitId);
            
            // 2. Validar que usuario está activo
            if (!$user->is_active) {
                throw new \Exception('Usuario inactivo no puede cerrar visitas');
            }
            
            // 3. Validar que se puede cerrar (doble validación contra race condition)
            if ($visit->status_id !== EnumVisitStatuses::ABIERTO->value) {
                throw new \Exception('Visita no está en estado ABIERTO (ya fue cerrada)');
            }
            if ($visit->end_at !== null) {
                throw new \Exception('Visita ya tiene fecha de cierre');
            }
            
            // 4. Preparar datos de cierre
            $closeData = [
                'status_id' => EnumVisitStatuses::CERRADO->value,
                'end_at' => now(),
                'closed_by' => $user->id,
            ];
            
            // Agregar placa si se proporciona
            if (!empty($data['vehicle_plate'])) {
                $closeData['vehicle_plate'] = strtoupper(trim($data['vehicle_plate']));
            }
            
            // 5. Cerrar visita (dentro de transacción para atomicidad)
            $visit->update($closeData);
            
            // 6. Log de auditoría (dentro de transacción para atomicidad)
            $this->logVisitClosure($visit->fresh(), $user);
            
            return $visit->fresh(['visitors', 'visitStatus', 'creator', 'closer']);
        });
    }

    /**
     * Actualiza una placa de vehículo sin cerrar la visita
     * 
     * PUNTO CIEGO #7: Registra auditoría de cambios con valor anterior
     * 
     * @param int $visitId ID de la visita
     * @param string|null $vehiclePlate Placa del vehículo
     * @return Visit Visita actualizada
     */
    public function updateVehiclePlate(int $visitId, ?string $vehiclePlate): Visit
    {
        return DB::transaction(function () use ($visitId, $vehiclePlate) {
            $visit = $this->visitRepository->findWithRelations($visitId);
            
            if (!$visit) {
                throw new \Exception('Visita no encontrada');
            }
            
            // Validar que la visita esté activa
            if ($visit->status_id !== EnumVisitStatuses::ABIERTO->value) {
                throw new \Exception('No se puede modificar la placa de una visita cerrada');
            }
            
            // Guardar valor anterior para auditoría
            $oldPlate = $visit->vehicle_plate;
            
            // Procesar placa
            $processedPlate = $vehiclePlate ? strtoupper(trim($vehiclePlate)) : null;
            
            // Actualizar
            $visit->update(['vehicle_plate' => $processedPlate]);
            
            // Log de auditoría CON VALOR ANTERIOR
            Log::info('Placa de vehículo actualizada', [
                'visit_id' => $visit->id,
                'old_plate' => $oldPlate,
                'new_plate' => $processedPlate,
                'updated_by' => JWTAuth::parseToken()->authenticate()->id
            ]);
            
            return $visit->fresh();
        });
    }

    /**
     * Búsqueda avanzada con estadísticas
     * 
     * @param array $filters Filtros de búsqueda
     * @return array Resultado con visitas, estadísticas y metadatos
     */
    public function performAdvancedSearch(array $filters): array
    {
        $visits = $this->visitRepository->advancedSearch($filters);
        
        return [
            'visits' => $visits,
            'statistics' => $this->calculateSearchStatistics($visits),
            'metadata' => [
                'total_count' => $visits->count(),
                'filters_applied' => $filters,
                'search_performed_at' => now()->toISOString(),
                'limited' => ($filters['limit'] ?? 1000) < $visits->count()
            ]
        ];
    }

    /**
     * Obtiene estadísticas optimizadas para el dashboard
     * Solo devuelve los datos necesarios para ActiveVisits
     * 
     * @return array Estadísticas (total_visitors_this_week, today_visitors, active_visits)
     */
    public function getDashboardStats(): array
    {
        return $this->visitRepository->getDashboardStats();
    }

    /**
     * Búsqueda de visitas con paginación
     * 
     * PUNTO CIEGO #4: Implementa paginación para evitar OOM (Out of Memory)
     * 
     * @param array $filters Filtros de búsqueda
     * @param int $perPage Resultados por página
     * @param int $page Número de página
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function searchVisitsPaginated(array $filters, int $perPage = 50, int $page = 1): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        // Limitar máximo de resultados por página
        $perPage = min(max($perPage, 1), 200);
        
        return $this->visitRepository->advancedSearchPaginated($filters, $perPage, $page);
    }

    /**
     * Obtiene estadísticas del dashboard solo para visitas misionales
     */
    public function getMissionStatsOnly(): array
    {
        return $this->visitRepository->getMissionStatsOnly();
    }

    /**
     * Obtiene estadísticas del dashboard solo para visitas NO misionales
     */
    public function getNonMissionStatsOnly(): array
    {
        return $this->visitRepository->getNonMissionStatsOnly();
    }

    /**
     * Aplica reglas de negocio específicas
     */
    private function applyBusinessRules(array &$data): void
    {
        // Establecer estado inicial siempre como ABIERTO
        $data['status_id'] = EnumVisitStatuses::ABIERTO->value;
        
        // Establecer fecha de creación
        $data['created_at'] = Carbon::now();
        
        // Si es caso misional, sobrescribir datos
        if ($data['mission_case'] ?? false) {
            $data['namePersonToVisit'] = 'Unidad de Gestión de Casos';
            $data['department'] = 'Gestión de Casos';
        }
    }

    /**
     * Validaciones de negocio para creación
     */
    private function validateVisitCreation(array $data): void
    {
        // Validar rango de carnet (1-99) solo si se proporciona y no es caso misional
        if (!empty($data['assigned_carnet']) && !($data['mission_case'] ?? false)) {
            $carnetNumber = (int) $data['assigned_carnet'];
            
            if ($carnetNumber < 1 || $carnetNumber > 99) {
                throw new \InvalidArgumentException(
                    "El número de carnet debe estar entre 1 y 99. Valor proporcionado: {$carnetNumber}"
                );
            }
        }

        // Validar visitantes sin visitas activas
        if (!empty($data['visitor_ids'])) {
            foreach ($data['visitor_ids'] as $visitorId) {
                if ($this->visitRepository->visitorHasActiveVisit($visitorId)) {
                    throw new \InvalidArgumentException(
                        "El visitante ID {$visitorId} ya tiene una visita activa"
                    );
                }
            }
        }
    }

    /**
     * Prepara datos finales para la visita
     */
    private function prepareFinalVisitData(array $data): array
    {
        // Limpiar solo visitor_ids (se maneja con sync)
        // send_email SI se guarda para registro histórico
        unset($data['visitor_ids']);
        
        // Asegurar que la visita se crea como ABIERTA
        if (!isset($data['status_id'])) {
            $data['status_id'] = EnumVisitStatuses::ABIERTO->value;
        }
        
        // Asegurar que send_email tenga valor por defecto si no viene
        if (!isset($data['send_email'])) {
            $data['send_email'] = false;
        }
        
        return $data;
    }

    /**
     * Determina si se debe enviar notificación
     */
    private function shouldSendNotification(array $data): bool
    {
        return ($data['send_email'] ?? false) && 
               !empty($data['person_to_visit_email']) &&
               $this->emailService->isEmailServiceAvailable();
    }

    /**
     * Maneja notificación por email de forma segura
     * 
     * NOTA: send_email ya está guardado en BD con valor true en este punto
     * Si el envío falla, el campo permanece en true indicando que se INTENTÓ enviar
     * Esto es intencional para mantener registro de la intención de notificación
     */
    private function handleEmailNotification(Visit $visit, array $data): void
    {
        try {
            $this->emailService->sendVisitNotification(
                $visit, 
                $data['person_to_visit_email']
            );
            
            // Log de éxito
            Log::info('Email de notificación enviado exitosamente', [
                'visit_id' => $visit->id,
                'email' => $data['person_to_visit_email']
            ]);
            
        } catch (\Exception $e) {
            // Log error pero no fallar la creación de visita
            Log::warning('Error enviando notificación de visita (no crítico)', [
                'visit_id' => $visit->id,
                'email' => $data['person_to_visit_email'] ?? 'no-email',
                'error' => $e->getMessage()
            ]);
            
            // NOTA: send_email queda en true aunque falle
            // Esto registra que se intentó enviar (útil para auditoría)
        }
    }

    

    /**
     * Valida que una visita se pueda cerrar
     */
    private function validateCanClose(Visit $visit): void
    {
        if ($visit->status_id !== EnumVisitStatuses::ABIERTO->value) {
            throw new \InvalidArgumentException('Solo se pueden cerrar visitas activas');
        }
        
        if ($visit->end_at !== null) {
            throw new \InvalidArgumentException('Esta visita ya está cerrada');
        }
    }

    /**
     * Calcula estadísticas de búsqueda
     */
    private function calculateSearchStatistics($visits): array
    {
        return [
            'total_count' => $visits->count(),
            'active_count' => $visits->where('status_id', EnumVisitStatuses::ABIERTO->value)->count(),
            'closed_count' => $visits->where('status_id', EnumVisitStatuses::CERRADO->value)->count(),
            'mission_cases' => $visits->where('mission_case', true)->count(),
            'today_visits' => $visits->filter(function ($visit) {
                return Carbon::parse($visit->created_at)->isToday();
            })->count(),
        ];
    }

    /**
     * Registra creación de visita para auditoría
     */
    private function logVisitCreation(Visit $visit): void
    {
        Log::info('Visita creada exitosamente', [
            'visit_id' => $visit->id,
            'assigned_carnet' => $visit->assigned_carnet,
            'visitors_count' => $visit->visitors->count(),
            'mission_case' => $visit->mission_case,
            'person_to_visit' => $visit->namePersonToVisit,
            'department' => $visit->department,
            'created_by' => $visit->user_id,
            'created_at' => $visit->created_at->toISOString()
        ]);
    }

    /**
     * Registra cierre de visita para auditoría
     */
    private function logVisitClosure(Visit $visit, $user): void
    {
        $duration = $visit->created_at->diffInMinutes($visit->end_at);
        
        Log::info('Visita cerrada exitosamente', [
            'visit_id' => $visit->id,
            'assigned_carnet' => $visit->assigned_carnet,
            'closed_by' => $user->id,
            'closed_by_name' => $user->name,
            'duration_minutes' => $duration,
            'duration_formatted' => $this->formatDuration($duration),
            'vehicle_plate' => $visit->vehicle_plate,
            'closed_at' => $visit->end_at instanceof \Carbon\Carbon 
                ? $visit->end_at->toISOString() 
                : \Carbon\Carbon::parse($visit->end_at)->toISOString()
        ]);
    }

    /**
     * Formatea duración en formato legible
     */
    private function formatDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} minutos";
        }
        
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;
        
        return "{$hours}h {$remainingMinutes}m";
    }

    /**
     * Buscar visitas con filtros
     * 
     * @param array $filters
     * @return \Illuminate\Support\Collection
     */
    public function searchVisits(array $filters)
    {
        $query = Visit::with(['user', 'closedBy', 'visitStatus', 'visitors']);

        // Filtrar por fecha
        if (!empty($filters['date'])) {
            $query->whereDate('created_at', $filters['date']);
        }

        // Filtrar por rango de fechas
        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        // Filtrar por estado
        if (!empty($filters['status_id'])) {
            $query->where('status_id', $filters['status_id']);
        }

        // Filtrar por búsqueda de texto
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('namePersonToVisit', 'like', "%{$search}%")
                  ->orWhere('department', 'like', "%{$search}%")
                  ->orWhere('observations', 'like', "%{$search}%")
                  ->orWhere('vehicle_plate', 'like', "%{$search}%");
            });
        }

        return $query->get();
    }

    /**
     * Actualizar una visita
     * 
     * @param Visit $visit
     * @param array $data
     * PUNTO CIEGO #7: Auditoría de cambios de visitantes con delta
     * 
     * @return Visit
     */
    public function updateVisit(Visit $visit, array $data): Visit
    {
        return DB::transaction(function () use ($visit, $data) {
            // Actualizar campos básicos
            $visit->update([
                'namePersonToVisit' => $data['namePersonToVisit'] ?? $visit->namePersonToVisit,
                'department' => $data['department'] ?? $visit->department,
                'building' => $data['building'] ?? $visit->building,
                'floor' => $data['floor'] ?? $visit->floor,
                'observations' => $data['observations'] ?? $visit->observations,
                'mission_case' => $data['mission_case'] ?? $visit->mission_case,
                'vehicle_plate' => $data['vehicle_plate'] ?? $visit->vehicle_plate,
            ]);

            // Actualizar visitantes si se proporcionaron
            if (isset($data['visitor_ids'])) {
                // Registrar cambios ANTES de hacer sync
                $oldVisitorIds = $visit->visitors->pluck('id')->toArray();
                $newVisitorIds = $data['visitor_ids'];
                
                // IDs removidos y agregados
                $removed = array_diff($oldVisitorIds, $newVisitorIds);
                $added = array_diff($newVisitorIds, $oldVisitorIds);
                
                // Log de auditoría con delta
                if ($removed || $added) {
                    Log::info('Visitantes de visita modificados', [
                        'visit_id' => $visit->id,
                        'removed_visitor_ids' => array_values($removed),
                        'added_visitor_ids' => array_values($added),
                        'updated_by' => auth()->id(),
                        'timestamp' => now(),
                    ]);
                }
                
                // Hacer sync
                $visit->visitors()->sync($newVisitorIds);
                $visit->load('visitors');
            }

            Log::info('Visita actualizada', ['visit_id' => $visit->id]);

            return $visit->fresh(['user', 'closedBy', 'visitStatus', 'visitors']);
        });
    }

    /**
     * Obtener estadísticas de visitas
     * 
     * @param string $period ('today', 'week', 'month')
     * @return array
     */
    public function getStatistics(string $period = 'today'): array
    {
        $query = Visit::query();

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)
                      ->whereYear('created_at', now()->year);
                break;
        }

        $visits = $query->get();

        return [
            'total' => $visits->count(),
            'active' => $visits->where('status_id', EnumVisitStatuses::ABIERTO->value)->count(),
            'closed' => $visits->where('status_id', EnumVisitStatuses::CERRADO->value)->count(),
            'mission_cases' => $visits->where('mission_case', true)->count(),
            'with_vehicles' => $visits->whereNotNull('vehicle_plate')->count(),
            'average_duration' => $this->calculateAverageDuration($visits->where('end_at', '!=', null)),
        ];
    }

    /**
     * Calcular duración promedio de visitas cerradas
     */
    private function calculateAverageDuration($closedVisits): ?int
    {
        if ($closedVisits->isEmpty()) {
            return null;
        }

        $totalMinutes = 0;
        foreach ($closedVisits as $visit) {
            $totalMinutes += Carbon::parse($visit->created_at)->diffInMinutes($visit->end_at);
        }

        return intdiv($totalMinutes, $closedVisits->count());
    }

    /**
     * Asignar carnet a visita
     * 
     * @param Visit $visit
     * @param int $carnetNumber
     * @return Visit
     */
    public function assignCarnet(Visit $visit, int $carnetNumber): Visit
    {
        // Validar rango 1-99
        if ($carnetNumber < 1 || $carnetNumber > 99) {
            throw new \InvalidArgumentException(
                "El número de carnet debe estar entre 1 y 99. Valor proporcionado: {$carnetNumber}"
            );
        }
        
        $visit->update(['assigned_carnet' => $carnetNumber]);
        
        Log::info('Carnet asignado', [
            'visit_id' => $visit->id,
            'carnet' => $carnetNumber
        ]);

        return $visit->fresh();
    }

    /**
     * Enviar notificación de visita
     * 
     * @param Visit $visit
     * @return array
     */
    public function sendVisitNotification(Visit $visit): array
    {
        try {
            // Obtener email del usuario a visitar
            $visit->load('user');
            // Fuente única: preferir email explícito de la persona a visitar si existe
            $recipientEmail = $visit->person_to_visit_email ?? ($visit->user->email ?? null);
            
            if (!$recipientEmail) {
                throw new \Exception('No se encontró email del destinatario');
            }
            
            $this->emailService->sendVisitNotification($visit, $recipientEmail);
            
            Log::info('Notificación enviada', ['visit_id' => $visit->id]);
            
            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Error enviando notificación', [
                'visit_id' => $visit->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}