<?php

namespace App\Repositories\Eloquent;

use App\Models\Visit;
use App\Repositories\Contracts\VisitRepositoryInterface;
use App\Enums\EnumVisitStatuses;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Implementación Eloquent del repositorio de visitas
 * 
 * Esta implementación encapsula todas las consultas relacionadas con visitas,
 * proporcionando métodos optimizados y reutilizables.
 */
class VisitRepository implements VisitRepositoryInterface
{
    /**
     * Relaciones que se cargan frecuentemente con campos optimizados
     */
    protected array $defaultWith = [
        'user:id,name',
        'closedByUser:id,name', 
        'status:id,name',
        'visitors:id,name,lastName,identity_document,document_type,phone,email'
    ];
    
    /**
     * Relaciones optimizadas para visitas activas (incluye case_id del pivot)
     * Nota: Los campos específicos de visitors se aplican en el método para incluir pivot data
     */
    protected array $activeVisitsWith = [
        'user:id,name',
        'status:id,name'
    ];

    /**
     * Obtiene todas las visitas con relaciones optimizadas
     */
    public function getAllWithRelations(array $relations = [], ?int $limit = null): Collection
    {
        $with = !empty($relations) ? $relations : $this->defaultWith;

        $query = Visit::with($with)->orderBy('created_at', 'desc');

        if (!is_null($limit)) {
            $query->limit(max(1, $limit));
        }

        return $query->get();
    }

    /**
     * Obtiene visitas de hoy con filtros opcionales
     */
    public function getTodayVisits(?string $search = null): Collection
    {
        $query = Visit::with($this->defaultWith)
            ->whereDate('created_at', Carbon::today())
            ->orderBy('created_at', 'desc');

        if ($search) {
            $query = $this->applyVisitorSearch($query, $search);
        }

        return $query->get();
    }

    /**
     * Obtiene visitas activas (abiertas)
     */
    public function getActiveVisits(?string $search = null): Collection
    {
        $query = Visit::with($this->defaultWith)
            ->where('status_id', EnumVisitStatuses::ABIERTO->value)
            ->orderBy('created_at', 'desc');

        if ($search) {
            $query = $this->applyVisitorSearch($query, $search);
        }

        return $query->get();
    }

    /**
     * Obtiene visitas activas misionales (abiertas y mission_case = true)
     * Optimizado con eager loading del case_id del pivot
     */
    public function getActiveMissionVisits(?string $search = null): Collection
    {
        $query = Visit::with(array_merge($this->activeVisitsWith, [
                'visitors' => function($q) {
                    $q->select('visitors.id', 'visitors.name', 'visitors.lastName', 'visitors.document_type', 'visitors.identity_document', 'visitors.institution', 'visit_visitor.visit_id', 'visit_visitor.visitor_id', 'visit_visitor.case_id');
                }
            ]))
            ->where('status_id', EnumVisitStatuses::ABIERTO->value)
            ->where('mission_case', true)
            ->orderBy('created_at', 'desc');

        if ($search) {
            $query = $this->applyVisitorSearch($query, $search);
        }

        return $query->get();
    }

    /**
     * Obtiene visitas activas NO misionales (abiertas y mission_case = false o null)
     */
    public function getActiveNonMissionVisits(?string $search = null): Collection
    {
        $query = Visit::with($this->defaultWith)
            ->where('status_id', EnumVisitStatuses::ABIERTO->value)
            ->where(function($q) {
                $q->where('mission_case', false)
                  ->orWhereNull('mission_case');
            })
            ->orderBy('created_at', 'desc');

        if ($search) {
            $query = $this->applyVisitorSearch($query, $search);
        }

        return $query->get();
    }

    /**
     * Obtiene visitas cerradas de hoy
     */
    public function getClosedTodayVisits(?string $search = null): Collection
    {
        $query = Visit::with($this->defaultWith)
            ->where('status_id', EnumVisitStatuses::CERRADO->value)
            ->whereDate('created_at', Carbon::today())
            ->orderBy('created_at', 'desc');

        if ($search) {
            $query = $this->applyVisitorSearch($query, $search);
        }

        return $query->get();
    }

