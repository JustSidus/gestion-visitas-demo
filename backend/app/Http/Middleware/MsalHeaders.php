<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class MsalHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Headers necesarios para MSAL popups
        $response->header('Cross-Origin-Opener-Policy', 'unsafe-none');
        $response->header('Cross-Origin-Embedder-Policy', 'unsafe-none');
        $response->header('Cross-Origin-Resource-Policy', 'cross-origin');

        return $response;
    }
}
