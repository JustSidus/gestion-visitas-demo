<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\LoggerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MonitorLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:monitor 
                            {--period=1h : Time period to analyze (1h, 24h, 7d, 30d)}
                            {--threshold=100 : Alert threshold for error count}
                            {--export : Export metrics to file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor system logs and generate health reports';

    protected LoggerService $logger;

    public function __construct(LoggerService $logger)
    {
        parent::__construct();
        $this->logger = $logger;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $period = $this->option('period');
        $threshold = (int) $this->option('threshold');
        $export = $this->option('export');

        $this->info(" Monitoring logs for period: {$period}");
        $this->newLine();

        // Obtener métricas del período
        $metrics = $this->getLogMetrics($period);
        
        // Mostrar resumen
        $this->displayMetrics($metrics);
        
        // Verificar umbrales y generar alertas
        $alerts = $this->checkAlerts($metrics, $threshold);
        
        if (!empty($alerts)) {
            $this->displayAlerts($alerts);
        } else {
            $this->info(' All metrics within normal thresholds');
        }

        // Exportar métricas si se solicita
        if ($export) {
            $this->exportMetrics($metrics, $period);
        }

        // Log del monitoreo
        $this->logger->health('log_monitoring', empty($alerts), [
            'period' => $period,
            'metrics' => $metrics,
            'alerts_count' => count($alerts),
        ]);

        return Command::SUCCESS;
    }

    /**
     * Get log metrics for the specified period
     */
    protected function getLogMetrics(string $period): array
    {
        $startTime = $this->getStartTime($period);
        
        return [
            'period' => $period,
            'start_time' => $startTime->toISOString(),
            'audit_logs' => $this->getAuditMetrics($startTime),
            'error_analysis' => $this->getErrorAnalysis($startTime),
            'security_events' => $this->getSecurityMetrics($startTime),
            'performance' => $this->getPerformanceMetrics($startTime),
            'api_metrics' => $this->getApiMetrics($startTime),
        ];
    }

    /**
     * Get audit log metrics
     */
    protected function getAuditMetrics(\Carbon\Carbon $startTime): array
    {
        return [
            'total_events' => AuditLog::where('created_at', '>=', $startTime)->count(),
            'by_action' => AuditLog::where('created_at', '>=', $startTime)
                ->groupBy('action')
                ->selectRaw('action, count(*) as count')
                ->pluck('count', 'action')
                ->toArray(),
            'by_severity' => AuditLog::where('created_at', '>=', $startTime)
                ->groupBy('severity')
                ->selectRaw('severity, count(*) as count')
                ->pluck('count', 'severity')
                ->toArray(),
            'unique_users' => AuditLog::where('created_at', '>=', $startTime)
                ->distinct('user_id')
                ->count('user_id'),
            'security_incidents' => AuditLog::where('created_at', '>=', $startTime)
                ->whereJsonContains('tags', 'security')
                ->count(),
        ];
    }

    /**
     * Get error analysis from log files
     */
    protected function getErrorAnalysis(\Carbon\Carbon $startTime): array
    {
        $errorLogPath = storage_path('logs/errors');
        
        if (!is_dir($errorLogPath)) {
            return ['total_errors' => 0, 'error_types' => []];
        }

        // Analizar archivos de error del período
        $errorCount = 0;
        $errorTypes = [];

        $files = glob($errorLogPath . '/*.log');
        foreach ($files as $file) {
            if (filemtime($file) >= $startTime->timestamp) {
                $content = file_get_contents($file);
                $lines = explode("\n", $content);
                
                foreach ($lines as $line) {
                    if (empty(trim($line))) continue;
                    
                    $errorCount++;
                    
                    // Extraer tipo de error
                    if (preg_match('/\[(\d{4}-\d{2}-\d{2}[^\]]+)\][^:]+: (.+?)(?:\{|$)/', $line, $matches)) {
                        $errorType = $this->classifyError($matches[2]);
                        $errorTypes[$errorType] = ($errorTypes[$errorType] ?? 0) + 1;
                    }
                }
            }
        }

        return [
            'total_errors' => $errorCount,
            'error_types' => $errorTypes,
        ];
    }

    /**
     * Get security metrics
     */
    protected function getSecurityMetrics(\Carbon\Carbon $startTime): array
    {
        return [
            'failed_logins' => AuditLog::where('created_at', '>=', $startTime)
                ->where('action', 'login')
                ->where('status_code', '>=', 400)
                ->count(),
            'unauthorized_access' => AuditLog::where('created_at', '>=', $startTime)
                ->whereJsonContains('tags', 'unauthorized_access')
                ->count(),
            'suspicious_activity' => AuditLog::where('created_at', '>=', $startTime)
                ->whereJsonContains('tags', 'suspicious')
                ->count(),
            'rate_limit_violations' => AuditLog::where('created_at', '>=', $startTime)
                ->whereJsonContains('tags', 'rate_limit_exceeded')
                ->count(),
        ];
    }

    /**
     * Get performance metrics
     */
    protected function getPerformanceMetrics(\Carbon\Carbon $startTime): array
    {
        return [
            'slow_requests' => AuditLog::where('created_at', '>=', $startTime)
                ->where('duration_ms', '>', 5000) // > 5 segundos
                ->count(),
            'avg_response_time' => AuditLog::where('created_at', '>=', $startTime)
                ->whereNotNull('duration_ms')
                ->avg('duration_ms'),
            'max_response_time' => AuditLog::where('created_at', '>=', $startTime)
                ->whereNotNull('duration_ms')
                ->max('duration_ms'),
        ];
    }

    /**
     * Get API metrics
     */
    protected function getApiMetrics(\Carbon\Carbon $startTime): array
    {
        return [
            'total_requests' => AuditLog::where('created_at', '>=', $startTime)
                ->whereNotNull('request_method')
                ->count(),
            'by_status_code' => AuditLog::where('created_at', '>=', $startTime)
                ->whereNotNull('status_code')
                ->groupBy('status_code')
                ->selectRaw('status_code, count(*) as count')
                ->pluck('count', 'status_code')
                ->toArray(),
            'by_endpoint' => AuditLog::where('created_at', '>=', $startTime)
                ->whereNotNull('request_url')
                ->groupBy('request_url')
                ->selectRaw('request_url, count(*) as count')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->pluck('count', 'request_url')
                ->toArray(),
        ];
    }

    /**
     * Display metrics in formatted table
     */
    protected function displayMetrics(array $metrics): void
    {
        $this->info(' System Metrics Summary');
        $this->info('Period: ' . $metrics['period'] . ' (from ' . \Carbon\Carbon::parse($metrics['start_time'])->format('Y-m-d H:i') . ')');
        $this->newLine();

        // Audit Logs
        $this->info(' Audit Events: ' . $metrics['audit_logs']['total_events']);
        if (!empty($metrics['audit_logs']['by_severity'])) {
            foreach ($metrics['audit_logs']['by_severity'] as $severity => $count) {
                $this->line("  - {$severity}: {$count}");
            }
        }
        $this->newLine();

        // Errors
        $this->info(' Errors: ' . $metrics['error_analysis']['total_errors']);
        if (!empty($metrics['error_analysis']['error_types'])) {
            foreach ($metrics['error_analysis']['error_types'] as $type => $count) {
                $this->line("  - {$type}: {$count}");
            }
        }
        $this->newLine();

        // Security
        $security = $metrics['security_events'];
        $this->info('️ Security Events:');
        $this->line("  - Failed logins: {$security['failed_logins']}");
        $this->line("  - Unauthorized access: {$security['unauthorized_access']}");
        $this->line("  - Suspicious activity: {$security['suspicious_activity']}");
        $this->newLine();

        // Performance
        $performance = $metrics['performance'];
        $this->info(' Performance:');
        $this->line("  - Slow requests: {$performance['slow_requests']}");
        $this->line("  - Avg response time: " . round($performance['avg_response_time'], 2) . "ms");
        $this->line("  - Max response time: " . round($performance['max_response_time'], 2) . "ms");
        $this->newLine();
    }

    /**
     * Check for alerts based on thresholds
     */
    protected function checkAlerts(array $metrics, int $errorThreshold): array
    {
        $alerts = [];

        // Error threshold
        if ($metrics['error_analysis']['total_errors'] > $errorThreshold) {
            $alerts[] = [
                'type' => 'error_count',
                'message' => "Error count ({$metrics['error_analysis']['total_errors']}) exceeds threshold ({$errorThreshold})",
                'severity' => 'high'
            ];
        }

        // Security alerts
        $security = $metrics['security_events'];
        if ($security['failed_logins'] > 50) {
            $alerts[] = [
                'type' => 'security',
                'message' => "High number of failed logins: {$security['failed_logins']}",
                'severity' => 'medium'
            ];
        }

        if ($security['unauthorized_access'] > 10) {
            $alerts[] = [
                'type' => 'security',
                'message' => "Unauthorized access attempts: {$security['unauthorized_access']}",
                'severity' => 'high'
            ];
        }

        // Performance alerts
        $performance = $metrics['performance'];
        if ($performance['slow_requests'] > 100) {
            $alerts[] = [
                'type' => 'performance',
                'message' => "High number of slow requests: {$performance['slow_requests']}",
                'severity' => 'medium'
            ];
        }

        return $alerts;
    }

    /**
     * Display alerts
     */
    protected function displayAlerts(array $alerts): void
    {
        $this->newLine();
        $this->error(' ALERTS DETECTED:');
        
        foreach ($alerts as $alert) {
            $emoji = $alert['severity'] === 'high' ? '' : '';
            $this->line("{$emoji} [{$alert['type']}] {$alert['message']}");
            
            // Log alert
            $this->logger->security('monitoring_alert', [
                'alert_type' => $alert['type'],
                'message' => $alert['message'],
                'severity' => $alert['severity'],
            ], $alert['severity'] === 'high' ? 'alert' : 'warning');
        }
    }

    /**
     * Export metrics to file
     */
    protected function exportMetrics(array $metrics, string $period): void
    {
        $filename = 'metrics_' . now()->format('Y-m-d_H-i-s') . '_' . $period . '.json';
        $path = 'logs/metrics/' . $filename;
        
        Storage::put($path, json_encode($metrics, JSON_PRETTY_PRINT));
        
        $this->info(" Metrics exported to: storage/app/{$path}");
    }

    /**
     * Get start time based on period
     */
    protected function getStartTime(string $period): \Carbon\Carbon
    {
        return match ($period) {
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subHour(),
        };
    }

    /**
     * Classify error type from error message
     */
    protected function classifyError(string $message): string
    {
        if (str_contains($message, 'database') || str_contains($message, 'SQL')) {
            return 'database';
        }
        
        if (str_contains($message, 'authentication') || str_contains($message, 'login')) {
            return 'authentication';
        }
        
        if (str_contains($message, 'validation') || str_contains($message, 'required')) {
            return 'validation';
        }
        
        if (str_contains($message, 'permission') || str_contains($message, 'authorized')) {
            return 'authorization';
        }
        
        return 'general';
    }
}