    /**
     * Búsqueda avanzada con múltiples filtros optimizada
     * DELEGADA a applyCommonFilters para consistencia
     */
    public function advancedSearch(array $filters): Collection
    {
        $query = Visit::with($this->defaultWith);
        
        // Log de depuración
        \Illuminate\Support\Facades\Log::info('VisitRepository::advancedSearch - Inicio', [
            'filters_received' => $filters,
            'has_start_date' => !empty($filters['start_date']),
            'has_end_date' => !empty($filters['end_date']),
            'has_limit' => isset($filters['limit'])
        ]);
        
        // Aplicar filtros comunes (incluye todos los filtros necesarios)
        $query = $this->applyCommonFilters($query, $filters);

        // Límite de seguridad SOLO SI SE ESPECIFICA
        // Si no se especifica límite, devuelve todos los resultados (para exportaciones)
        if (isset($filters['limit'])) {
            $limit = min($filters['limit'], 5000);
            $query->limit($limit);
            \Illuminate\Support\Facades\Log::info('VisitRepository::advancedSearch - Límite aplicado', ['limit' => $limit]);
        } else {
            \Illuminate\Support\Facades\Log::info('VisitRepository::advancedSearch - Sin límite (exportación)');
        }
        
        $results = $query->orderBy('created_at', 'desc')->get();
        
        \Illuminate\Support\Facades\Log::info('VisitRepository::advancedSearch - Resultados', [
            'count' => $results->count()
        ]);
        
        return $results;
    }

