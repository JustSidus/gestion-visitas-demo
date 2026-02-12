<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Services\MicrosoftGraphService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Controlador para gestión de usuarios (solo Admin)
 * 
 * Permite al administrador:
 * - Listar usuarios de la aplicación
 * - Buscar usuarios en Microsoft 365
 * - Agregar usuarios a la aplicación
 * - Asignar/cambiar roles
 * - Activar/desactivar usuarios
 * - Eliminar usuarios
 */
class UserManagementController extends Controller
{
    private MicrosoftGraphService $graphService;

    public function __construct(MicrosoftGraphService $graphService)
    {
        $this->graphService = $graphService;
    }

    /**
     * Listar todos los usuarios de la aplicación
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $users = User::with(['roles', 'createdBy'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($user) {
                    $role = $user->roles->first();
                    
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $role ? $role->name : null,
                        'role_id' => $role ? $role->id : null,
                        'is_active' => $user->is_active,
                        'microsoft_id' => $user->microsoft_id,
                        'created_by' => $user->createdBy ? $user->createdBy->name : 'Sistema',
                        'created_at' => $user->created_at->format('Y-m-d H:i:s')
                    ];
                });

            return response()->json([
                'users' => $users,
                'total' => $users->count()
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Error al listar usuarios', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Error al obtener usuarios'
            ], 500);
        }
    }

    /**
     * Buscar usuarios en Microsoft 365
     * Para que el admin pueda agregarlos a la app
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Busca usuarios en Microsoft 365 para el campo "Persona a visitar"
     * Maneja renovación automática de token si ha expirado
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchMicrosoftUsers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:3'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Debes ingresar al menos 3 caracteres para buscar'
            ], 400);
        }

        try {
            // Obtener token de Microsoft del usuario autenticado
            $user = JWTAuth::parseToken()->authenticate();
            $userId = $user->id;
            $encryptedToken = Cache::get('microsoft_token_' . $userId);
            $microsoftToken = $encryptedToken ? Crypt::decryptString($encryptedToken) : null;

            if (!$microsoftToken) {
                Log::warning('Token de Microsoft no encontrado en cache', [
                    'user_id' => $userId
                ]);
                
                return response()->json([
                    'error' => 'Sesión de Microsoft expirada',
                    'message' => 'Tu sesión de Microsoft ha expirado. Por favor, cierra sesión y vuelve a iniciar sesión.',
                    'code' => 'TOKEN_EXPIRED'
                ], 401);
            }

            // Buscar en Azure AD
            $searchQuery = $request->input('query');
            
            Log::info('Buscando usuarios en Microsoft 365', [
                'query' => $searchQuery,
                'user_id' => $userId
            ]);
            
            try {
                $microsoftUsers = $this->graphService->searchUsers(
                    $microsoftToken,
                    $searchQuery
                );
            } catch (\Exception $e) {
                // Si el error es por token inválido/expirado
                if (str_contains($e->getMessage(), 'Unauthorized') || 
                    str_contains($e->getMessage(), 'InvalidAuthenticationToken') ||
                    str_contains($e->getMessage(), '401')) {
                    
                    Log::warning('Token de Microsoft inválido, requiere reautenticación', [
                        'user_id' => $userId
                    ]);
                    
                    // Limpiar token del cache
                    Cache::forget('microsoft_token_' . $userId);
                    
                    return response()->json([
                        'error' => 'Sesión de Microsoft expirada',
                        'message' => 'Tu sesión de Microsoft ha expirado. Por favor, cierra sesión y vuelve a iniciar sesión.',
                        'code' => 'TOKEN_EXPIRED'
                    ], 401);
                }
                
                // Otro tipo de error
                throw $e;
            }

            // Formatear resultados
            $formattedUsers = array_map(function ($user) {
                return [
                    'id' => $user['id'],
                    'displayName' => $user['displayName'],
                    'mail' => $user['mail'] ?? $user['userPrincipalName'],
                    'userPrincipalName' => $user['userPrincipalName'] ?? '',
                    'jobTitle' => $user['jobTitle'] ?? null,
                    'department' => $user['department'] ?? null,
                    'officeLocation' => $user['officeLocation'] ?? null,
                    'businessPhones' => $user['businessPhones'] ?? []
                ];
            }, $microsoftUsers);

            Log::info('Usuarios formateados para respuesta', [
                'total' => count($formattedUsers)
            ]);

            return response()->json([
                'users' => array_values($formattedUsers),
                'total' => count($formattedUsers)
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Error buscando usuarios en Microsoft', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'query' => $request->input('query')
            ]);
            
            return response()->json([
                'error' => 'Error al buscar usuarios en Microsoft 365',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar usuario de Microsoft a la aplicación
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email',
            'name' => 'required|string|max:255',
            'microsoft_id' => 'required|string|unique:users,microsoft_id',
            'role_id' => 'required|exists:roles,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Datos inválidos',
                'details' => $validator->errors()
            ], 400);
        }

        try {
            // Verificar que el usuario existe en Microsoft 365
            $adminId = JWTAuth::parseToken()->authenticate()->id;
            $encryptedToken = Cache::get('microsoft_token_' . $adminId);
            $microsoftToken = $encryptedToken ? Crypt::decryptString($encryptedToken) : null;

            if ($microsoftToken) {
                $microsoftUser = $this->graphService->getUserByEmail(
                    $microsoftToken,
                    $request->email
                );

                if (!$microsoftUser) {
                    return response()->json([
                        'error' => 'Usuario no encontrado en Microsoft 365'
                    ], 404);
                }
            }

            // Crear usuario en la app
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'microsoft_id' => $request->microsoft_id,
                'is_active' => true,
                'created_by' => $adminId,
                'password' => Hash::make(Str::random(32)) // Password seguro aleatorio (no se usará con SSO)
            ]);

            // Asignar rol (relación many-to-many)
            $user->roles()->attach($request->role_id);

            $role = Role::find($request->role_id);

            Log::info('Usuario agregado a la aplicación', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $role->name,
                'created_by' => $adminId
            ]);

            return response()->json([
                'message' => 'Usuario agregado exitosamente',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $role->name,
                    'role_id' => $role->id,
                    'is_active' => $user->is_active
                ]
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Error al agregar usuario', [
                'error' => $e->getMessage(),
                'email' => $request->email
            ]);
            
            return response()->json([
                'error' => 'Error al agregar usuario',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar rol o estado de usuario
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'error' => 'Usuario no encontrado'
            ], 404);
        }

        // Prevenir que un admin se modifique a sí mismo
        if ($user->id === JWTAuth::parseToken()->authenticate()->id && $request->has('role_id')) {
            return response()->json([
                'error' => 'No puedes cambiar tu propio rol'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'role_id' => 'sometimes|exists:roles,id',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Datos inválidos',
                'details' => $validator->errors()
            ], 400);
        }

        try {
            // Actualizar estado
            if ($request->has('is_active')) {
                $user->update(['is_active' => $request->is_active]);
            }

            // Actualizar rol
            if ($request->has('role_id')) {
                // Eliminar rol anterior y asignar nuevo
                $user->roles()->detach();
                $user->roles()->attach($request->role_id);
            }

            $role = $user->roles->first();

            Log::info('Usuario actualizado', [
                'user_id' => $user->id,
                'updated_by' => JWTAuth::parseToken()->authenticate()->id,
                'changes' => $request->only(['role_id', 'is_active'])
            ]);

            return response()->json([
                'message' => 'Usuario actualizado exitosamente',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $role ? $role->name : null,
                    'role_id' => $role ? $role->id : null,
                    'is_active' => $user->is_active
                ]
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Error al actualizar usuario', [
                'error' => $e->getMessage(),
                'user_id' => $id
            ]);
            
            return response()->json([
                'error' => 'Error al actualizar usuario'
            ], 500);
        }
    }

    /**
     * Activar/Desactivar usuario
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleActive($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'error' => 'Usuario no encontrado'
            ], 404);
        }

        // Prevenir que un admin se desactive a sí mismo
        if ($user->id === JWTAuth::parseToken()->authenticate()->id) {
            return response()->json([
                'error' => 'No puedes desactivar tu propia cuenta'
            ], 400);
        }

        try {
            $newStatus = !$user->is_active;
            $user->update(['is_active' => $newStatus]);

            Log::info('Usuario ' . ($newStatus ? 'activado' : 'desactivado'), [
                'user_id' => $user->id,
                'changed_by' => JWTAuth::parseToken()->authenticate()->id
            ]);

            return response()->json([
                'message' => $newStatus ? 'Usuario activado exitosamente' : 'Usuario desactivado exitosamente',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_active' => $user->is_active
                ]
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Error al cambiar estado de usuario', [
                'error' => $e->getMessage(),
                'user_id' => $id
            ]);
            
            return response()->json([
                'error' => 'Error al cambiar estado del usuario'
            ], 500);
        }
    }

    /**
     * Eliminar usuario permanentemente de la base de datos
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'error' => 'Usuario no encontrado'
            ], 404);
        }

        // Prevenir que un admin se elimine a sí mismo
        if ($user->id === JWTAuth::parseToken()->authenticate()->id) {
            return response()->json([
                'error' => 'No puedes eliminar tu propia cuenta',
                'message' => 'Por tu seguridad, no se permite eliminar la cuenta con la que estás actualmente logueado. Inicia sesión con otra cuenta de administrador si necesitas eliminar este usuario.',
                'code' => 'CANNOT_DELETE_OWN_ACCOUNT'
            ], 400);
        }

        try {
            // Guardar información antes de eliminar para el log
            $userName = $user->name;
            $userEmail = $user->email;
            
            // Eliminar relaciones con roles
            $user->roles()->detach();
            
            // Eliminar usuario permanentemente
            $user->delete();

            Log::warning('Usuario eliminado permanentemente de la base de datos', [
                'user_id' => $id,
                'name' => $userName,
                'email' => $userEmail,
                'deleted_by' => JWTAuth::parseToken()->authenticate()->id
            ]);

            return response()->json([
                'message' => 'Usuario eliminado exitosamente de la base de datos'
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Error al eliminar usuario', [
                'error' => $e->getMessage(),
                'user_id' => $id
            ]);
            
            return response()->json([
                'error' => 'Error al eliminar usuario'
            ], 500);
        }
    }

    /**
     * Obtener todos los roles disponibles
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRoles()
    {
        try {
            $roles = Role::select('id', 'name')->get();
            
            return response()->json([
                'roles' => $roles
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener roles', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Error al obtener roles'
            ], 500);
        }
    }
}

