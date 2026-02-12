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
 * Job para limpiar archivos temporales y cache expirado
 * 
 * Responsabilidades:
 * - Limpiar archivos temporales de reportes expirados
 * - Limpiar cache obsoleto
 * - Mantener el sistema optimizado
 * - Ejecutarse programáticamente (cron job)
 */
class CleanupTempFilesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * El número de veces que se puede intentar el job.
     */
    public int $tries = 2;

    /**
     * El número de segundos después de los cuales el job puede timeoutear.
     */
    public int $timeout = 300; // 5 minutos

    /**
     * Tipo de limpieza a realizar
     */
    private string $cleanupType;

    /**
     * Create a new job instance.
     */
    public function __construct(string $cleanupType = 'all')
    {
        $this->cleanupType = $cleanupType;
        
        // Configurar la cola específica para mantenimiento
        $this->onQueue('maintenance');
        
        Log::info("CleanupTempFilesJob queued", [
            'cleanup_type' => $cleanupType,
            'queue' => 'maintenance'
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(CacheService $cacheService): void
    {
        try {
            Log::info("Processing CleanupTempFilesJob", [
                'cleanup_type' => $this->cleanupType,
                'attempt' => $this->attempts()
            ]);

            $cleanedItems = 0;

            // Ejecutar limpieza según el tipo
            match($this->cleanupType) {
                'reports' => $cleanedItems = $this->cleanupExpiredReports(),
                'cache' => $cleanedItems = $this->cleanupExpiredCache($cacheService),
                'logs' => $cleanedItems = $this->cleanupOldLogs(),
                'all' => $cleanedItems = $this->cleanupAll($cacheService),
                default => throw new \InvalidArgumentException("Cleanup type '{$this->cleanupType}' not supported")
            };

            Log::info("Cleanup completed successfully", [
                'cleanup_type' => $this->cleanupType,
                'items_cleaned' => $cleanedItems
            ]);

        } catch (\Exception $e) {
            Log::error("CleanupTempFilesJob failed", [
                'cleanup_type' => $this->cleanupType,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Limpiar reportes expirados
     */
    private function cleanupExpiredReports(): int
    {
        $cleaned = 0;
        $reportPath = storage_path('app/reports');
        
        if (!is_dir($reportPath)) {
            return $cleaned;
        }

        $files = glob($reportPath . '/*');
        $expiration = now()->subHours(24); // Archivos de más de 24 horas
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $fileTime = \Carbon\Carbon::createFromTimestamp(filemtime($file));
                
                if ($fileTime->lt($expiration)) {
                    if (unlink($file)) {
                        $cleaned++;
                        Log::info("Expired report file deleted", [
                            'file' => basename($file),
                            'size' => filesize($file)
                        ]);
                    }
                }
            }
        }

        return $cleaned;
    }

    /**
     * Limpiar cache expirado
     */
    private function cleanupExpiredCache(CacheService $cacheService): int
    {
        $cleaned = 0;
        
        // Limpiar estadísticas antiguas
        $oldStatKeys = [
            'visit_statistics_' . now()->subDays(2)->format('Y_m_d'),
            'visitor_statistics_' . now()->subDays(2)->format('Y_m_d'),
            'daily_performance_' . now()->subDays(2)->format('Y_m_d'),
            'active_users_' . now()->subDays(2)->format('Y_m_d')
        ];

        foreach ($oldStatKeys as $key) {
            if ($cacheService->forget($key)) {
                $cleaned++;
            }
        }

        // Limpiar reportes mensuales antiguos (más de 6 meses)
        $oldDate = now()->subMonths(6);
        for ($i = 0; $i < 12; $i++) {
            $date = $oldDate->copy()->addMonths($i);
            $key = "monthly_report_{$date->year}_{$date->month}";
            if ($cacheService->forget($key)) {
                $cleaned++;
            }
        }

        return $cleaned;
    }

    /**
     * Limpiar logs antiguos
     */
    private function cleanupOldLogs(): int
    {
        $cleaned = 0;
        $logPath = storage_path('logs');
        
        if (!is_dir($logPath)) {
            return $cleaned;
        }

        $files = glob($logPath . '/laravel-*.log');
        $expiration = now()->subDays(30); // Logs de más de 30 días
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $fileTime = \Carbon\Carbon::createFromTimestamp(filemtime($file));
                
                if ($fileTime->lt($expiration)) {
                    if (unlink($file)) {
                        $cleaned++;
                        Log::info("Old log file deleted", [
                            'file' => basename($file)
                        ]);
                    }
                }
            }
        }

        return $cleaned;
    }

    /**
     * Limpiar todo
     */
    private function cleanupAll(CacheService $cacheService): int
    {
        $totalCleaned = 0;
        
        $totalCleaned += $this->cleanupExpiredReports();
        $totalCleaned += $this->cleanupExpiredCache($cacheService);
        $totalCleaned += $this->cleanupOldLogs();
        
        return $totalCleaned;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("CleanupTempFilesJob permanently failed", [
            'cleanup_type' => $this->cleanupType,
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
            'cleanup:' . $this->cleanupType,
            'maintenance'
        ];
    }
}