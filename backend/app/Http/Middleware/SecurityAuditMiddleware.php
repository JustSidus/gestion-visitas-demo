<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para auditoría y logging de seguridad
 * 
 * Responsabilidades:
 * - Registrar todas las acciones importantes del sistema
 * - Capturar intentos de acceso no autorizados
 * - Crear trail de auditoría para compliance
 * - Detectar patrones sospechosos de actividad
 */
class SecurityAuditMiddleware
{
    /**
     * Actions that should always be logged
     */
    protected array $auditableActions = [
        'POST' => ['visits', 'visitors', 'users'],
        'PUT' => ['visits', 'visitors', 'users'],
        'PATCH' => ['visits', 'visitors', 'users'], 
        'DELETE' => ['visits', 'visitors', 'users'],
    ];

    /**
     * Sensitive endpoints that require detailed logging
     */
    protected array $sensitiveEndpoints = [
        'login',
        'register', 
        'password',
        'export',
        'bulk',
        'admin',
        'statistics',
        'reports'
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        // Log request inicio
        $this->logRequestStart($request);
        
        // Procesar request
        $response = $next($request);
        
        // Log request completado
        $this->logRequestEnd($request, $response, $startTime);
        
        return $response;
    }

    /**
     * Log request start for audit trail
     */
    protected function logRequestStart(Request $request): void
    {
        if ($this->shouldAudit($request)) {
            // Obtener session_id solo si hay sesión disponible (no en API routes)
            $sessionId = null;
            try {
                if ($request->hasSession()) {
                    $sessionId = $request->session()->getId();
                }
            } catch (\Exception $e) {
                // Ignorar si no hay sesión (normal en rutas API)
            }
            
            Log::info('Security audit: Request started', [
                'audit_id' => $this->generateAuditId(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'user_id' => $request->user()?->id,
                'user_role' => $request->user()?->roles->first()?->name,
                'session_id' => $sessionId,
                'request_headers' => $this->getSafeHeaders($request),
                'request_size' => strlen($request->getContent()),
                'timestamp' => now()->toISOString(),
                'type' => 'request_start'
            ]);
        }
    }

    /**
     * Log request completion for audit trail
     */
    protected function logRequestEnd(Request $request, Response $response, float $startTime): void
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2); // en milliseconds
        