    /**
     * Búsqueda avanzada CON PAGINACIÓN
     * 
     * PUNTO CIEGO #4: Implementa paginación para evitar OOM
     * 
     * @param array $filters
     * @param int $perPage
     * @param int $page
     * @return LengthAwarePaginator
     */
    public function advancedSearchPaginated(array $filters, int $perPage = 50, int $page = 1): LengthAwarePaginator
    {
        $query = Visit::with($this->defaultWith);
        
        // Aplicar filtros comunes
        $query = $this->applyCommonFilters($query, $filters);

        // Paginar resultados (máximo 200 por página)
        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Obtiene estadísticas optimizadas para el dashboard con una sola query
     * Consolida 6 queries en 1 usando CASE statements
     */
    public function getDashboardStats(): array
    {
        $today = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();

        $stats = Visit::selectRaw('
            SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as total_visitors_this_week,
            SUM(CASE WHEN status_id = ? AND DATE(end_at) = ? THEN 1 ELSE 0 END) as today_visitors,
            SUM(CASE WHEN status_id = ? THEN 1 ELSE 0 END) as active_visits,
            SUM(CASE WHEN status_id = ? AND DATE(end_at) = ? THEN 1 ELSE 0 END) as closed_visitors,
            SUM(CASE WHEN mission_case = 1 AND created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as mission_cases_week,
            SUM(CASE WHEN mission_case = 1 AND created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as total_mission_visitors_this_week,
            SUM(CASE WHEN mission_case = 1 AND status_id = ? AND DATE(end_at) = ? THEN 1 ELSE 0 END) as today_mission_visitors,
            SUM(CASE WHEN mission_case = 1 AND status_id = ? THEN 1 ELSE 0 END) as active_mission_visits,
            COUNT(*) as total_visits_all_time
        ', [
            $weekStart, $weekEnd,
            EnumVisitStatuses::CERRADO->value, $today,
            EnumVisitStatuses::ABIERTO->value,
            EnumVisitStatuses::CERRADO->value, $today,
            $weekStart, $weekEnd,
            $weekStart, $weekEnd,
            EnumVisitStatuses::CERRADO->value, $today,
            EnumVisitStatuses::ABIERTO->value
        ])
        ->first()
        ->toArray();

        return $stats;
    }

    /**
     * Obtiene estadísticas del dashboard solo para visitas misionales
     */
    public function getMissionStatsOnly(): array
    {
        $today = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();

        $stats = Visit::where('mission_case', true)
            ->selectRaw('
                SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as total_visitors_this_week,
                SUM(CASE WHEN status_id = ? AND DATE(end_at) = ? THEN 1 ELSE 0 END) as today_visitors,
                SUM(CASE WHEN status_id = ? THEN 1 ELSE 0 END) as active_visits,
                SUM(CASE WHEN status_id = ? AND DATE(end_at) = ? THEN 1 ELSE 0 END) as closed_visitors,
                COUNT(*) as total_visits_all_time
            ', [
                $weekStart, $weekEnd,
                EnumVisitStatuses::CERRADO->value, $today,
                EnumVisitStatuses::ABIERTO->value,
                EnumVisitStatuses::CERRADO->value, $today
            ])
            ->first()
            ->toArray();

        // Asegurarse de que todos los campos existan (puede haber nulls)
        return array_merge([
            'total_visitors_this_week' => 0,
            'today_visitors' => 0,
            'active_visits' => 0,
            'closed_visitors' => 0,
            'mission_cases_week' => 0,
            'total_visits_all_time' => 0,
        ], array_filter($stats, fn($v) => $v !== null));
    }

    /**
     * Obtiene estadísticas del dashboard solo para visitas NO misionales
     */
    public function getNonMissionStatsOnly(): array
    {
        $today = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();

        $stats = Visit::where(function($q) {
                $q->where('mission_case', false)
                  ->orWhereNull('mission_case');
            })
            ->selectRaw('
                SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as total_visitors_this_week,
                SUM(CASE WHEN status_id = ? AND DATE(end_at) = ? THEN 1 ELSE 0 END) as today_visitors,
                SUM(CASE WHEN status_id = ? THEN 1 ELSE 0 END) as active_visits,
                SUM(CASE WHEN status_id = ? AND DATE(end_at) = ? THEN 1 ELSE 0 END) as closed_visitors,
                COUNT(*) as total_visits_all_time
            ', [
                $weekStart, $weekEnd,
                EnumVisitStatuses::CERRADO->value, $today,
                EnumVisitStatuses::ABIERTO->value,
                EnumVisitStatuses::CERRADO->value, $today
            ])
            ->first()
            ->toArray();

        // Asegurarse de que todos los campos existan (puede haber nulls)
        return array_merge([
            'total_visitors_this_week' => 0,
            'today_visitors' => 0,
            'active_visits' => 0,
            'closed_visitors' => 0,
            'mission_cases_week' => 0,
            'total_visits_all_time' => 0,
        ], array_filter($stats, fn($v) => $v !== null));
    }

    /**
     * Encuentra visita con relaciones optimizadas
     */
    public function findWithRelations(int $id, array $relations = []): ?Visit
    {
        $with = !empty($relations) ? $relations : $this->defaultWith;

        return Visit::with($with)->find($id);
    }

    /**
     * Crea nueva visita
     */
    public function create(array $data): Visit
    {
        return Visit::create($data);
    }

    /**
     * Actualiza visita existente
     */
    public function update(int $id, array $data): Visit
    {
        $visit = Visit::findOrFail($id);
        $visit->update($data);
        return $visit->fresh();
    }

    /**
     * Cierra una visita
     */
    public function closeVisit(int $id, array $data = []): Visit
    {
        $visit = Visit::findOrFail($id);
        
        $updateData = array_merge([
            'status_id' => EnumVisitStatuses::CERRADO->value,
            'end_at' => now(),
        ], $data);
        
        $visit->update($updateData);
        return $visit->fresh(['visitors', 'visitStatus', 'creator', 'closer']);
    }

    /**
     * Elimina una visita (soft delete)
     * 
     * PUNTO CIEGO #10: Implementa soft delete para auditoría y recuperación
     * Evita eliminación permanente y permite rastrear qué se eliminó
     * 
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $visit = Visit::findOrFail($id);
        // Si se usa SoftDeletes en el modelo, delete() hará soft delete automáticamente
        return $visit->delete();
    }
    
    /**
     * Restaura una visita eliminada (soft delete)
     * 
     * @param int $id
     * @return bool
     */
    public function restore(int $id): bool
    {
        $visit = Visit::withTrashed()->findOrFail($id);
        return $visit->restore();
    }

