<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

/**
 * Middleware para verificar roles de usuario
 * 
 * Uso en rutas:
 * Route::get('/ruta', [Controller::class, 'metodo'])->middleware('role:Admin');
 * Route::get('/ruta', [Controller::class, 'metodo'])->middleware('role:Admin,Asist_adm');
 */
class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles  Los roles permitidos (puede ser uno o varios)
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Verificar que el usuario está autenticado
        $user = auth()->user();

        if (!$user) {
            Log::warning('CheckRole - Usuario no autenticado intentó acceder a ruta protegida');
            
            return response()->json([
                'error' => 'No autenticado',
                'message' => 'Debes iniciar sesión para acceder a este recurso'
            ], 401);
        }

        // Verificar que el usuario está activo
        if (!$user->canAccessApp()) {
            Log::warning('CheckRole - Usuario desactivado intentó acceder', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            return response()->json([
                'error' => 'Cuenta desactivada',
                'message' => 'Tu cuenta ha sido desactivada. Contacta al administrador.'
            ], 403);
        }

        // Si no se especificaron roles, solo verificar autenticación
        if (empty($roles)) {
            return $next($request);
        }

        // Verificar si el usuario tiene alguno de los roles permitidos
        $userRole = $user->roles->first();

        if (!$userRole) {
            Log::error('CheckRole - Usuario sin rol asignado', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            return response()->json([
                'error' => 'Sin permisos',
                'message' => 'Tu usuario no tiene un rol asignado. Contacta al administrador.'
            ], 403);
        }

        // Verificar si el rol del usuario está en la lista de roles permitidos
        if (!in_array($userRole->name, $roles)) {
            Log::warning('CheckRole - Acceso denegado por rol insuficiente', [
                'user_id' => $user->id,
                'user_role' => $userRole->name,
                'required_roles' => $roles,
                'route' => $request->path()
            ]);
            
            // SEGURIDAD: No exponer roles en producción
            return response()->json([
                'error' => 'No autorizado',
                'message' => 'No tienes permisos para acceder a este recurso'
            ], 403);
        }

        // El usuario tiene el rol adecuado, continuar
        return $next($request);
    }
}
