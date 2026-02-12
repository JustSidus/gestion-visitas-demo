<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Traits\Auditable;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'microsoft_id',
        'is_active',
        'created_by'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Relación con Roles (un usuario puede tener múltiples roles - many to many)
     * Nota: En la práctica, solo asignaremos UN rol por usuario
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }

    /**
     * Relación con el usuario que lo creó
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relación con usuarios que este usuario creó
     */
    public function createdUsers()
    {
        return $this->hasMany(User::class, 'created_by');
    }

    public function getJWTIdentifier()
    {
        return $this->getKey(); // Laravel usará el ID del usuario
    }

    /**
     * Devuelve un array con claims personalizados para el JWT.
     */
    public function getJWTCustomClaims()
    {
        $primaryRole = $this->roles->first();
        
        return [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $primaryRole ? $primaryRole->name : null,
            'role_id' => $primaryRole ? $primaryRole->id : null,
        ];
    }

    /**
     * Verifica si el usuario tiene un rol específico
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Verifica si el usuario es Admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('Admin');
    }

    /**
     * Verifica si el usuario es Asist_adm
     */
    public function isAsistAdm(): bool
    {
        return $this->hasRole('Asist_adm');
    }

    /**
     * Verifica si el usuario es Guardia
     */
    public function isGuardia(): bool
    {
        return $this->hasRole('Guardia');
    }

    /**
     * Verifica si el usuario es aux_ugc
     */
    public function isAuxUgc(): bool
    {
        return $this->hasRole('aux_ugc');
    }

    /**
     * Verifica si el usuario puede acceder a la aplicación
     */
    public function canAccessApp(): bool
    {
        return $this->is_active;
    }

    /**
     * Verifica si el usuario puede gestionar usuarios (solo Admin)
     */
    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Verifica si el usuario puede gestionar todas las visitas
     */
    public function canManageAllVisits(): bool
    {
        return $this->isAdmin() || $this->isAsistAdm();
    }

    /**
     * Verifica si el usuario puede crear visitas
     */
    public function canCreateVisits(): bool
    {
        return $this->isAdmin() || $this->isAsistAdm();
    }

    /**
     * Relación con visitas creadas por este usuario
     */
    public function createdVisits()
    {
        return $this->hasMany(Visit::class, 'user_id');
    }
    
    /**
     * Alias para retrocompatibilidad - todas las visitas relacionadas con el usuario
     */
    public function visits()
    {
        return $this->createdVisits();
    }

    /**
     * Relación con visitas cerradas por este usuario
     */
    public function closedVisits()
    {
        return $this->hasMany(Visit::class, 'closed_by_user_id');
    }

    /**
     * Relación con visitantes creados por este usuario
     */
    public function createdVisitors()
    {
        return $this->hasMany(Visitor::class, 'user_id');
    }

    /**
     * SCOPES PARA OPTIMIZAR CONSULTAS COMUNES
     */

    /**
     * Scope para usuarios activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para usuarios inactivos
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope para usuarios por rol
     */
    public function scopeByRole($query, $roleName)
    {
        return $query->whereHas('roles', function($q) use ($roleName) {
            $q->where('name', $roleName);
        });
    }

    /**
     * Scope para administradores
     */
    public function scopeAdmins($query)
    {
        return $query->byRole('Admin');
    }

    /**
     * Scope para guardias
     */
    public function scopeGuards($query)
    {
        return $query->byRole('Guardia');
    }

    /**
     * Scope para asistentes administrativos
     */
    public function scopeAssistants($query)
    {
        return $query->byRole('Asist_adm');
    }

    /**
     * Scope para usuarios verificados
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    /**
     * Scope para usuarios no verificados
     */
    public function scopeUnverified($query)
    {
        return $query->whereNull('email_verified_at');
    }

    /**
     * Scope para búsqueda por nombre o email
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function($q) use ($searchTerm) {
            $q->where('name', 'like', "%{$searchTerm}%")
              ->orWhere('email', 'like', "%{$searchTerm}%");
        });
    }

    /**
     * Scope para usuarios creados en un rango de fechas
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope para cargar relaciones optimizadas
     */
    public function scopeWithOptimizedRelations($query)
    {
        return $query->with([
            'roles:id,name',
            'createdBy:id,name,email'
        ]);
    }

    /**
     * Scope con conteos de actividad
     */
    public function scopeWithCounts($query)
    {
        return $query->withCount([
            'createdVisits',
            'closedVisits',
            'createdVisitors'
        ]);
    }

    /**
     * Scope para usuarios que han creado visitas
     */
    public function scopeWithVisitActivity($query)
    {
        return $query->whereHas('createdVisits')
                    ->orWhereHas('closedVisits');
    }

    /**
     * ACCESSORS Y MUTATORS
     */

    /**
     * Accessor para el rol principal del usuario
     */
    public function getPrimaryRoleAttribute(): ?string
    {
        return $this->roles->first()?->name;
    }

    /**
     * Accessor para verificar si es un usuario del sistema
     */
    public function getIsSystemUserAttribute(): bool
    {
        return $this->hasRole('Admin') || $this->hasRole('Asist_adm') || $this->hasRole('Guardia');
    }

    /**
     * Accessor para obtener estadísticas de actividad
     */
    public function getActivityStatsAttribute(): array
    {
        return [
            'visits_created' => $this->created_visits_count ?? 0,
            'visits_closed' => $this->closed_visits_count ?? 0,
            'visitors_created' => $this->created_visitors_count ?? 0,
            'total_actions' => ($this->created_visits_count ?? 0) + 
                             ($this->closed_visits_count ?? 0) + 
                             ($this->created_visitors_count ?? 0)
        ];
    }

    /**
     * Mutator para formatear el nombre
     */
    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = $value ? ucwords(strtolower(trim($value))) : null;
    }

    /**
     * Mutator para formatear el email
     */
    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = $value ? strtolower(trim($value)) : null;
    }

    // Configuración de auditoría
    protected array $auditableFields = [
        'name',
        'email',
        'role_id',
        'email_verified_at',
        'is_active'
    ];

    protected array $auditExcludeFields = [
        'password',
        'email_verification_token',
        'password_reset_token'
    ];

    protected array $auditSeverityMap = [
        'create' => 'high',      // Crear usuarios es crítico
        'update' => 'medium',
        'delete' => 'critical',  // Eliminar usuarios es crítico
        'login' => 'medium',
        'logout' => 'low'
    ];

    protected string $auditResourceType = 'user';

    /**
     * Get custom audit tags
     */
    protected function getCustomAuditTags(string $action): array
    {
        $tags = [];
        
        if ($action === 'update' && $this->isDirty('role_id')) {
            $tags[] = 'role_change';
        }
        
        if ($action === 'update' && $this->isDirty('is_active')) {
            $tags[] = $this->is_active ? 'user_activation' : 'user_deactivation';
        }
        
        if ($action === 'update' && $this->isDirty('email')) {
            $tags[] = 'email_change';
        }
        
        return $tags;
    }

    /**
     * Get audit metadata
     */
    protected function getAuditMetadata(): array
    {
        return [
            'role_name' => $this->role?->name,
            'total_visits_created' => $this->visits()->count(),
            'last_login' => $this->last_login_at,
            'is_system_user' => in_array($this->email, ['admin@system.com', 'super@admin.com']),
        ];
    }

    /**
     * Sanitizar datos sensibles para auditoría
     */
    protected function customSanitizeAuditValues(array $values): array
    {
        // Parcialmente ocultar email
        if (isset($values['email']) && $values['email']) {
            $email = $values['email'];
            $atPos = strpos($email, '@');
            if ($atPos !== false && $atPos > 2) {
                $values['email'] = substr($email, 0, 2) . '****' . substr($email, $atPos);
            }
        }

        return $values;
    }
}