    /**
     * Verifica si carnet está en uso (consulta optimizada)
     */
    public function isCarnetInUse(int $carnet): bool
    {
        return Visit::where('assigned_carnet', $carnet)
                   ->where('status_id', EnumVisitStatuses::ABIERTO->value)
                   ->exists();
    }

    /**
     * Verifica si visitante tiene visita activa
     */
    public function visitorHasActiveVisit(int $visitorId): bool
    {
        return Visit::where('status_id', EnumVisitStatuses::ABIERTO->value)
                   ->whereHas('visitors', function ($query) use ($visitorId) {
                       $query->where('visitors.id', $visitorId);
                   })
                   ->exists();
    }

    /**
     * Busca visitas por documento de identidad del visitante
     */
    public function findByVisitorIdentity(string $identityDocument): Collection
    {
        return Visit::with(['visitors', 'status'])
            ->whereHas('visitors', function ($query) use ($identityDocument) {
                $query->where('identity_document', $identityDocument);
            })
            ->get();
    }

    /**
     * Obtiene visitas filtradas por rango de fechas
     */
    public function getVisitsByDateFilter(string $filter): Collection
    {
        $query = Visit::with($this->defaultWith);

        switch ($filter) {
            case 'today':
                $query->whereDate('created_at', Carbon::today());
                break;

            case 'this_week':
                $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                break;

            case 'last_week':
                $query->whereBetween('created_at', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]);
                break;

            case 'last_month':
                $query->whereBetween('created_at', [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()]);
                break;

            case 'past':
                $query->whereDate('created_at', '<', Carbon::today());
                break;
        }

