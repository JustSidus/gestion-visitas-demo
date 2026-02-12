<?php

namespace App\Http\Middleware;

use App\Services\LoggerService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para logging automático de requests
 * 
 * Responsabilidades:
 * - Registrar automáticamente todas las requests HTTP
 * - Medir tiempos de respuesta
 * - Capturar errores y excepciones
 * - Enriquecer logs con contexto útil
 */
class RequestLoggingMiddleware
{
    public function __construct(
        protected LoggerService $logger
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        // Generar correlation ID si no existe
        if (!$request->headers->has('X-Correlation-ID')) {
            $correlationId = $this->generateCorrelationId();
            $request->headers->set('X-Correlation-ID', $correlationId);
        }

        // Log inicio de request (solo para requests importantes)
        if ($this->shouldLogRequest($request)) {
            $this->logRequestStart($request);
        }

        $response = null;
        $exception = null;

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $exception = $e;
            throw $e;
        } finally {
            $duration = microtime(true) - $startTime;
            
            // Log final del request
            if ($this->shouldLogRequest($request)) {
                $this->logRequestEnd($request, $response, $duration, $exception);
            }
            
            // Log métricas de performance
            $this->logPerformanceMetrics($request, $duration);
        }

        return $response;
    }

    /**
     * Log request start
     */
    protected function logRequestStart(Request $request): void
    {
        $this->logger->structured('api', 'info', 'Request started', [
            'request_id' => $request->headers->get('X-Correlation-ID'),
            'method' => $request->method(),
            'endpoint' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'content_length' => $request->headers->get('Content-Length', 0),
            'type' => 'request_start'
        ]);
    }

    /**
     * Log request completion
     */
    protected function logRequestEnd(Request $request, ?Response $response, float $duration, ?\Throwable $exception): void
    {
        $context = [
            'request_id' => $request->headers->get('X-Correlation-ID'),
            'method' => $request->method(),
            'endpoint' => $request->path(),
            'duration_ms' => round($duration * 1000, 2),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'type' => 'request_end'
        ];

        if ($response) {
            $context['status_code'] = $response->getStatusCode();
            $context['response_size'] = $response->headers->get('Content-Length', 0);
        }

        if ($exception) {
            $context['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ];
            
            $this->logger->structured('api', 'error', 'Request failed with exception', $context);
            $this->logger->error($exception, ['request_id' => $request->headers->get('X-Correlation-ID')]);
        } else {
            $level = $this->getLogLevel($response?->getStatusCode() ?? 500);
            $this->logger->structured('api', $level, 'Request completed', $context);
        }
    }

    /**
     * Log performance metrics
     */
    protected function logPerformanceMetrics(Request $request, float $duration): void
    {
        $operation = $request->method() . ' ' . $request->path();
        
        $metrics = [
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'user_role' => $request->user()?->role?->name ?? 'guest',
        ];

        $this->logger->performance($operation, $duration, $metrics);

        // Métricas específicas
        $this->logger->metric('request_duration', $duration, [
            'endpoint' => $request->path(),
            'method' => $request->method()
        ]);

        $this->logger->metric('memory_usage', memory_get_usage(true), [
            'endpoint' => $request->path()
        ]);
    }

    /**
     * Determine if request should be logged
     */
    protected function shouldLogRequest(Request $request): bool
    {
        $path = $request->path();
        
        // Excluir rutas de health check, assets, etc.
        $excludePaths = [
            'up',
            'health',
            'ping',
            '_debugbar',
            'telescope',
        ];

        foreach ($excludePaths as $excludePath) {
            if (str_starts_with($path, $excludePath)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get appropriate log level based on status code
     */
    protected function getLogLevel(?int $statusCode): string
    {
        if ($statusCode === null) return 'error';
        
        return match (true) {
            $statusCode >= 500 => 'error',
            $statusCode >= 400 => 'warning',
            $statusCode >= 300 => 'info',
            default => 'info'
        };
    }

    /**
     * Generate correlation ID
     */
    protected function generateCorrelationId(): string
    {
        return 'req_' . now()->format('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8);
    }
}