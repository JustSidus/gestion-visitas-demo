<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AddCoopHeader
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Para Microsoft MSAL (Azure AD) que usa popups para autenticación,
        // necesitamos permitir que JavaScript pueda verificar el estado de las ventanas popup
        // 'unsafe-none' permite esto sin restricciones de mismo origen
        $response->header('Cross-Origin-Opener-Policy', 'unsafe-none');
        
        return $response;
    }
}