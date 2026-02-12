<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

/**
 * Servicio centralizado de logging estructurado
 * 
 * Responsabilidades:
 * - Proporcionar métodos específicos para diferentes tipos de logs
 * - Enriquecer logs con contexto relevante del sistema
 * - Formatear logs de manera consistente
 * - Facilitar el análisis y monitoreo posterior
 * - Integrar con sistemas de alertas y métricas
 */
class LoggerService
{
    /**
     * Log security-related events
     */
    public function security(string $event, array $context = [], string $level = 'info'): void
    {
        $enrichedContext = array_merge([
            'event_type' => 'security',
            'timestamp' => now()->toISOString(),
            'session_id' => request()?->session()?->getId(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'user_id' => Auth::id(),
            'user_role' => Auth::user()?->role?->name,
        ], $context);

        Log::channel('security')->{$level}($event, $enrichedContext);

        // Alertas para eventos críticos de seguridad
        if ($level === 'critical' || $level === 'alert') {
            $this->sendSecurityAlert($event, $enrichedContext);
        }
    }

    /**
     * Log business logic events
     */
    public function business(string $event, array $context = [], string $level = 'info'): void
    {
        $enrichedContext = array_merge([
            'event_type' => 'business',
            'timestamp' => now()->toISOString(),
            'user_id' => Auth::id(),
            'user_role' => Auth::user()?->role?->name,
            'environment' => app()->environment(),
        ], $context);

        Log::channel('business')->{$level}($event, $enrichedContext);
    }

    /**
     * Log performance metrics
     */
    public function performance(string $operation, float $duration, array $metrics = []): void
    {
        $context = array_merge([
            'event_type' => 'performance',
            'operation' => $operation,
            'duration_ms' => round($duration * 1000, 2),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'timestamp' => now()->toISOString(),
            'user_id' => Auth::id(),
        ], $metrics);

        Log::channel('performance')->info("Performance: {$operation}", $context);

        // Alerta para operaciones lentas
        if ($duration > 5.0) { // > 5 segundos
            try {
                Log::channel('alerts')->warning("Slow operation detected: {$operation}", $context);
            } catch (\Throwable $e) {
                // Fallback a daily si alerts falla
                Log::channel('daily')->warning("Slow operation detected: {$operation}", $context);
            }
        }
    }

    /**
     * Log API requests and responses
     */
    public function api(Request $request, $response = null, ?float $duration = null): void
    {
        $context = [
            'event_type' => 'api',
            'method' => $request->method(),
            'endpoint' => $request->path(),
            'full_url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => Auth::id(),
            'request_size' => strlen($request->getContent()),
            'timestamp' => now()->toISOString(),
        ];

        if ($response) {
            $context['status_code'] = method_exists($response, 'getStatusCode') ? 
                $response->getStatusCode() : 200;
            $context['response_size'] = method_exists($response, 'getContent') ? 
                strlen($response->getContent()) : 0;
        }

        if ($duration !== null) {
            $context['duration_ms'] = round($duration * 1000, 2);
        }

        // Incluir parámetros de query (sin datos sensibles)
        $queryParams = $request->query();
        if (!empty($queryParams)) {
            $context['query_params'] = $this->sanitizeData($queryParams);
        }

        Log::channel('api')->info('API Request', $context);
    }

    /**
     * Log email operations
     */
    public function email(string $event, array $details = []): void
    {
        $context = array_merge([
            'event_type' => 'email',
            'timestamp' => now()->toISOString(),
            'user_id' => Auth::id(),
        ], $details);

        Log::channel('email')->info($event, $context);
    }

    /**
     * Log database operations
     */
    public function database(string $operation, string $table, array $details = []): void
    {
        $context = array_merge([
            'event_type' => 'database',
            'operation' => $operation,
            'table' => $table,
            'timestamp' => now()->toISOString(),
            'user_id' => Auth::id(),
        ], $details);

        Log::channel('database')->debug("DB Operation: {$operation} on {$table}", $context);
    }

    /**
     * Log export operations
     */
    public function export(string $type, int $recordCount, array $filters = []): void
    {
        $context = [
            'event_type' => 'export',
            'export_type' => $type,
            'record_count' => $recordCount,
            'filters_applied' => $filters,
            'timestamp' => now()->toISOString(),
            'user_id' => Auth::id(),
            'user_role' => Auth::user()?->role?->name,
        ];

        Log::channel('exports')->info("Export: {$type}", $context);
    }

    /**
     * Log visit-specific operations
     */
    public function visit(string $action, $visitId = null, array $details = []): void
    {
        $context = array_merge([
            'event_type' => 'visit',
            'action' => $action,
            'visit_id' => $visitId,
            'timestamp' => now()->toISOString(),
            'user_id' => Auth::id(),
            'user_role' => Auth::user()?->role?->name,
        ], $details);

        Log::channel('visits')->info("Visit: {$action}", $context);
    }

    /**
     * Log queue job operations
     */
    public function queue(string $jobClass, string $status, array $details = []): void
    {
        $context = array_merge([
            'event_type' => 'queue',
            'job_class' => $jobClass,
            'status' => $status,
            'timestamp' => now()->toISOString(),
        ], $details);

        $level = $status === 'failed' ? 'error' : 'info';
        Log::channel('queue')->{$level}("Queue Job: {$jobClass}", $context);
    }

    /**
     * Log application errors with enhanced context
     */
    public function error(\Throwable $exception, array $context = []): void
    {
        $enrichedContext = array_merge([
            'event_type' => 'error',
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'exception_trace' => $exception->getTraceAsString(),
            'timestamp' => now()->toISOString(),
            'user_id' => Auth::id(),
            'ip_address' => request()?->ip(),
            'url' => request()?->fullUrl(),
            'method' => request()?->method(),
        ], $context);

        Log::channel('errors')->error($exception->getMessage(), $enrichedContext);

        // Enviar alerta para errores críticos
        if ($this->isCriticalError($exception)) {
            Log::channel('alerts')->critical('Critical error detected', $enrichedContext);
        }
    }

    /**
     * Log metrics for monitoring
     */
    public function metric(string $name, $value, array $tags = []): void
    {
        $context = [
            'metric_name' => $name,
            'metric_value' => $value,
            'tags' => $tags,
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment(),
        ];

        Log::channel('metrics')->info("Metric: {$name}", $context);
    }

    /**
     * Log audit events (complementa AuditLog model)
     */
    public function audit(string $action, string $resource, $resourceId, array $details = []): void
    {
        $context = array_merge([
            'event_type' => 'audit',
            'action' => $action,
            'resource_type' => $resource,
            'resource_id' => $resourceId,
            'timestamp' => now()->toISOString(),
            'user_id' => Auth::id(),
            'user_role' => Auth::user()?->role?->name,
            'ip_address' => request()?->ip(),
            'session_id' => request()?->session()?->getId(),
        ], $details);

        Log::channel('audit')->info("Audit: {$action} on {$resource}", $context);
    }

    /**
     * Log system health checks
     */
    public function health(string $check, bool $status, array $details = []): void
    {
        $context = array_merge([
            'event_type' => 'health_check',
            'check_name' => $check,
            'status' => $status ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toISOString(),
        ], $details);

        $level = $status ? 'info' : 'warning';
        Log::channel('daily')->{$level}("Health Check: {$check}", $context);

        if (!$status) {
            Log::channel('alerts')->warning("Health check failed: {$check}", $context);
        }
    }

    /**
     * Send security alerts to monitoring systems
     */
    protected function sendSecurityAlert(string $event, array $context): void
    {
        // Aquí podrías integrar con sistemas como:
        // - Slack webhooks
        // - Email notifications
        // - PagerDuty
        // - SMS alerts
        // - SIEM systems

        Log::channel('alerts')->critical("SECURITY ALERT: {$event}", [
            'alert_type' => 'security',
            'requires_immediate_attention' => true,
            'context' => $context
        ]);
    }

    /**
     * Determine if an error is critical
     */
    protected function isCriticalError(\Throwable $exception): bool
    {
        $criticalExceptions = [
            'PDOException',
            'ErrorException', 
            'FatalError',
            'Illuminate\Database\QueryException'
        ];

        return in_array(get_class($exception), $criticalExceptions) ||
               str_contains($exception->getMessage(), 'database') ||
               str_contains($exception->getMessage(), 'connection');
    }

    /**
     * Sanitize sensitive data from logs
     */
    protected function sanitizeData(array $data): array
    {
        $sensitiveKeys = ['password', 'token', 'key', 'secret', 'authorization'];
        
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                foreach ($sensitiveKeys as $sensitiveKey) {
                    if (str_contains(strtolower($key), $sensitiveKey)) {
                        $data[$key] = '[SANITIZED]';
                        break;
                    }
                }
            }
            
            if (is_array($value)) {
                $data[$key] = $this->sanitizeData($value);
            }
        }
        
        return $data;
    }

    /**
     * Create structured log entry with correlation ID
     */
    public function structured(string $channel, string $level, string $message, array $context = []): void
    {
        // Generar correlation ID para tracing distribuido
        $correlationId = request()->headers->get('X-Correlation-ID', $this->generateCorrelationId());
        
        $enrichedContext = array_merge([
            'correlation_id' => $correlationId,
            'service' => 'visit-management',
            'version' => config('app.version', '1.0.0'),
            'environment' => app()->environment(),
            'timestamp' => now()->toISOString(),
        ], $context);

        Log::channel($channel)->{$level}($message, $enrichedContext);
    }

    /**
     * Generate unique correlation ID for request tracing
     */
    protected function generateCorrelationId(): string
    {
        return 'vms_' . now()->format('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8);
    }
}