<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;

/**
 * Resource para transformar datos de User
 * 
 * Responsabilidades:
 * - Formatear salida consistente de datos de usuario
 * - Incluir roles y permisos
 * - Ocultar información sensible
 * - Proporcionar estadísticas de actividad
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            
            // Información básica (sin datos sensibles)
            'name' => $this->name,
            'email' => $this->email,
            
            // Roles y permisos
            'roles' => $this->whenLoaded('roles', function() {
                return $this->roles->map(function($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'display_name' => $role->display_name ?? $role->name
                    ];
                });
            }),
            'role_names' => $this->whenLoaded('roles', function() {
                return $this->roles->pluck('name')->toArray();
            }),
            'primary_role' => $this->whenLoaded('roles', function() {
                $primaryRole = $this->roles->first();
                return $primaryRole ? [
                    'id' => $primaryRole->id,
                    'name' => $primaryRole->name,
                    'display_name' => $primaryRole->display_name ?? $primaryRole->name
                ] : null;
            }),
            
            // Estado del usuario
            'is_active' => $this->when(isset($this->is_active), $this->is_active ?? true),
            'email_verified_at' => $this->when($this->email_verified_at, [
                'raw' => $this->email_verified_at,
                'formatted' => $this->email_verified_at?->format('d/m/Y H:i:s'),
                'verified' => !is_null($this->email_verified_at)
            ]),
            
            // Fechas formateadas
            'created_at' => [
                'raw' => $this->created_at,
                'formatted' => $this->created_at?->format('d/m/Y H:i:s'),
                'date' => $this->created_at?->format('d/m/Y'),
                'human' => $this->created_at?->diffForHumans()
            ],
            'updated_at' => [
                'raw' => $this->updated_at,
                'formatted' => $this->updated_at?->format('d/m/Y H:i:s'),
                'human' => $this->updated_at?->diffForHumans()
            ],
            
            // Estadísticas de actividad (solo si se cargan las relaciones)
            'visits_created_count' => $this->when(
                $this->relationLoaded('createdVisits'),
                $this->createdVisits?->count() ?? 0
            ),
            'visits_closed_count' => $this->when(
                $this->relationLoaded('closedVisits'),
                $this->closedVisits?->count() ?? 0
            ),
            'visitors_created_count' => $this->when(
                $this->relationLoaded('createdVisitors'),
                $this->createdVisitors?->count() ?? 0
            ),
            
            // Actividad reciente
            'recent_activity' => $this->whenLoaded('createdVisits', function() {
                $recentVisits = $this->createdVisits?->sortByDesc('created_at')->take(5);
                return $recentVisits?->map(function($visit) {
                    return [
                        'id' => $visit->id,
                        'action' => 'visit_created',
                        'description' => "Creó visita para {$visit->namePersonToVisit}",
                        'date' => $visit->created_at?->format('d/m/Y H:i:s'),
                        'human' => $visit->created_at?->diffForHumans()
                    ];
                });
            }),
            
            // Estadísticas de rendimiento
            'performance_stats' => $this->when(
                $this->relationLoaded('createdVisits') && $this->relationLoaded('closedVisits'),
                function() {
                    $createdCount = $this->createdVisits?->count() ?? 0;
                    $closedCount = $this->closedVisits?->count() ?? 0;
                    $totalActions = $createdCount + $closedCount;
                    
                    return [
                        'total_actions' => $totalActions,
                        'visits_created' => $createdCount,
                        'visits_closed' => $closedCount,
                        'efficiency_rate' => $totalActions > 0 ? round(($closedCount / $totalActions) * 100, 2) : 0,
                        'activity_level' => $this->getActivityLevel($totalActions)
                    ];
                }
            ),
            
            // Información adicional calculada
            'account_age_days' => $this->created_at?->diffInDays(now()),
            'is_new_user' => $this->created_at?->isAfter(now()->subWeek()),
            'is_admin' => $this->whenLoaded('roles', function() {
                return $this->roles->where('name', 'Admin')->isNotEmpty();
            }),
            'is_guard' => $this->whenLoaded('roles', function() {
                return $this->roles->where('name', 'Guardia')->isNotEmpty();
            }),
            
            // Información de sesión (solo para el usuario actual)
            'is_current_user' => $this->when(
                $request->user(),
                $request->user()?->id === $this->id
            ),
            
            // Metadatos para el frontend
            'can_edit_users' => $this->whenLoaded('roles', function() {
                return $this->roles->where('name', 'Admin')->isNotEmpty();
            }),
            'can_manage_visits' => $this->whenLoaded('roles', function() {
                return $this->roles->whereIn('name', ['Admin', 'Asist_adm', 'Guardia'])->isNotEmpty();
            }),
            
            // Enlaces relacionados (HATEOAS)
            'links' => array_filter([
                'self' => Route::has('users.show') ? route('users.show', $this->id) : null,
                'update' => Route::has('users.update') ? route('users.update', $this->id) : null,
                'visits_created' => Route::has('users.visits-created') ? route('users.visits-created', $this->id) : null,
                'visitors_created' => Route::has('users.visitors-created') ? route('users.visitors-created', $this->id) : null,
            ])
        ];
    }

    /**
     * Determinar nivel de actividad basado en total de acciones
     */
    private function getActivityLevel(int $totalActions): string
    {
        if ($totalActions === 0) return 'Sin actividad';
        if ($totalActions <= 5) return 'Actividad baja';
        if ($totalActions <= 20) return 'Actividad media';
        if ($totalActions <= 50) return 'Actividad alta';
        return 'Actividad muy alta';
    }

    /**
     * Customize the outgoing response for the resource.
     */
    public function withResponse(Request $request, $response): void
    {
        // Agregar headers personalizados si es necesario
        $response->header('X-User-Version', '1.0');
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'generated_at' => now()->toISOString(),
                'includes_sensitive_data' => false
            ]
        ];
    }
}