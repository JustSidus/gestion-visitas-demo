<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MicrosoftGraphService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Controlador de autenticación con Microsoft 365
 * 
 * Maneja el login con SSO de Microsoft, validación de tokens
 * y autorización de usuarios en la aplicación
 */
class MicrosoftAuthController extends Controller
{
    private MicrosoftGraphService $graphService;

    public function __construct(MicrosoftGraphService $graphService)
    {
        $this->graphService = $graphService;
    }

    /**
     * Login con Microsoft 365
     * 
     * El frontend envía el access token obtenido de Microsoft y los datos del usuario.
     * Este método valida el token, verifica que el usuario esté autorizado en la app
     * y genera un JWT para las siguientes peticiones.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $traceId = 'mslogin-' . Str::uuid();

        // Validar datos recibidos
        $validator = Validator::make($request->all(), [
            'access_token' => 'required|string',
            'microsoft_user' => 'required|array',
            'microsoft_user.mail' => 'nullable|email',
            'microsoft_user.userPrincipalName' => 'nullable|email',
            'microsoft_user.displayName' => 'required|string',
            'microsoft_user.id' => 'required|string',
        ]);

        if ($validator->fails()) {
            Log::warning('Login Microsoft - Datos inválidos', [
                'errors' => $validator->errors(),
                'trace_id' => $traceId,
            ]);
            
            return response()->json([
                'error' => 'Datos inválidos',
                'details' => $validator->errors(),
                'trace_id' => $traceId,
            ], 400);
        }

        $accessToken = $request->access_token;
        $microsoftUserData = $request->microsoft_user;

        // 1. Validar el token con Microsoft Graph API
        $startValidation = microtime(true);
        Log::info('Microsoft login - Iniciando validación de token', [
            'email_hint' => $microsoftUserData['mail'] ?? $microsoftUserData['userPrincipalName'] ?? 'unknown',
            'microsoft_id' => $microsoftUserData['id'] ?? 'unknown',
            'trace_id' => $traceId,
        ]);

        $validatedUser = $this->graphService->validateToken($accessToken);

        $validationDuration = round((microtime(true) - $startValidation) * 1000, 2);
        Log::info('Microsoft login - Validación completada', [
            'duration_ms' => $validationDuration,
            'success' => $validatedUser !== null,
            'trace_id' => $traceId,
        ]);

        if (!$validatedUser) {
            Log::warning('Login Microsoft - Token inválido');
            
            return response()->json([
                'error' => 'Token de Microsoft inválido o expirado',
                'message' => 'Por favor, vuelve a iniciar sesión',
                'trace_id' => $traceId,
            ], 401);
        }

        // 2. Extraer el email del usuario (puede venir en 'mail' o 'userPrincipalName')
        $email = $validatedUser['mail'] 
                 ?? $validatedUser['userPrincipalName'] 
                 ?? $microsoftUserData['mail'] 
                 ?? $microsoftUserData['userPrincipalName'];

        if (!$email) {
            Log::error('Login Microsoft - No se pudo obtener el email', [
                'microsoft_data' => $validatedUser,
                'trace_id' => $traceId,
            ]);
            
            return response()->json([
                'error' => 'No se pudo obtener tu correo electrónico',
                'message' => 'Contacta al administrador del sistema',
                'trace_id' => $traceId,
            ], 400);
        }

        // 3. Buscar usuario en nuestra base de datos
        $user = User::where('email', $email)->first();

        // 4. CONTROL DE ACCESO - ¿El usuario está autorizado?
        if (!$user) {
            Log::warning('Login Microsoft - Usuario no autorizado', [
                'email' => $email,
                'trace_id' => $traceId,
            ]);
            
            return response()->json([
                'error' => 'No autorizado',
                'message' => 'Tu cuenta de Microsoft es válida, pero no tienes acceso a esta aplicación.',
                'help' => 'Contacta al administrador para solicitar acceso.',
                'email' => $email,
                'trace_id' => $traceId,
            ], 403);
        }

        // 5. Verificar si el usuario está activo
        if (!$user->canAccessApp()) {
            Log::warning('Login Microsoft - Usuario desactivado', [
                'email' => $email,
                'user_id' => $user->id,
                'trace_id' => $traceId,
            ]);
            
            return response()->json([
                'error' => 'Cuenta desactivada',
                'message' => 'Tu cuenta ha sido desactivada. Contacta al administrador.',
                'trace_id' => $traceId,
            ], 403);
        }

        // 6. Actualizar microsoft_id si es la primera vez que usa SSO
        if (!$user->microsoft_id) {
            $user->update([
                'microsoft_id' => $microsoftUserData['id']
            ]);
            
            Log::info('Microsoft ID asignado al usuario', [
                'user_id' => $user->id,
                'email' => $email
            ]);
        }

        // 7. Obtener el rol del usuario
        $userRole = $user->roles->first();

        if (!$userRole) {
            Log::error('Usuario sin rol asignado', [
                'user_id' => $user->id,
                'email' => $email,
                'trace_id' => $traceId,
            ]);
            
            return response()->json([
                'error' => 'Configuración incompleta',
                'message' => 'Tu usuario no tiene un rol asignado. Contacta al administrador.',
                'trace_id' => $traceId,
            ], 500);
        }

        // 8. Guardar el access_token de Microsoft en caché (encriptado por seguridad)
        // Lo usaremos para enviar correos y consultar Graph API
        $cacheKey = 'microsoft_token_' . $user->id;
        Cache::put($cacheKey, Crypt::encryptString($accessToken), now()->addMinutes(50)); // Expira antes que el token real (60 min)

        Log::info('Token de Microsoft guardado en caché', [
            'user_id' => $user->id,
            'cache_key' => $cacheKey,
            'expires_at' => now()->addMinutes(50),
            'trace_id' => $traceId,
        ]);

        // 9. Generar token JWT de nuestra aplicación
        $appToken = JWTAuth::fromUser($user);

        Log::info('Login exitoso con Microsoft', [
            'user_id' => $user->id,
            'email' => $email,
            'role' => $userRole->name,
            'trace_id' => $traceId,
        ]);

        // 10. Retornar datos del usuario y token
        return response()->json([
            'message' => 'Login exitoso',
            'access_token' => $appToken,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl', 60) * 60, // Segundos (por defecto 60 minutos)
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $userRole->name,
                'role_id' => $userRole->id,
                'is_active' => $user->is_active,
                'permissions' => $this->getUserPermissions($userRole->name)
            ],
            'trace_id' => $traceId,
        ], 200);
    }

    /**
     * Refresh con Microsoft Token
     * 
     * Cuando el JWT expira, el frontend puede obtener un nuevo access token de Microsoft
     * (si la sesión de Microsoft sigue activa) y enviarlo aquí para obtener un nuevo JWT
     * sin necesidad de que el usuario vuelva a hacer login ni pase por 2FA.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refreshWithMicrosoft(Request $request)
    {
        // Validar que se reciba el token de Microsoft
        $validator = Validator::make($request->all(), [
            'access_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            Log::warning('Token refresh - Datos inválidos', [
                'errors' => $validator->errors()
            ]);
            
            return response()->json([
                'error' => 'Token de Microsoft requerido'
            ], 400);
        }

        $accessToken = $request->access_token;

        try {
            // 1. Validar el token con Microsoft Graph API
            $validatedUser = $this->graphService->validateToken($accessToken);

            if (!$validatedUser) {
                Log::warning('Token refresh - Token inválido o expirado');
                
                return response()->json([
                    'error' => 'Token de Microsoft inválido o expirado',
                    'message' => 'Por favor, vuelve a iniciar sesión'
                ], 401);
            }

            // 2. Extraer el email del usuario
            $email = $validatedUser['mail'] ?? $validatedUser['userPrincipalName'];

            if (!$email) {
                Log::error('Token refresh - No se pudo obtener el email', [
                    'microsoft_data' => $validatedUser
                ]);
                
                return response()->json([
                    'error' => 'No se pudo obtener el correo electrónico'
                ], 400);
            }

            // 3. Buscar usuario en nuestra base de datos
            $user = User::where('email', $email)->first();

            if (!$user) {
                Log::warning('Token refresh - Usuario no encontrado', [
                    'email' => $email
                ]);
                
                return response()->json([
                    'error' => 'Usuario no autorizado'
                ], 403);
            }

            // 4. Verificar que el usuario siga activo
            if (!$user->canAccessApp()) {
                Log::warning('Token refresh - Usuario desactivado', [
                    'email' => $email,
                    'user_id' => $user->id
                ]);
                
                return response()->json([
                    'error' => 'Cuenta desactivada'
                ], 403);
            }

            // 5. Actualizar el token de Microsoft en caché (encriptado)
            $cacheKey = 'microsoft_token_' . $user->id;
            Cache::put($cacheKey, Crypt::encryptString($accessToken), now()->addMinutes(50));

            Log::info('Token refresh exitoso', [
                'user_id' => $user->id,
                'email' => $email
            ]);

            // 6. Generar nuevo JWT
            $appToken = JWTAuth::fromUser($user);

            return response()->json([
                'message' => 'Token renovado exitosamente',
                'access_token' => $appToken,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl', 60) * 60
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al renovar token con Microsoft', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Error al renovar token',
                'message' => 'Por favor, vuelve a iniciar sesión'
            ], 500);
        }
    }

    /**
     * Logout - Cierra sesión y limpia tokens
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if ($user) {
                // Eliminar token de Microsoft del caché
                Cache::forget('microsoft_token_' . $user->id);

                Log::info('Usuario cerró sesión', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
            }

            // Invalidar token JWT
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'message' => 'Sesión cerrada exitosamente'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Sesión cerrada exitosamente'
            ], 200);
        }
    }

    /**
     * Refresh - Renueva el token JWT
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());
            
            return response()->json([
                'access_token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl', 60) * 60
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Error al renovar token', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'No se pudo renovar el token',
                'message' => 'Por favor, vuelve a iniciar sesión'
            ], 401);
        }
    }

    /**
     * Me - Obtiene información del usuario autenticado
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'No autenticado'
                ], 401);
            }

            $userRole = $user->roles->first();

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $userRole ? $userRole->name : null,
                    'role_id' => $userRole ? $userRole->id : null,
                    'is_active' => $user->is_active,
                    'permissions' => $this->getUserPermissions($userRole ? $userRole->name : null)
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'No autenticado'
            ], 401);
        }
    }

    /**
     * Obtener permisos del usuario según su rol
     * 
     * @param string|null $roleName
     * @return array
     */
    private function getUserPermissions(?string $roleName): array
    {
        if (!$roleName) {
            return [];
        }

        $permissions = [
            'Admin' => [
                'manage_users',           // Gestionar usuarios
                'manage_roles',           // Asignar roles
                'create_visits',          // Crear visitas
                'view_all_visits',        // Ver todas las visitas
                'edit_visits',            // Editar visitas
                'delete_visits',          // Eliminar visitas
                'close_visits',           // Cerrar visitas
                'export_data',            // Exportar datos
                'view_reports',           // Ver reportes
                'manage_settings'         // Configuración del sistema
            ],
            'Asist_adm' => [
                'create_visits',
                'view_all_visits',
                'edit_visits',
                'close_visits',
                'export_data',
                'view_reports'
            ],
            'Guardia' => [
                'view_active_visits',     // Ver solo visitas activas
                'close_visits_restricted', // Cerrar visitas (4pm-11:59pm)
                'validate_qr',            // Validar QR
                'register_vehicle_plate'  // Registrar placa
            ],
            'aux_ugc' => [
                'view_mission_visits',    // Ver solo visitas misionales activas
                'close_mission_visits'    // Cerrar visitas misionales
            ]
        ];

        return $permissions[$roleName] ?? [];
    }
}
