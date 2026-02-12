<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Enums\EnumVisitStatuses;

/**
 * Collection Resource para listas de visitas
 * 
 * Responsabilidades:
 * - Formatear listas paginadas de visitas
 * - Agregar metadatos de paginación
 * - Incluir estadísticas del conjunto de datos
 * - Proporcionar información adicional útil para el frontend
 */
class VisitCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'statistics' => $this->getStatistics(),
                'filters_applied' => $this->getAppliedFilters($request),
                'summary' => $this->getSummary()
            ]
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'version' => '1.0',
            'generated_at' => now()->toISOString(),
            'response_time' => microtime(true) - LARAVEL_START
        ];
    }

    /**
     * Customize the pagination information for the resource.
     *
     * @return array<string, mixed>
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        $default['meta']['pagination'] = [
            'current_page' => $paginated['current_page'],
            'last_page' => $paginated['last_page'],
            'per_page' => $paginated['per_page'],
            'total' => $paginated['total'],
            'from' => $paginated['from'],
            'to' => $paginated['to'],
            'has_more_pages' => $paginated['current_page'] < $paginated['last_page'],
            'is_first_page' => $paginated['current_page'] === 1,
            'is_last_page' => $paginated['current_page'] === $paginated['last_page']
        ];

        // Remover links innecesarios del Laravel paginator
        unset($default['links']);

        return $default;
    }

    /**
     * Obtener estadísticas del conjunto de datos
     */
    private function getStatistics(): array
    {
        $visits = $this->collection;
        
        if ($visits->isEmpty()) {
            return [
                'total' => 0,
                'active' => 0,
                'closed' => 0,
                'with_vehicle' => 0,
                'with_email' => 0,
                'average_visitors_per_visit' => 0
            ];
        }

        $activeCount = $visits->filter(function($visit) {
            return $visit->resource->status_id === EnumVisitStatuses::ABIERTO->value;
        })->count();

        $withVehicleCount = $visits->filter(function($visit) {
            return !empty($visit->resource->vehicle_plate);
        })->count();

        $withEmailCount = $visits->filter(function($visit) {
            return !empty($visit->resource->person_to_visit_email);
        })->count();

        $totalVisitors = $visits->sum(function($visit) {
            return $visit->resource->visitors?->count() ?? 0;
        });

        return [
            'total' => $visits->count(),
            'active' => $activeCount,
            'closed' => $visits->count() - $activeCount,
            'with_vehicle' => $withVehicleCount,
            'with_email' => $withEmailCount,
            'average_visitors_per_visit' => $visits->count() > 0 ? round($totalVisitors / $visits->count(), 2) : 0,
            'percentage_active' => $visits->count() > 0 ? round(($activeCount / $visits->count()) * 100, 2) : 0
        ];
    }

    /**
     * Obtener filtros aplicados en la consulta
     */
    private function getAppliedFilters(Request $request): array
    {
        $filters = [];

        // Verificar qué filtros se aplicaron
        if ($request->filled('search')) {
            $filters['search'] = $request->input('search');
        }

        if ($request->filled('status')) {
            $filters['status'] = $request->input('status');
        }

        if ($request->filled('department')) {
            $filters['department'] = $request->input('department');
        }

        if ($request->filled('date_from')) {
            $filters['date_from'] = $request->input('date_from');
        }

        if ($request->filled('date_to')) {
            $filters['date_to'] = $request->input('date_to');
        }

        if ($request->filled('visitor_name')) {
            $filters['visitor_name'] = $request->input('visitor_name');
        }

        if ($request->filled('visitor_carnet')) {
            $filters['visitor_carnet'] = $request->input('visitor_carnet');
        }

        if ($request->filled('vehicle_plate')) {
            $filters['vehicle_plate'] = $request->input('vehicle_plate');
        }

        if ($request->boolean('created_today')) {
            $filters['created_today'] = true;
        }

        if ($request->boolean('created_this_week')) {
            $filters['created_this_week'] = true;
        }

        if ($request->boolean('created_this_month')) {
            $filters['created_this_month'] = true;
        }

        return [
            'count' => count($filters),
            'filters' => $filters,
            'has_active_filters' => count($filters) > 0
        ];
    }

    /**
     * Obtener resumen del conjunto de datos
     */
    private function getSummary(): array
    {
        $visits = $this->collection;
        
        if ($visits->isEmpty()) {
            return [
                'message' => 'No se encontraron visitas',
                'suggestion' => 'Intenta modificar los filtros de búsqueda'
            ];
        }

        $summary = [
            'total_visits' => $visits->count(),
            'date_range' => $this->getDateRange($visits),
            'top_departments' => $this->getTopDepartments($visits),
            'recent_activity' => $this->getRecentActivity($visits)
        ];

        // Agregar mensaje contextual
        if ($visits->count() === 1) {
            $summary['message'] = 'Se encontró 1 visita';
        } else {
            $summary['message'] = "Se encontraron {$visits->count()} visitas";
        }

        return $summary;
    }

    /**
     * Obtener rango de fechas del conjunto de datos
     */
    private function getDateRange(object $visits): array
    {
        if ($visits->isEmpty()) {
            return [];
        }

        $dates = $visits->map(function($visit) {
            return $visit->resource->created_at;
        })->filter()->sort();

        $earliest = $dates->first();
        $latest = $dates->last();

        return [
            'earliest' => $earliest?->format('d/m/Y H:i:s'),
            'latest' => $latest?->format('d/m/Y H:i:s'),
            'span_days' => $earliest && $latest ? $earliest->diffInDays($latest) : 0
        ];
    }

    /**
     * Obtener top departamentos
     */
    private function getTopDepartments(object $visits): array
    {
        if ($visits->isEmpty()) {
            return [];
        }

        $departments = $visits->groupBy(function($visit) {
            return $visit->resource->department;
        })->map(function($group, $department) {
            return [
                'department' => $department,
                'count' => $group->count(),
                'percentage' => 0 // Se calcula después
            ];
        })->sortByDesc('count')->take(5);

        $total = $visits->count();
        return $departments->map(function($item) use ($total) {
            $item['percentage'] = round(($item['count'] / $total) * 100, 1);
            return $item;
        })->values()->toArray();
    }

    /**
     * Obtener actividad reciente
     */
    private function getRecentActivity(object $visits): array
    {
        if ($visits->isEmpty()) {
            return [];
        }

        $recentVisits = $visits->sortByDesc(function($visit) {
            return $visit->resource->created_at;
        })->take(3);

        return $recentVisits->map(function($visit) {
            return [
                'id' => $visit->resource->id,
                'action' => 'Visita creada',
                'description' => "Visita para {$visit->resource->namePersonToVisit} en {$visit->resource->department}",
                'time' => $visit->resource->created_at?->diffForHumans()
            ];
        })->values()->toArray();
    }
}