        if ($this->shouldAudit($request)) {
            $logLevel = $this->getLogLevel($response->getStatusCode());
            
            Log::log($logLevel, 'Security audit: Request completed', [
                'audit_id' => $this->generateAuditId(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'status_code' => $response->getStatusCode(),
                'response_size' => $response->headers->get('Content-Length', 0),
                'duration_ms' => $duration,
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
                'timestamp' => now()->toISOString(),
                'type' => 'request_end',
                'success' => $response->getStatusCode() < 400
            ]);

            // Log adicional para cambios de datos
            if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                $this->logDataChanges($request, $response);
            }

            // Log para respuestas de error
            if ($response->getStatusCode() >= 400) {
                $this->logErrorDetails($request, $response);
            }

            // Detectar patrones sospechosos
            $this->detectSuspiciousActivity($request, $response, $duration);
        }
    }

    /**
     * Determine if request should be audited
     */
    protected function shouldAudit(Request $request): bool
    {
        $method = $request->method();
        $path = $request->path();

        // Auditar siempre acciones de modificación de datos
        if (isset($this->auditableActions[$method])) {
            foreach ($this->auditableActions[$method] as $resource) {
                if (str_contains($path, $resource)) {
                    return true;
                }
            }
        }

        // Auditar endpoints sensibles
        foreach ($this->sensitiveEndpoints as $endpoint) {
            if (str_contains($path, $endpoint)) {
                return true;
            }
        }

        // Auditar requests fallidos
        return false;
    }

    /**
     * Log data modification attempts
     */
    protected function logDataChanges(Request $request, Response $response): void
    {
        $changedData = $this->extractChangedData($request);
        
        if (!empty($changedData)) {
            Log::info('Security audit: Data modification', [
                'audit_id' => $this->generateAuditId(),
                'action' => $request->method(),
                'resource' => $this->extractResourceType($request),
                'resource_id' => $this->extractResourceId($request),
                'changed_fields' => array_keys($changedData),
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
                'timestamp' => now()->toISOString(),
                'type' => 'data_change',
                'success' => $response->getStatusCode() < 400
            ]);
        }
    }

    /**
     * Log error details for security analysis
     */
    protected function logErrorDetails(Request $request, Response $response): void
    {
        $logLevel = $response->getStatusCode() >= 500 ? 'error' : 'warning';
        
        Log::log($logLevel, 'Security audit: Request error', [
            'audit_id' => $this->generateAuditId(),
            'status_code' => $response->getStatusCode(),
            'error_type' => $this->getErrorType($response->getStatusCode()),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
            'type' => 'error',
            'requires_investigation' => $response->getStatusCode() === 401 || $response->getStatusCode() === 403
        ]);
    }

    /**
     * Detect suspicious activity patterns
     */
    protected function detectSuspiciousActivity(Request $request, Response $response, float $duration): void
    {
        $suspiciousIndicators = [];

        // Request muy lento (posible DoS)
        if ($duration > 10000) { // > 10 segundos
            $suspiciousIndicators[] = 'slow_request';
        }

        // Múltiples errores 401/403
        if (in_array($response->getStatusCode(), [401, 403])) {
            $suspiciousIndicators[] = 'unauthorized_access';
        }

        // Request muy grande
        if (strlen($request->getContent()) > 10485760) { // > 10MB
            $suspiciousIndicators[] = 'large_payload';
        }

        // User agent sospechoso
        $userAgent = $request->userAgent();
        if (empty($userAgent) || str_contains(strtolower($userAgent), 'bot') || str_contains(strtolower($userAgent), 'crawler')) {
            $suspiciousIndicators[] = 'suspicious_user_agent';
        }

        // SQL injection attempts en parámetros
        if ($this->detectSqlInjection($request)) {
            $suspiciousIndicators[] = 'sql_injection_attempt';
        }

        if (!empty($suspiciousIndicators)) {
            Log::warning('Security audit: Suspicious activity detected', [
                'audit_id' => $this->generateAuditId(),
                'indicators' => $suspiciousIndicators,
                'severity' => $this->calculateSeverity($suspiciousIndicators),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
                'user_agent' => $userAgent,
                'timestamp' => now()->toISOString(),
                'type' => 'suspicious_activity',
                'requires_immediate_attention' => in_array('sql_injection_attempt', $suspiciousIndicators)
            ]);
        }
    }

    /**
     * Generate unique audit ID for tracking
     */
    protected function generateAuditId(): string
    {
        return 'audit_' . now()->format('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8);
    }

    /**
     * Get safe headers for logging (exclude sensitive data)
     */
    protected function getSafeHeaders(Request $request): array
    {
        $excludeHeaders = ['authorization', 'cookie', 'x-api-key', 'x-auth-token'];
        $headers = $request->headers->all();
        
        $safeHeaders = [];
        foreach ($headers as $key => $value) {
            if (!in_array(strtolower($key), $excludeHeaders)) {
                $safeHeaders[$key] = is_array($value) ? implode(', ', $value) : $value;
            }
        }
        
        return $safeHeaders;
    }

    /**
     * Extract changed data from request
     */
    protected function extractChangedData(Request $request): array
    {
        $data = $request->all();
        
        // Excluir campos sensibles del log
        $excludeFields = ['password', 'password_confirmation', '_token', '_method'];
        
        return array_filter($data, function ($key) use ($excludeFields) {
            return !in_array($key, $excludeFields);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Extract resource type from request
     */
    protected function extractResourceType(Request $request): string
    {
        $path = $request->path();
        
        if (str_contains($path, 'visits')) return 'visit';
        if (str_contains($path, 'visitors')) return 'visitor';
        if (str_contains($path, 'users')) return 'user';
        
        return 'unknown';
    }

    /**
     * Extract resource ID from request
     */
    protected function extractResourceId(Request $request): ?string
    {
        return $request->route('id') ?? $request->route('visit') ?? $request->route('visitor') ?? null;
    }

    /**
     * Get appropriate log level based on status code
     */
    protected function getLogLevel(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 500 => 'error',
            $statusCode >= 400 => 'warning', 
            default => 'info'
        };
    }

    /**
     * Get error type description
     */
    protected function getErrorType(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'bad_request',
            401 => 'unauthorized',
            403 => 'forbidden',
            404 => 'not_found',
            422 => 'validation_error',
            429 => 'rate_limit_exceeded',
            500 => 'internal_server_error',
            default => 'unknown_error'
        };
    }

    /**
     * Detect SQL injection attempts
     */
    private function detectSqlInjection(Request $request): bool
    {
        $sqlPatterns = [
            '/(\s|^)(union|select|insert|update|delete|drop|create|alter|exec|execute)(\s|$)/i',
            '/(\'|\"|;|--|\*|\/\*|\*\/)/i',
            '/(\s|^)(or|and)(\s|$).*(\s|^)(=|like)(\s|$)/i'
        ];

        // Convertir valores a strings, ignorando arrays complejos
        $inputValues = array_map(function($value) {
            if (is_array($value)) {
                return json_encode($value);
            }
            return (string) $value;
        }, $request->all());
        
        $allInput = implode(' ', $inputValues);
        
        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $allInput)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate severity level
     */
    protected function calculateSeverity(array $indicators): string
    {
        $highSeverityIndicators = ['sql_injection_attempt', 'unauthorized_access'];
        
        foreach ($indicators as $indicator) {
            if (in_array($indicator, $highSeverityIndicators)) {
                return 'high';
            }
        }

        return count($indicators) > 2 ? 'medium' : 'low';
    }
}