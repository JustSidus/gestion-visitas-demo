<?php

namespace App\Jobs;

use App\Services\CacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job para precalentar el cache del sistema
 * 
 * Responsabilidades:
 * - Precalentar datos frecuentemente consultados
 * - Mejorar el rendimiento para los primeros usuarios del día
 * - Ejecutarse automáticamente en horarios programados
 * - Minimizar la latencia en consultas importantes
 */
class WarmupCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * El número de veces que se puede intentar el job.
     */
    public int $tries = 2;

    /**
     * El número de segundos después de los cuales el job puede timeoutear.
     */
    public int $timeout = 180; // 3 minutos

    /**
     * Tipo de precalentamiento
     */
    private string $warmupType;

    /**
     * Create a new job instance.
     */
    public function __construct(string $warmupType = 'daily')
    {
        $this->warmupType = $warmupType;
        
        // Configurar la cola específica para cache
        $this->onQueue('cache');
        
        Log::info("WarmupCacheJob queued", [
            'warmup_type' => $warmupType,
            'queue' => 'cache'
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(CacheService $cacheService): void
    {
        try {
            Log::info("Processing WarmupCacheJob", [
                'warmup_type' => $this->warmupType,
                'attempt' => $this->attempts()
            ]);

            $warmedItems = 0;

            // Ejecutar precalentamiento según el tipo
            $warmedItems = match($this->warmupType) {
                'daily' => $this->warmupDailyCache($cacheService),
                'statistics' => $this->warmupStatisticsCache($cacheService),
                'reports' => $this->warmupReportsCache($cacheService),
                'full' => $this->warmupFullCache($cacheService),
                default => throw new \InvalidArgumentException("Warmup type '{$this->warmupType}' not supported")
            };

            Log::info("Cache warmup completed successfully", [
                'warmup_type' => $this->warmupType,
                'items_warmed' => $warmedItems
            ]);

        } catch (\Exception $e) {
            Log::error("WarmupCacheJob failed", [
                'warmup_type' => $this->warmupType,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Precalentar cache diario básico
     */
    private function warmupDailyCache(CacheService $cacheService): int
    {
        $warmed = 0;

        try {
            // Estadísticas diarias
            $cacheService->getDailyPerformance();
            $warmed++;
            
            // Departamentos únicos
            $cacheService->getDepartments();
            $warmed++;
            
            // Usuarios activos
            $cacheService->getActiveUsers();
            $warmed++;

            Log::info("Daily cache warmup completed", [
                'items_warmed' => $warmed
            ]);

        } catch (\Exception $e) {
            Log::warning("Error warming daily cache", [
                'error' => $e->getMessage()
            ]);
        }

        return $warmed;
    }

    /**
     * Precalentar cache de estadísticas
     */
    private function warmupStatisticsCache(CacheService $cacheService): int
    {
        $warmed = 0;

        try {
            // Estadísticas de visitas
            $cacheService->getVisitStatistics();
            $warmed++;
            
            // Estadísticas de visitantes
            $cacheService->getVisitorStatistics();
            $warmed++;
            
            // Visitantes frecuentes
            $cacheService->getFrequentVisitors();
            $warmed++;

            Log::info("Statistics cache warmup completed", [
                'items_warmed' => $warmed
            ]);

        } catch (\Exception $e) {
            Log::warning("Error warming statistics cache", [
                'error' => $e->getMessage()
            ]);
        }

        return $warmed;
    }

    /**
     * Precalentar cache de reportes
     */
    private function warmupReportsCache(CacheService $cacheService): int
    {
        $warmed = 0;

        try {
            // Reporte del mes actual
            $currentYear = now()->year;
            $currentMonth = now()->month;
            $cacheService->getMonthlyReport($currentYear, $currentMonth);
            $warmed++;
            
            // Reporte del mes anterior
            $lastMonth = now()->subMonth();
            $cacheService->getMonthlyReport($lastMonth->year, $lastMonth->month);
            $warmed++;

            Log::info("Reports cache warmup completed", [
                'items_warmed' => $warmed
            ]);

        } catch (\Exception $e) {
            Log::warning("Error warming reports cache", [
                'error' => $e->getMessage()
            ]);
        }

        return $warmed;
    }

    /**
     * Precalentar todo el cache
     */
    private function warmupFullCache(CacheService $cacheService): int
    {
        $totalWarmed = 0;
        
        $totalWarmed += $this->warmupDailyCache($cacheService);
        $totalWarmed += $this->warmupStatisticsCache($cacheService);
        $totalWarmed += $this->warmupReportsCache($cacheService);
        
        return $totalWarmed;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("WarmupCacheJob permanently failed", [
            'warmup_type' => $this->warmupType,
            'final_error' => $exception->getMessage(),
            'attempts_made' => $this->attempts()
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'warmup:' . $this->warmupType,
            'cache',
            'performance'
        ];
    }
}