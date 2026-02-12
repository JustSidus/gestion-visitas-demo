<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Servicio centralizado de caché inteligente
 * 
 * Responsabilidades:
 * - Gestionar cache con tags para invalidación selectiva
 * - Proporcionar métodos específicos para datos del sistema
 * - Manejar expiración y actualización automática
 * - Optimizar consultas frecuentes
 */
class CacheService
{
    // Constantes de tiempo de vida del cache (en minutos)
    const CACHE_TTL_SHORT = 5;     // 5 minutos - datos que cambian frecuentemente
    const CACHE_TTL_MEDIUM = 30;   // 30 minutos - estadísticas regulares
    const CACHE_TTL_LONG = 120;    // 2 horas - datos estáticos como departamentos
    const CACHE_TTL_DAILY = 1440;  // 24 horas - estadísticas diarias

    // Tags para organizar y limpiar cache por categorías
    const TAG_VISITS = 'visits';
    const TAG_VISITORS = 'visitors';
    const TAG_USERS = 'users';
    const TAG_STATISTICS = 'statistics';
    const TAG_DEPARTMENTS = 'departments';
    const TAG_REPORTS = 'reports';

    /**
     * Obtener valor del cache o ejecutar callback si no existe
     */
    public function remember(string $key, int $ttl, callable $callback, array $tags = [])
    {
        try {
            if (!empty($tags)) {
                return Cache::tags($tags)->remember($key, $ttl, $callback);
            }
            
            return Cache::remember($key, $ttl, $callback);
        } catch (\Exception $e) {
            Log::warning("Cache error for key '{$key}': " . $e->getMessage());
            // Si falla el cache, ejecutar directamente la función
            return $callback();
        }
    }

