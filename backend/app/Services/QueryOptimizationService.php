<?php

namespace App\Services;

use App\Models\Visit;
use App\Models\Visitor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Servicio para consultas optimizadas de la base de datos
 * 
 * Responsabilidades:
 * - Proporcionar consultas SQL optimizadas para reportes
 * - Evitar N+1 queries en operaciones complejas
 * - Centralizar lógica de agregación de datos
 * - Optimizar consultas con joins y subqueries
 */
class QueryOptimizationService
{
    /**
     * Obtener estadísticas del dashboard con una sola consulta
     */
    public function getDashboardStats(): array
    {
        // Consulta optimizada que obtiene todas las estadísticas de una vez
        $stats = DB::select("
            SELECT 
                COUNT(v.id) as total_visits,
                COUNT(CASE WHEN vs.status = 'active' THEN 1 END) as active_visits,
                COUNT(CASE WHEN vs.status = 'closed' THEN 1 END) as closed_visits,
                COUNT(CASE WHEN DATE(v.created_at) = CURDATE() THEN 1 END) as today_visits,
                COUNT(CASE WHEN YEARWEEK(v.created_at) = YEARWEEK(NOW()) THEN 1 END) as this_week_visits,
                COUNT(CASE WHEN YEAR(v.created_at) = YEAR(NOW()) AND MONTH(v.created_at) = MONTH(NOW()) THEN 1 END) as this_month_visits,
                COUNT(CASE WHEN v.vehicle_plate IS NOT NULL AND v.vehicle_plate != '' THEN 1 END) as visits_with_vehicle,
                COUNT(DISTINCT visitor_id) as unique_visitors,
                AVG(CASE 
                    WHEN v.closed_at IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, v.created_at, v.closed_at) 
                END) as avg_duration_minutes
            FROM visits v
            LEFT JOIN visit_statuses vs ON v.status_id = vs.id
            LEFT JOIN visit_visitor vv ON v.id = vv.visit_id
        ");

        return (array) $stats[0];
    }

    /**
     * Obtener top departamentos con conteos optimizados
     */
    public function getTopDepartments(int $limit = 10, ?string $period = null): array
    {
        $query = "
            SELECT 
                v.department,
                COUNT(v.id) as visits_count,
                COUNT(CASE WHEN vs.status = 'active' THEN 1 END) as active_visits,
                COUNT(DISTINCT vv.visitor_id) as unique_visitors,
                AVG(CASE 
                    WHEN v.closed_at IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, v.created_at, v.closed_at) 
                END) as avg_duration
            FROM visits v
            LEFT JOIN visit_statuses vs ON v.status_id = vs.id
            LEFT JOIN visit_visitor vv ON v.id = vv.visit_id
        ";

        // Agregar filtro de período si se especifica
        if ($period) {
            $query .= " WHERE " . $this->getPeriodWhereClause($period);
        }

        $query .= "
            GROUP BY v.department
            ORDER BY visits_count DESC
            LIMIT {$limit}
        ";

