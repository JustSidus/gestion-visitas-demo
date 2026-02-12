<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para rate limiting personalizado
 * 
 * Responsabilidades:
 * - Limitar requests por IP y usuario
 * - Aplicar diferentes límites según rol
 * - Registrar intentos de abuso
 * - Proteger endpoints críticos
 */
class RateLimitingMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $type = 'general'): Response
    {
        $identifier = $this->getIdentifier($request);
        $limits = $this->getLimits($request, $type);
        
        foreach ($limits as $period => $maxAttempts) {
            $key = "rate_limit:{$type}:{$identifier}:{$period}";
            $attempts = Cache::get($key, 0);
            
            if ($attempts >= $maxAttempts) {
                $this->logRateLimitViolation($request, $identifier, $type, $attempts);
                
                return response()->json([
                    'error' => 'Rate limit exceeded',
                    'message' => "Too many requests. Limit: {$maxAttempts}/{$period}",
                    'retry_after' => $this->getRetryAfter($period)
                ], 429);
            }
        }
        
        // Incrementar contadores
        $this->incrementCounters($identifier, $type, $limits);
        
        return $next($request);
    }

    /**
     * Get unique identifier for rate limiting
     */
    protected function getIdentifier(Request $request): string
    {
        // Priorizar usuario autenticado, luego IP
        if ($request->user()) {
            return 'user:' . $request->user()->id;
        }
        
        return 'ip:' . $request->ip();
    }

    /**
     * Get rate limits based on request context and type
     */
    protected function getLimits(Request $request, string $type): array
    {
        $user = $request->user();
        
        // Límites base por tipo de endpoint
        $baseLimits = match ($type) {
            'auth' => [
                'minute' => 10,   // 10 intentos por minuto
                'hour' => 50,     // 50 intentos por hora
                'day' => 200      // 200 intentos por día
            ],
            'api' => [
                'minute' => 100,  // 100 requests por minuto
                'hour' => 3000,   // 3000 requests por hora
                'day' => 50000    // 50000 requests por día
            ],
            'export' => [
                'minute' => 5,    // 5 exportaciones por minuto
                'hour' => 20,     // 20 exportaciones por hora
                'day' => 100      // 100 exportaciones por día
            ],
            'search' => [
                'minute' => 50,   // 50 búsquedas por minuto
                'hour' => 1000,   // 1000 búsquedas por hora
                'day' => 10000    // 10000 búsquedas por día
            ],
            'upload' => [
                'minute' => 10,   // 10 uploads por minuto
                'hour' => 100,    // 100 uploads por hora
                'day' => 500      // 500 uploads por día
            ],
            default => [
                'minute' => 60,
                'hour' => 2000,
                'day' => 20000
            ]
        };

        // Ajustar límites según rol del usuario
        if ($user) {
            $multiplier = match ($user->role->name) {
                'Admin' => 3.0,      // Admins tienen límites 3x más altos
                'Asist_adm' => 2.0,   // Asist_adm tienen límites 2x más altos
                'Guardia' => 1.5,     // Guardias tienen límites 1.5x más altos
                default => 1.0        // Usuarios normales mantienen límites base
            };

            foreach ($baseLimits as $period => $limit) {
                $baseLimits[$period] = (int) ($limit * $multiplier);
            }
        }

        return $baseLimits;
    }

    /**
     * Increment rate limiting counters
     */
    protected function incrementCounters(string $identifier, string $type, array $limits): void
    {
        foreach (array_keys($limits) as $period) {
            $key = "rate_limit:{$type}:{$identifier}:{$period}";
            $ttl = $this->getTtl($period);
            
            Cache::put($key, Cache::get($key, 0) + 1, $ttl);
        }
    }

    /**
     * Get TTL in seconds for each period
     */
    protected function getTtl(string $period): int
    {
        return match ($period) {
            'minute' => 60,
            'hour' => 3600,
            'day' => 86400,
            default => 3600
        };
    }

    /**
     * Get retry after time for period
     */
    protected function getRetryAfter(string $period): int
    {
        return match ($period) {
            'minute' => 60,
            'hour' => 3600,
            'day' => 86400,
            default => 3600
        };
    }

    /**
     * Log rate limit violation for security monitoring
     */
    protected function logRateLimitViolation(Request $request, string $identifier, string $type, int $attempts): void
    {
        Log::warning('Rate limit exceeded', [
            'identifier' => $identifier,
            'type' => $type,
            'attempts' => $attempts,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'endpoint' => $request->fullUrl(),
            'method' => $request->method(),
            'user_id' => $request->user()?->id,
            'timestamp' => now()->toISOString()
        ]);

        // Alerta para intentos excesivos
        if ($attempts > 1000) {
            Log::alert('Excessive rate limit violations detected', [
                'identifier' => $identifier,
                'attempts' => $attempts,
                'type' => $type,
                'requires_investigation' => true
            ]);
        }
    }
}