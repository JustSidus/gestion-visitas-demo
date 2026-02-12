<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    /**
     * Lista de orígenes permitidos
     */
    private array $allowedOrigins = [
        'http://localhost:5173',
        'http://localhost:3000',
        'http://localhost:8080',
        'http://localhost:4200',
        'https://demo.example.org',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Obtener el origen de la solicitud
        $origin = $request->header('Origin');
        
        // Agregar FRONTEND_URL si está configurado
        $frontendUrl = env('FRONTEND_URL');
        if ($frontendUrl && !in_array($frontendUrl, $this->allowedOrigins)) {
            $this->allowedOrigins[] = $frontendUrl;
        }

        // Verificar si el origen está permitido
        $allowedOrigin = in_array($origin, $this->allowedOrigins) ? $origin : '';

        // Manejar solicitudes OPTIONS (preflight)
        if ($request->isMethod('OPTIONS')) {
            return response('', 204)
                ->header('Access-Control-Allow-Origin', $allowedOrigin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-XSRF-TOKEN')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400');
        }

        $response = $next($request);

        // Agregar headers CORS a la respuesta
        if ($allowedOrigin) {
            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-XSRF-TOKEN');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