        return $query->get();
    }

    /**
     * Obtener estadísticas generales de visitas
     */
    public function getStatistics(): array
    {
        $totalVisits = Visit::count();
        $activeVisits = Visit::where('status_id', EnumVisitStatuses::ABIERTO->value)->count();
        $closedVisits = Visit::where('status_id', EnumVisitStatuses::CERRADO->value)->count();

        $todayVisits = Visit::whereDate('created_at', Carbon::today())->count();
        $thisWeekVisits = Visit::whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->count();
        $thisMonthVisits = Visit::whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])->count();

        return [
            'total_visits' => $totalVisits,
            'active_visits' => $activeVisits,
            'closed_visits' => $closedVisits,
            'today_visits' => $todayVisits,
            'this_week_visits' => $thisWeekVisits,
            'this_month_visits' => $thisMonthVisits,
            'average_per_day' => $thisWeekVisits > 0 ? round($thisWeekVisits / 7, 2) : 0
        ];
    }

    /**
     * Obtener departamentos únicos
     */
    public function getUniqueDepartments(): array
    {
        return Visit::distinct()
            ->pluck('department')
            ->filter()
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Obtener visitas del día actual
     */
    public function getVisitsToday()
    {
        return Visit::with($this->defaultWith)
            ->whereDate('created_at', Carbon::today())
            ->orderBy('created_at', 'desc');
    }

    /**
     * Obtener visitas cerradas hoy
     */
    public function getClosedToday()
    {
        return Visit::with($this->defaultWith)
            ->whereDate('end_at', Carbon::today())
            ->where('status_id', EnumVisitStatuses::CERRADO->value);
    }

    /**
     * Obtener top departamentos del día
     */
    public function getTopDepartmentsToday(int $limit = 5): array
    {
        return Visit::whereDate('created_at', Carbon::today())
            ->selectRaw('department, COUNT(*) as visits_count')
            ->groupBy('department')
            ->orderByDesc('visits_count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Obtener horas más ocupadas
     */
    public function getBusiestHours(): array
    {
        return Visit::whereDate('created_at', Carbon::today())
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as visits_count')
            ->groupBy('hour')
            ->orderByDesc('visits_count')
            ->get()
            ->toArray();
    }

    /**
     * Obtener duración promedio de visitas
     */
    public function getAverageVisitDuration(): float
    {
        $closedVisits = Visit::whereNotNull('end_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, end_at)) as avg_duration')
            ->first();

        return $closedVisits->avg_duration ?? 0.0;
    }

    /**
     * Obtener visitas de un mes específico
     */
    public function getVisitsInMonth(int $year, int $month)
    {
        return Visit::with($this->defaultWith)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month);
    }

    /**
     * Obtener visitantes únicos en un mes
     */
    public function getUniqueVisitorsInMonth(int $year, int $month): int
    {
        return Visit::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->with('visitors')
            ->get()
            ->pluck('visitors')
            ->flatten()
            ->unique('id')
            ->count();
    }

    /**
     * Obtener estadísticas de departamentos por mes
     */
    public function getDepartmentStatsForMonth(int $year, int $month): array
    {
        return Visit::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->selectRaw('department, COUNT(*) as visits_count')
            ->groupBy('department')
            ->orderByDesc('visits_count')
            ->get()
            ->toArray();
    }

    /**
     * Obtener distribución diaria para un mes
     */
    public function getDailyDistributionForMonth(int $year, int $month): array
    {
        return Visit::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as visits_count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Obtener duración promedio de visitas por mes
     */
    public function getAverageVisitDurationForMonth(int $year, int $month): float
    {
        $result = Visit::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->whereNotNull('end_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, end_at)) as avg_duration')
            ->first();

        return $result->avg_duration ?? 0.0;
    }

    /**
     * Obtener horas pico de un mes
     */
    public function getPeakHoursForMonth(int $year, int $month): array
    {
        return Visit::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as visits_count')
            ->groupBy('hour')
            ->orderByDesc('visits_count')
            ->get()
            ->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 100));
        $page = max($page, 1);

        $query = Visit::with($this->defaultWith);
        $query = $this->applyCommonFilters($query, $filters);

        $allowedSorts = [
            'id',
            'namePersonToVisit',
            'department',
            'reason',
            'created_at',
            'end_at',
            'status_id',
            'visitors_count',
        ];

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = strtolower($filters['sort_direction'] ?? 'desc');

        if ($sortBy === 'closed_at') {
            $sortBy = 'end_at';
        } elseif ($sortBy === 'visitor_count') {
            $sortBy = 'visitors_count';
        }

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }

        if (!in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'desc';
        }

        $query->orderBy($sortBy, $sortDirection);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * {@inheritDoc}
     */
    public function getPastVisits(int $perPage = 15): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 100));

        return Visit::with($this->defaultWith)
            ->whereDate('created_at', '<', Carbon::today())
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function search(string $searchTerm, array $filters = []): Collection
    {
        $filters['search'] = $searchTerm;
        $limit = (int) ($filters['limit'] ?? 100);
        $limit = $limit > 0 ? min($limit, 500) : 100;

        $query = Visit::with($this->defaultWith);
        $query = $this->applyCommonFilters($query, $filters);

        return $query
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findByDate(string $date): Collection
    {
        return Visit::with($this->defaultWith)
            ->whereDate('created_at', $date)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function getForExport(array $filters = []): Collection
    {
        $query = Visit::with($this->defaultWith);
        $query = $this->applyCommonFilters($query, $filters);

        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 5000;
        $limit = $limit > 0 ? min($limit, 5000) : 5000;

        if ($limit) {
            $query->limit($limit);
        }

        return $query
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Aplica búsqueda en visitantes (método reutilizable privado)
     */
    private function applyVisitorSearch(Builder $query, string $search): Builder
    {
        return $query->whereHas('visitors', function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('lastName', 'like', "%{$search}%")
              ->orWhere('identity_document', 'like', "%{$search}%");
        });
    }

    /**
     * Aplica filtros comunes para listados y búsquedas.
     */
    private function applyCommonFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function (Builder $q) use ($term) {
                $q->where('namePersonToVisit', 'like', "%{$term}%")
                    ->orWhere('department', 'like', "%{$term}%")
                    ->orWhere('reason', 'like', "%{$term}%")
                    ->orWhere('vehicle_plate', 'like', "%{$term}%");
            });
        }

        if (!empty($filters['visitor_name'])) {
            $query = $this->applyVisitorSearch($query, $filters['visitor_name']);
        }

        // Soportar también 'visitor_search' (usado en exportaciones)
        if (!empty($filters['visitor_search'])) {
            $query = $this->applyVisitorSearch($query, $filters['visitor_search']);
        }

        // Soportar búsqueda por persona visitada
        if (!empty($filters['person_visited'])) {
            $query->where('namePersonToVisit', 'like', '%' . trim($filters['person_visited']) . '%');
        }

        if (!empty($filters['visitor_carnet'])) {
            $carnet = trim($filters['visitor_carnet']);
            $query->whereHas('visitors', function (Builder $q) use ($carnet) {
                $q->where('carnet', 'like', "%{$carnet}%")
                  ->orWhere('identity_document', 'like', "%{$carnet}%");
            });
        }

        if (!empty($filters['vehicle_plate'])) {
            $query->where('vehicle_plate', 'like', '%' . trim($filters['vehicle_plate']) . '%');
        }

        if (!empty($filters['department'])) {
            $query->where('department', 'like', '%' . trim($filters['department']) . '%');
        }

        if (!empty($filters['status_id'])) {
            $query->where('status_id', (int) $filters['status_id']);
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $statusMap = [
                'active' => EnumVisitStatuses::ABIERTO->value,
                'closed' => EnumVisitStatuses::CERRADO->value,
            ];

            if (isset($statusMap[$filters['status']])) {
                $query->where('status_id', $statusMap[$filters['status']]);
            }
        }

        if (isset($filters['mission_case']) && $filters['mission_case'] !== null && $filters['mission_case'] !== '') {
            $query->where('mission_case', filter_var($filters['mission_case'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (!empty($filters['closed_by'])) {
            $query->where('closed_by', (int) $filters['closed_by']);
        }

        if (!empty($filters['date'])) {
            $query->whereDate('created_at', $filters['date']);
        }

        // Soportar tanto date_from/date_to como start_date/end_date
        if (!empty($filters['date_from']) || !empty($filters['start_date'])) {
            $dateFrom = $filters['date_from'] ?? $filters['start_date'];
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if (!empty($filters['date_to']) || !empty($filters['end_date'])) {
            $dateTo = $filters['date_to'] ?? $filters['end_date'];
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if (!empty($filters['created_today'])) {
            $query->whereDate('created_at', Carbon::today());
        }

        if (!empty($filters['created_this_week'])) {
            $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
        }

        if (!empty($filters['created_this_month'])) {
            $query->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
        }

        if (isset($filters['has_vehicle'])) {
            if ($filters['has_vehicle']) {
                $query->whereNotNull('vehicle_plate')->where('vehicle_plate', '!=', '');
            } else {
                $query->where(function (Builder $q) {
                    $q->whereNull('vehicle_plate')->orWhere('vehicle_plate', '=', '');
                });
            }
        }

        if (isset($filters['has_email'])) {
            if ($filters['has_email']) {
                $query->whereNotNull('person_to_visit_email')->where('person_to_visit_email', '!=', '');
            } else {
                $query->where(function (Builder $q) {
                    $q->whereNull('person_to_visit_email')->orWhere('person_to_visit_email', '=', '');
                });
            }
        }

        $needsVisitorCount = isset($filters['visitor_count_min'])
            || isset($filters['visitor_count_max'])
            || (($filters['sort_by'] ?? null) === 'visitor_count');

        if ($needsVisitorCount) {
            $query->withCount('visitors');

            if (isset($filters['visitor_count_min'])) {
                $query->having('visitors_count', '>=', (int) $filters['visitor_count_min']);
            }

            if (isset($filters['visitor_count_max'])) {
                $query->having('visitors_count', '<=', (int) $filters['visitor_count_max']);
            }
        }

        return $query;
    }
}