        return DB::select($query);
    }

    /**
     * Obtener visitantes frecuentes con estadísticas completas
     */
    public function getFrequentVisitors(int $minVisits = 5, int $limit = 20): array
    {
        return DB::select("
            SELECT 
                vis.id,
                vis.name,
                vis.carnet,
                vis.company,
                COUNT(v.id) as total_visits,
                COUNT(CASE WHEN vs.status = 'active' THEN 1 END) as active_visits,
                MAX(v.created_at) as last_visit_date,
                MIN(v.created_at) as first_visit_date,
                COUNT(DISTINCT v.department) as departments_visited,
                GROUP_CONCAT(DISTINCT v.department LIMIT 3) as favorite_departments
            FROM visitors vis
            INNER JOIN visit_visitor vv ON vis.id = vv.visitor_id
            INNER JOIN visits v ON vv.visit_id = v.id
            LEFT JOIN visit_statuses vs ON v.status_id = vs.id
            GROUP BY vis.id, vis.name, vis.carnet, vis.company
            HAVING total_visits >= {$minVisits}
            ORDER BY total_visits DESC
            LIMIT {$limit}
        ");
    }

    /**
     * Obtener distribución horaria de visitas
     */
    public function getHourlyDistribution(?string $period = null): array
    {
        $query = "
            SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as visits_count,
                COUNT(CASE WHEN vs.status = 'active' THEN 1 END) as active_count,
                AVG(TIMESTAMPDIFF(MINUTE, created_at, COALESCE(closed_at, NOW()))) as avg_duration
            FROM visits v
            LEFT JOIN visit_statuses vs ON v.status_id = vs.id
        ";

        if ($period) {
            $query .= " WHERE " . $this->getPeriodWhereClause($period);
        }

        $query .= "
            GROUP BY HOUR(created_at)
            ORDER BY hour
        ";

        return DB::select($query);
    }

    /**
     * Obtener estadísticas mensuales completas
     */
    public function getMonthlyStats(int $year, int $month): array
    {
        return DB::select("
            SELECT 
                DATE(v.created_at) as visit_date,
                COUNT(v.id) as visits_count,
                COUNT(CASE WHEN vs.status = 'closed' THEN 1 END) as closed_visits,
                COUNT(DISTINCT vv.visitor_id) as unique_visitors,
                COUNT(CASE WHEN v.vehicle_plate IS NOT NULL THEN 1 END) as visits_with_vehicle,
                AVG(CASE 
                    WHEN v.closed_at IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, v.created_at, v.closed_at) 
                END) as avg_duration,
                GROUP_CONCAT(DISTINCT v.department ORDER BY COUNT(*) DESC LIMIT 3) as top_departments
            FROM visits v
            LEFT JOIN visit_statuses vs ON v.status_id = vs.id
            LEFT JOIN visit_visitor vv ON v.id = vv.visit_id
            WHERE YEAR(v.created_at) = {$year} AND MONTH(v.created_at) = {$month}
            GROUP BY DATE(v.created_at)
            ORDER BY visit_date
        ");
    }

    /**
     * Obtener rendimiento de usuarios
     */
    public function getUserPerformance(?string $period = null): array
    {
        $query = "
            SELECT 
                u.id,
                u.name,
                u.email,
                r.name as role_name,
                COUNT(DISTINCT v_created.id) as visits_created,
                COUNT(DISTINCT v_closed.id) as visits_closed,
                COUNT(DISTINCT vis_created.id) as visitors_created,
                MAX(COALESCE(v_created.created_at, v_closed.closed_at, vis_created.created_at)) as last_activity
            FROM users u
            LEFT JOIN role_user ru ON u.id = ru.user_id
            LEFT JOIN roles r ON ru.role_id = r.id
            LEFT JOIN visits v_created ON u.id = v_created.user_id";

        if ($period) {
            $query .= " AND " . str_replace('v.created_at', 'v_created.created_at', $this->getPeriodWhereClause($period));
        }

        $query .= "
            LEFT JOIN visits v_closed ON u.id = v_closed.closed_by_user_id";

        if ($period) {
            $query .= " AND " . str_replace('v.created_at', 'v_closed.closed_at', $this->getPeriodWhereClause($period));
        }

        $query .= "
            LEFT JOIN visitors vis_created ON u.id = vis_created.user_id";

        if ($period) {
            $query .= " AND " . str_replace('v.created_at', 'vis_created.created_at', $this->getPeriodWhereClause($period));
        }

        $query .= "
            WHERE u.is_active = 1
            GROUP BY u.id, u.name, u.email, r.name
            ORDER BY (visits_created + visits_closed + visitors_created) DESC
        ";

        return DB::select($query);
    }

    /**
     * Búsqueda optimizada con scoring de relevancia
     */
    public function searchVisitsWithScoring(string $searchTerm, int $limit = 50): array
    {
        return DB::select("
            SELECT 
                v.*,
                vs.status,
                u.name as creator_name,
                GROUP_CONCAT(vis.name SEPARATOR ', ') as visitor_names,
                (
                    CASE WHEN v.namePersonToVisit LIKE ? THEN 10 ELSE 0 END +
                    CASE WHEN v.department LIKE ? THEN 8 ELSE 0 END +
                    CASE WHEN v.reason LIKE ? THEN 6 ELSE 0 END +
                    CASE WHEN vis.name LIKE ? THEN 9 ELSE 0 END +
                    CASE WHEN vis.carnet LIKE ? THEN 7 ELSE 0 END +
                    CASE WHEN v.vehicle_plate LIKE ? THEN 5 ELSE 0 END
                ) as relevance_score
            FROM visits v
            LEFT JOIN visit_statuses vs ON v.status_id = vs.id
            LEFT JOIN users u ON v.user_id = u.id
            LEFT JOIN visit_visitor vv ON v.id = vv.visit_id
            LEFT JOIN visitors vis ON vv.visitor_id = vis.id
            WHERE (
                v.namePersonToVisit LIKE ? OR
                v.department LIKE ? OR
                v.reason LIKE ? OR
                vis.name LIKE ? OR
                vis.carnet LIKE ? OR
                v.vehicle_plate LIKE ?
            )
            GROUP BY v.id
            ORDER BY relevance_score DESC, v.created_at DESC
            LIMIT {$limit}
        ", array_fill(0, 12, "%{$searchTerm}%"));
    }

    /**
     * Obtener consulta con caché de conteos para evitar recálculos
     */
    public function getVisitsWithCachedCounts(array $visitIds): array
    {
        if (empty($visitIds)) {
            return [];
        }

        $idsStr = implode(',', array_map('intval', $visitIds));

        return DB::select("
            SELECT 
                v.*,
                vs.status,
                u.name as creator_name,
                uc.name as closer_name,
                v_counts.visitor_count,
                v_counts.visitor_names
            FROM visits v
            LEFT JOIN visit_statuses vs ON v.status_id = vs.id
            LEFT JOIN users u ON v.user_id = u.id
            LEFT JOIN users uc ON v.closed_by_user_id = uc.id
            LEFT JOIN (
                SELECT 
                    vv.visit_id,
                    COUNT(vv.visitor_id) as visitor_count,
                    GROUP_CONCAT(vis.name ORDER BY vis.name SEPARATOR ', ') as visitor_names
                FROM visit_visitor vv
                INNER JOIN visitors vis ON vv.visitor_id = vis.id
                WHERE vv.visit_id IN ({$idsStr})
                GROUP BY vv.visit_id
            ) v_counts ON v.id = v_counts.visit_id
            WHERE v.id IN ({$idsStr})
            ORDER BY v.created_at DESC
        ");
    }

    /**
     * Generar cláusula WHERE para períodos de tiempo
     */
    private function getPeriodWhereClause(string $period): string
    {
        return match($period) {
            'today' => "DATE(v.created_at) = CURDATE()",
            'yesterday' => "DATE(v.created_at) = DATE(CURDATE() - INTERVAL 1 DAY)",
            'this_week' => "YEARWEEK(v.created_at) = YEARWEEK(NOW())",
            'last_week' => "YEARWEEK(v.created_at) = YEARWEEK(NOW() - INTERVAL 1 WEEK)",
            'this_month' => "YEAR(v.created_at) = YEAR(NOW()) AND MONTH(v.created_at) = MONTH(NOW())",
            'last_month' => "v.created_at >= DATE(CURDATE() - INTERVAL 1 MONTH) AND v.created_at < DATE(CURDATE() - INTERVAL 0 MONTH)",
            'this_year' => "YEAR(v.created_at) = YEAR(NOW())",
            'last_30_days' => "v.created_at >= DATE(CURDATE() - INTERVAL 30 DAY)",
            default => "1=1"
        };
    }

    /**
     * Optimizar consultas eliminando N+1 problems
     */
    public function preloadVisitsWithAllRelations(int $limit = 100, array $filters = []): array
    {
        $query = Visit::withOptimizedRelations()
                     ->withCounts();

        // Aplicar filtros
        if (isset($filters['status'])) {
            $query->whereHas('visitStatus', function($q) use ($filters) {
                $q->where('status', $filters['status']);
            });
        }

        if (isset($filters['department'])) {
            $query->byDepartment($filters['department']);
        }

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->dateRange($filters['date_from'], $filters['date_to']);
        }

        if (isset($filters['search'])) {
            $query->search($filters['search']);
        }

        return $query->latest()
                    ->limit($limit)
                    ->get()
                    ->toArray();
    }
}