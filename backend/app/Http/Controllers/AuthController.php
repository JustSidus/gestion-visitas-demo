<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\LoggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Controller para autenticación de usuarios
 * 
 * Responsabilidades:
 * - Manejar login/logout con JWT
 * - Proporcionar información del usuario autenticado
 * - Logging de eventos de autenticación
 * - Manejo de errores de autenticación
 */
class AuthController extends Controller
{
    public function __construct(
        protected LoggerService $logger
    ) {}

    /**
     * Login de usuario con credenciales
     */
    public function login(Request $request)
    {
        // Validación de credenciales
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            $this->logger->security('Login attempt with invalid data', [
                'email' => $request->email,
                'errors' => $validator->errors()->toArray(),
            ], 'warning');

            return response()->json([
                'error' => 'Datos de entrada inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        // Intentar autenticación
        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                $this->logger->security('Failed login attempt', [
                    'email' => $request->email,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ], 'warning');

                return response()->json([
                    'error' => 'Credenciales inválidas'
                ], 401);
            }

            // Obtener usuario autenticado
            $user = JWTAuth::setToken($token)->toUser();
            
            // Cargar relación de rol
            $user->load('role');

            // Verificar si el usuario está activo
            if (!$user->canAccessApp()) {
                JWTAuth::invalidate($token);
                
                $this->logger->security('Login attempt by inactive user', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ], 'warning');

                return response()->json([
                    'error' => 'Usuario inactivo. Contacte al administrador.'
                ], 403);
            }

            // Actualizar última fecha de login
            $user->update([
                'last_login_at' => now()
            ]);

            // Log successful login
            $this->logger->security('User logged in successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role?->name,
            ]);

            $this->logger->business('User login', [
                'user_id' => $user->id,
                'role' => $user->role?->name,
            ]);

            // Métricas de login
            $this->logger->metric('user_logins_total', 1, [
                'role' => $user->role?->name,
            ]);

            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'user' => new UserResource($user),
            ]);

        } catch (\Exception $e) {
            $this->logger->error($e, [
                'action' => 'login',
                'email' => $request->email,
            ]);

            return response()->json([
                'error' => 'Error en el proceso de autenticación'
            ], 500);
        }
    }

    /**
     * Login demo para entorno anonimizado (sin Microsoft SSO)
     */
    public function demoLogin(Request $request)
    {
        try {
            if (!config('app.demo_mode', false)) {
                return response()->json([
                    'error' => 'Recurso no disponible'
                ], 404);
            }

            $role = Role::where('name', 'Admin')->first();

            if (!$role) {
                $role = Role::create([
                    'name' => 'Admin',
                    'description' => 'Rol administrador para entorno demo',
                ]);
            }

            $user = User::firstOrCreate(
                ['email' => 'demo@demo.example.org'],
                [
                    'name' => 'Usuario Demo',
                    'password' => Hash::make('demo123456'),
                    'is_active' => true,
                ]
            );

            if (!$user->is_active) {
                $user->update(['is_active' => true]);
            }

            if (!$user->roles()->where('roles.id', $role->id)->exists()) {
                $user->roles()->sync([$role->id]);
            }

            $token = JWTAuth::fromUser($user);
            $user->load('roles');

            $this->logger->business('Demo login', [
                'user_id' => $user->id,
                'role' => $role->name,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Login demo exitoso',
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl', 60) * 60,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $role->name,
                    'role_id' => $role->id,
                    'is_active' => (bool) $user->is_active,
                    'permissions' => ['demo_mode'],
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error($e, [
                'action' => 'demo_login',
            ]);

            return response()->json([
                'error' => 'No se pudo iniciar sesión en modo demo'
            ], 500);
        }
    }

    /**
     * Obtener información del usuario autenticado
     */
    public function getUser(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            // Cargar relación de rol
            $user->load('role');

            return response()->json([
                'user' => new UserResource($user)
            ]);

        } catch (\Exception $e) {
            $this->logger->error($e, [
                'action' => 'getUser',
            ]);

            return response()->json([
                'error' => 'Error al obtener información del usuario'
            ], 500);
        }
    }

    /**
     * Cerrar sesión del usuario
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if ($user) {
                // Log logout
                $this->logger->security('User logged out', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);

                $this->logger->business('User logout', [
                    'user_id' => $user->id,
                ]);
            }

            // Invalidar token
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'message' => 'Sesión cerrada exitosamente'
            ]);

        } catch (\Exception $e) {
            $this->logger->error($e, [
                'action' => 'logout',
            ]);

            return response()->json([
                'message' => 'Sesión cerrada (con advertencias)'
            ]);
        }
    }

    /**
     * Refrescar token JWT
     */
    public function refresh(Request $request)
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
            $user = JWTAuth::setToken($token)->toUser();

            $this->logger->security('Token refreshed', [
                'user_id' => $user->id,
            ]);

            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
            ]);

        } catch (\Exception $e) {
            $this->logger->error($e, [
                'action' => 'refresh',
            ]);

            return response()->json([
                'error' => 'No se pudo refrescar el token'
            ], 401);
        }
    }

    /**
     * Verificar validez del token
     */
    public function check(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'valid' => false
                ], 401);
            }

            return response()->json([
                'valid' => true,
                'user' => new UserResource($user)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'valid' => false
            ], 401);
        }
    }
}
