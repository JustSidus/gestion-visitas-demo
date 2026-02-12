<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Collection Resource para listas de visitantes
 * 
 * Responsabilidades:
 * - Formatear listas paginadas de visitantes
 * - Agregar metadatos de paginación
 * - Incluir estadísticas del conjunto de datos
 * - Proporcionar información adicional útil para el frontend
 */
class VisitorCollection extends ResourceCollection
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
        $visitors = $this->collection;
        
        if ($visitors->isEmpty()) {
            return [
                'total' => 0,
                'with_phone' => 0,
                'with_email' => 0,
                'with_company' => 0,
                'frequent_visitors' => 0,
                'new_visitors' => 0
            ];
        }

        $withPhoneCount = $visitors->filter(function($visitor) {
            return !empty($visitor->resource->phone);
        })->count();

        $withEmailCount = $visitors->filter(function($visitor) {
            return !empty($visitor->resource->email);
        })->count();

        $withCompanyCount = $visitors->filter(function($visitor) {
            return !empty($visitor->resource->company);
        })->count();

        $frequentVisitors = $visitors->filter(function($visitor) {
            return $visitor->resource->visits?->count() > 5;
        })->count();

        $newVisitors = $visitors->filter(function($visitor) {
            return $visitor->resource->created_at?->isAfter(now()->subWeek());
        })->count();

        return [
            'total' => $visitors->count(),
            'with_phone' => $withPhoneCount,
            'with_email' => $withEmailCount,
            'with_company' => $withCompanyCount,
            'frequent_visitors' => $frequentVisitors,
            'new_visitors' => $newVisitors,
            'percentage_complete_info' => $visitors->count() > 0 ? 
                round((($withPhoneCount + $withEmailCount + $withCompanyCount) / ($visitors->count() * 3)) * 100, 2) : 0
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

        if ($request->filled('name')) {
            $filters['name'] = $request->input('name');
        }

        if ($request->filled('carnet')) {
            $filters['carnet'] = $request->input('carnet');
        }

        if ($request->filled('company')) {
            $filters['company'] = $request->input('company');
        }

        if ($request->filled('phone')) {
            $filters['phone'] = $request->input('phone');
        }

        if ($request->filled('email')) {
            $filters['email'] = $request->input('email');
        }

        if ($request->boolean('has_active_visits')) {
            $filters['has_active_visits'] = true;
        }

        if ($request->boolean('frequent_visitors_only')) {
            $filters['frequent_visitors_only'] = true;
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
        $visitors = $this->collection;
        
        if ($visitors->isEmpty()) {
            return [
                'message' => 'No se encontraron visitantes',
                'suggestion' => 'Intenta modificar los filtros de búsqueda'
            ];
        }

        $summary = [
            'total_visitors' => $visitors->count(),
            'top_companies' => $this->getTopCompanies($visitors),
            'visitor_types' => $this->getVisitorTypes($visitors),
            'recent_registrations' => $this->getRecentRegistrations($visitors)
        ];

        // Agregar mensaje contextual
        if ($visitors->count() === 1) {
            $summary['message'] = 'Se encontró 1 visitante';
        } else {
            $summary['message'] = "Se encontraron {$visitors->count()} visitantes";
        }

        return $summary;
    }

    /**
     * Obtener top empresas
     */
    private function getTopCompanies(object $visitors): array
    {
        if ($visitors->isEmpty()) {
            return [];
        }

        $companies = $visitors->filter(function($visitor) {
            return !empty($visitor->resource->company);
        })->groupBy(function($visitor) {
            return $visitor->resource->company;
        })->map(function($group, $company) {
            return [
                'company' => $company,
                'count' => $group->count(),
                'percentage' => 0 // Se calcula después
            ];
        })->sortByDesc('count')->take(5);

        $total = $visitors->count();
        return $companies->map(function($item) use ($total) {
            $item['percentage'] = round(($item['count'] / $total) * 100, 1);
            return $item;
        })->values()->toArray();
    }

    /**
     * Obtener tipos de visitantes
     */
    private function getVisitorTypes(object $visitors): array
    {
        if ($visitors->isEmpty()) {
            return [];
        }

        $types = [
            'new' => 0,
            'occasional' => 0,
            'regular' => 0,
            'frequent' => 0
        ];

        $visitors->each(function($visitor) use (&$types) {
            $visitsCount = $visitor->resource->visits?->count() ?? 0;
            
            if ($visitsCount === 0 || $visitsCount === 1) {
                $types['new']++;
            } elseif ($visitsCount <= 3) {
                $types['occasional']++;
            } elseif ($visitsCount <= 10) {
                $types['regular']++;
            } else {
                $types['frequent']++;
            }
        });

        $total = $visitors->count();
        return collect($types)->map(function($count, $type) use ($total) {
            return [
                'type' => $type,
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
                'label' => $this->getTypeLabel($type)
            ];
        })->values()->toArray();
    }

    /**
     * Obtener etiquetas de tipos de visitantes
     */
    private function getTypeLabel(string $type): string
    {
        return match($type) {
            'new' => 'Nuevos/Primera visita',
            'occasional' => 'Ocasionales (2-3 visitas)',
            'regular' => 'Regulares (4-10 visitas)',
            'frequent' => 'Frecuentes (10+ visitas)',
            default => 'Desconocido'
        };
    }

    /**
     * Obtener registros recientes
     */
    private function getRecentRegistrations(object $visitors): array
    {
        if ($visitors->isEmpty()) {
            return [];
        }

        $recentVisitors = $visitors->sortByDesc(function($visitor) {
            return $visitor->resource->created_at;
        })->take(3);

        return $recentVisitors->map(function($visitor) {
            return [
                'id' => $visitor->resource->id,
                'name' => $visitor->resource->name,
                'company' => $visitor->resource->company ?: 'Sin empresa',
                'registered' => $visitor->resource->created_at?->diffForHumans(),
                'visits_count' => $visitor->resource->visits?->count() ?? 0
            ];
        })->values()->toArray();
    }
}