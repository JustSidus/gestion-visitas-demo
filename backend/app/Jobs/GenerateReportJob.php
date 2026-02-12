<?php

namespace App\Jobs;

use App\Services\ExportService;
use App\Services\CacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

/**
 * Job para generar reportes de forma asíncrona
 * 
 * Responsabilidades:
 * - Generar reportes Excel/PDF en background
 * - Manejar grandes volúmenes de datos sin timeout
 * - Notificar al usuario cuando el reporte esté listo
 * - Gestionar almacenamiento de archivos temporales
 */
class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * El número de veces que se puede intentar el job.
     */
    public int $tries = 2;

    /**
     * El número de segundos después de los cuales el job puede timeoutear.
     */
    public int $timeout = 600; // 10 minutos para reportes grandes

    /**
     * Eliminar el job si sus modelos dependientes no existen.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Tipo de reporte a generar
     */
    private string $reportType;

    /**
     * Formato de salida (excel, pdf, csv)
     */
    private string $format;

    /**
     * Filtros para el reporte
     */
    private array $filters;

    /**
     * ID del usuario que solicita el reporte
     */
    private int $userId;

    /**
     * Email del usuario para notificar cuando esté listo
     */
    private string $userEmail;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $reportType, 
        string $format, 
        array $filters, 
        int $userId, 
        string $userEmail
    ) {
        $this->reportType = $reportType;
        $this->format = $format;
        $this->filters = $filters;
        $this->userId = $userId;
        $this->userEmail = $userEmail;
        
        // Configurar la cola específica para reportes
        $this->onQueue('reports');
        
        Log::info("GenerateReportJob queued", [
            'report_type' => $reportType,
            'format' => $format,
            'user_id' => $userId,
            'queue' => 'reports'
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(ExportService $exportService, CacheService $cacheService): void
    {
        try {
            Log::info("Processing GenerateReportJob", [
                'report_type' => $this->reportType,
                'format' => $this->format,
                'user_id' => $this->userId,
                'attempt' => $this->attempts()
            ]);

            // Generar nombre único para el archivo
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $filename = "{$this->reportType}_{$timestamp}_{$this->userId}.{$this->format}";

            // Generar el reporte según el tipo
            $filePath = match($this->reportType) {
                'visits' => $this->generateVisitsReport($exportService, $filename),
                'visitors' => $this->generateVisitorsReport($exportService, $filename),
                'monthly' => $this->generateMonthlyReport($exportService, $filename),
                'department_stats' => $this->generateDepartmentStatsReport($exportService, $filename),
                'user_activity' => $this->generateUserActivityReport($exportService, $filename),
                default => throw new \InvalidArgumentException("Report type '{$this->reportType}' not supported")
            };

            if ($filePath && Storage::exists($filePath)) {
                // Guardar información del reporte en cache para descarga
                $reportInfo = [
                    'file_path' => $filePath,
                    'filename' => $filename,
                    'report_type' => $this->reportType,
                    'format' => $this->format,
                    'generated_at' => Carbon::now()->toISOString(),
                    'user_id' => $this->userId,
                    'file_size' => Storage::size($filePath),
                    'expires_at' => Carbon::now()->addHours(24)->toISOString() // 24 horas para descargar
                ];

                // Guardar en cache con clave única
                $cacheKey = "report_{$this->userId}_{$timestamp}";
                $cacheService->put($cacheKey, $reportInfo, 1440); // 24 horas

                // Notificar al usuario (aquí podrías disparar otro Job para email)
                $this->notifyUserReportReady($cacheKey, $filename);

                Log::info("Report generated successfully", [
                    'report_type' => $this->reportType,
                    'format' => $this->format,
                    'user_id' => $this->userId,
                    'file_path' => $filePath,
                    'file_size' => Storage::size($filePath),
                    'cache_key' => $cacheKey
                ]);

            } else {
                throw new \Exception("Report file was not generated or saved properly");
            }

        } catch (\Exception $e) {
            Log::error("GenerateReportJob failed", [
                'report_type' => $this->reportType,
                'format' => $this->format,
                'user_id' => $this->userId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Notificar al usuario del error
            $this->notifyUserReportError($e->getMessage());

            // Re-lanzar la excepción para activar el sistema de reintentos
            throw $e;
        }
    }

    /**
     * Generar reporte de visitas
     */
    private function generateVisitsReport(ExportService $exportService, string $filename): string
    {
        Log::info("Generating visits report", [
            'filters' => $this->filters,
            'format' => $this->format
        ]);

        return match($this->format) {
            'excel' => $exportService->exportVisitsToExcel($this->filters, $filename),
            'pdf' => $exportService->exportVisitsToPdf($this->filters, $filename),
            'csv' => $exportService->exportVisitsToCsv($this->filters, $filename),
            default => throw new \InvalidArgumentException("Format '{$this->format}' not supported")
        };
    }

    /**
     * Generar reporte de visitantes
     */
    private function generateVisitorsReport(ExportService $exportService, string $filename): string
    {
        Log::info("Generating visitors report", [
            'filters' => $this->filters,
            'format' => $this->format
        ]);

        return match($this->format) {
            'excel' => $exportService->exportVisitorsToExcel($this->filters, $filename),
            'pdf' => $exportService->exportVisitorsToPdf($this->filters, $filename),
            'csv' => $exportService->exportVisitorsToCsv($this->filters, $filename),
            default => throw new \InvalidArgumentException("Format '{$this->format}' not supported")
        };
    }

    /**
     * Generar reporte mensual
     */
    private function generateMonthlyReport(ExportService $exportService, string $filename): string
    {
        Log::info("Generating monthly report", [
            'filters' => $this->filters,
            'format' => $this->format
        ]);

        $year = $this->filters['year'] ?? Carbon::now()->year;
        $month = $this->filters['month'] ?? Carbon::now()->month;

        return match($this->format) {
            'excel' => $exportService->exportMonthlyStatsToExcel($year, $month, $filename),
            'pdf' => $exportService->exportMonthlyStatsToPdf($year, $month, $filename),
            default => throw new \InvalidArgumentException("Format '{$this->format}' not supported for monthly reports")
        };
    }

    /**
     * Generar reporte de estadísticas de departamentos
     */
    private function generateDepartmentStatsReport(ExportService $exportService, string $filename): string
    {
        Log::info("Generating department stats report", [
            'filters' => $this->filters,
            'format' => $this->format
        ]);

        return match($this->format) {
            'excel' => $exportService->exportDepartmentStatsToExcel($this->filters, $filename),
            'pdf' => $exportService->exportDepartmentStatsToPdf($this->filters, $filename),
            default => throw new \InvalidArgumentException("Format '{$this->format}' not supported for department stats")
        };
    }

    /**
     * Generar reporte de actividad de usuarios
     */
    private function generateUserActivityReport(ExportService $exportService, string $filename): string
    {
        Log::info("Generating user activity report", [
            'filters' => $this->filters,
            'format' => $this->format
        ]);

        return match($this->format) {
            'excel' => $exportService->exportUserActivityToExcel($this->filters, $filename),
            'pdf' => $exportService->exportUserActivityToPdf($this->filters, $filename),
            default => throw new \InvalidArgumentException("Format '{$this->format}' not supported for user activity")
        };
    }

    /**
     * Notificar al usuario que el reporte está listo
     */
    private function notifyUserReportReady(string $cacheKey, string $filename): void
    {
        // Aquí podrías disparar otro Job para enviar email
        // O usar un sistema de notificaciones en tiempo real como WebSockets
        
        Log::info("User notified - report ready", [
            'user_id' => $this->userId,
            'user_email' => $this->userEmail,
            'cache_key' => $cacheKey,
            'filename' => $filename
        ]);

        // Ejemplo: Disparar job de notificación por email
        // SendReportNotificationJob::dispatch($this->userId, $this->userEmail, $cacheKey, 'ready');
    }

    /**
     * Notificar al usuario de error en la generación
     */
    private function notifyUserReportError(string $error): void
    {
        Log::info("User notified - report error", [
            'user_id' => $this->userId,
            'user_email' => $this->userEmail,
            'error' => $error
        ]);

        // Ejemplo: Disparar job de notificación por email
        // SendReportNotificationJob::dispatch($this->userId, $this->userEmail, null, 'error', ['error' => $error]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("GenerateReportJob permanently failed", [
            'report_type' => $this->reportType,
            'format' => $this->format,
            'user_id' => $this->userId,
            'final_error' => $exception->getMessage(),
            'attempts_made' => $this->attempts()
        ]);

        // Notificar al usuario del fallo permanente
        $this->notifyUserReportError("No se pudo generar el reporte después de múltiples intentos.");
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        // Reintentar después de 2 minutos para reportes
        return [120];
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'report:' . $this->reportType,
            'format:' . $this->format,
            'user:' . $this->userId,
            'export'
        ];
    }
}