    /**
     * Almacenar valor en cache con tags
     */
    public function put(string $key, $value, int $ttl, array $tags = []): bool
    {
        try {
            if (!empty($tags)) {
                return Cache::tags($tags)->put($key, $value, $ttl);
            }
            
            return Cache::put($key, $value, $ttl);
        } catch (\Exception $e) {
            Log::warning("Failed to cache key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener valor del cache
     */
    public function get(string $key, $default = null)
    {
        try {
            return Cache::get($key, $default);
        } catch (\Exception $e) {
            Log::warning("Failed to get cache key '{$key}': " . $e->getMessage());
            return $default;
        }
    }

    /**
     * Eliminar cache por clave específica
     */
    public function forget(string $key): bool
    {
        try {
            return Cache::forget($key);
        } catch (\Exception $e) {
            Log::warning("Failed to forget cache key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Limpiar cache por tags
     */
    public function flush(array $tags): bool
    {
        try {
            Cache::tags($tags)->flush();
            Log::info("Cache flushed for tags: " . implode(', ', $tags));
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to flush cache tags: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener estadísticas de visitas en cache
     */
    public function getVisitStatistics(): array
    {
        return $this->remember(
            'visit_statistics_' . now()->format('Y_m_d'),
            self::CACHE_TTL_MEDIUM,
            function() {
                // Esta lógica se implementará con el repository
                return app(\App\Repositories\Contracts\VisitRepositoryInterface::class)
                    ->getStatistics();
            },
            [self::TAG_VISITS, self::TAG_STATISTICS]
        );
    }

    /**
     * Obtener estadísticas de visitantes en cache
     */
    public function getVisitorStatistics(): array
    {
        return $this->remember(
            'visitor_statistics_' . now()->format('Y_m_d'),
            self::CACHE_TTL_MEDIUM,
            function() {
                return app(\App\Repositories\Contracts\VisitorRepositoryInterface::class)
                    ->getStatistics();
            },
            [self::TAG_VISITORS, self::TAG_STATISTICS]
        );
    }

    /**
     * Obtener lista de departamentos únicos en cache
     */
    public function getDepartments(): array
    {
        return $this->remember(
            'unique_departments',
            self::CACHE_TTL_LONG,
            function() {
                return app(\App\Repositories\Contracts\VisitRepositoryInterface::class)
                    ->getUniqueDepartments();
            },
            [self::TAG_DEPARTMENTS, self::TAG_VISITS]
        );
    }

    /**
     * Obtener usuarios activos en cache
     */
    public function getActiveUsers(): array
    {
        return $this->remember(
            'active_users_' . now()->format('Y_m_d'),
            self::CACHE_TTL_MEDIUM,
            function() {
                return app(\App\Repositories\Contracts\UserRepositoryInterface::class)
                    ->getActiveUsers();
            },
            [self::TAG_USERS]
        );
    }

    /**
     * Obtener estadísticas de rendimiento del día
     */
    public function getDailyPerformance(): array
    {
        return $this->remember(
            'daily_performance_' . now()->format('Y_m_d'),
            self::CACHE_TTL_SHORT,
            function() {
                $visitRepo = app(\App\Repositories\Contracts\VisitRepositoryInterface::class);
                
                return [
                    'visits_today' => $visitRepo->getVisitsToday()->count(),
                    'active_visits' => $visitRepo->getActiveVisits()->count(),
                    'closed_today' => $visitRepo->getClosedToday()->count(),
                    'top_departments_today' => $visitRepo->getTopDepartmentsToday(5),
                    'busiest_hours' => $visitRepo->getBusiestHours(),
                    'average_visit_duration' => $visitRepo->getAverageVisitDuration()
                ];
            },
            [self::TAG_STATISTICS, self::TAG_VISITS]
        );
    }

    /**
     * Obtener visitantes frecuentes en cache
     */
    public function getFrequentVisitors(int $limit = 10): array
    {
        return $this->remember(
            "frequent_visitors_{$limit}_" . now()->format('Y_m'),
            self::CACHE_TTL_LONG,
            function() use ($limit) {
                return app(\App\Repositories\Contracts\VisitorRepositoryInterface::class)
                    ->getFrequentVisitors($limit);
            },
            [self::TAG_VISITORS, self::TAG_STATISTICS]
        );
    }

    /**
     * Obtener reportes mensuales en cache
     */
    public function getMonthlyReport(int $year, int $month): array
    {
        return $this->remember(
            "monthly_report_{$year}_{$month}",
            self::CACHE_TTL_DAILY,
            function() use ($year, $month) {
                $visitRepo = app(\App\Repositories\Contracts\VisitRepositoryInterface::class);
                
                return [
                    'total_visits' => $visitRepo->getVisitsInMonth($year, $month)->count(),
                    'unique_visitors' => $visitRepo->getUniqueVisitorsInMonth($year, $month),
                    'department_breakdown' => $visitRepo->getDepartmentStatsForMonth($year, $month),
                    'daily_distribution' => $visitRepo->getDailyDistributionForMonth($year, $month),
                    'average_duration' => $visitRepo->getAverageVisitDurationForMonth($year, $month),
                    'peak_hours' => $visitRepo->getPeakHoursForMonth($year, $month)
                ];
            },
            [self::TAG_REPORTS, self::TAG_STATISTICS]
        );
    }

    /**
     * Invalidar cache cuando se crea una nueva visita
     */
    public function invalidateVisitCaches(): void
    {
        $this->flush([self::TAG_VISITS, self::TAG_STATISTICS]);
        
        // También invalidar algunos caches específicos
        $this->forget('visit_statistics_' . now()->format('Y_m_d'));
        $this->forget('daily_performance_' . now()->format('Y_m_d'));
        
        Log::info('Visit caches invalidated due to new visit creation');
    }

    /**
     * Invalidar cache cuando se actualiza un visitante
     */
    public function invalidateVisitorCaches(): void
    {
        $this->flush([self::TAG_VISITORS]);
        
        Log::info('Visitor caches invalidated due to visitor update');
    }

    /**
     * Invalidar cache cuando se cierra una visita
     */
    public function invalidateVisitClosureCaches(): void
    {
        $this->flush([self::TAG_VISITS, self::TAG_STATISTICS]);
        
        Log::info('Visit caches invalidated due to visit closure');
    }

    /**
     * Invalidar todos los reportes
     */
    public function invalidateReportCaches(): void
    {
        $this->flush([self::TAG_REPORTS]);
        
        Log::info('Report caches invalidated');
    }

    /**
     * Obtener información del estado del cache
     */
    public function getCacheStats(): array
    {
        $keys = [
            'visit_statistics_' . now()->format('Y_m_d'),
            'visitor_statistics_' . now()->format('Y_m_d'),
            'unique_departments',
            'active_users_' . now()->format('Y_m_d'),
            'daily_performance_' . now()->format('Y_m_d')
        ];

        $stats = [
            'total_keys_checked' => count($keys),
            'cached_keys' => [],
            'missing_keys' => [],
            'cache_hit_rate' => 0
        ];

        foreach ($keys as $key) {
            if (Cache::has($key)) {
                $stats['cached_keys'][] = $key;
            } else {
                $stats['missing_keys'][] = $key;
            }
        }

        $stats['cache_hit_rate'] = count($stats['cached_keys']) > 0 ? 
            round((count($stats['cached_keys']) / count($keys)) * 100, 2) : 0;

        return $stats;
    }

    /**
     * Precalentar caches importantes
     */
    public function warmUpCache(): void
    {
        Log::info('Starting cache warm-up process');

        try {
            // Precargar estadísticas del día
            $this->getDailyPerformance();
            
            // Precargar departamentos
            $this->getDepartments();
            
            // Precargar usuarios activos
            $this->getActiveUsers();
            
            // Precargar visitantes frecuentes
            $this->getFrequentVisitors();
            
            Log::info('Cache warm-up completed successfully');
        } catch (\Exception $e) {
            Log::error('Cache warm-up failed: ' . $e->getMessage());
        }
    }
}