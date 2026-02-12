<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo para registros de auditoría del sistema
 * 
 * Responsabilidades:
 * - Almacenar trail completo de acciones del sistema
 * - Relacionar acciones con usuarios y recursos
 * - Proporcionar base para reportes de compliance
 * - Facilitar investigación de incidentes de seguridad
 */
class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'resource_type',
        'resource_id', 
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'session_id',
        'request_method',
        'request_url',
        'status_code',
        'duration_ms',
        'metadata',
        'severity',
        'tags'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'tags' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the user that performed this action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for filtering by action type
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope for filtering by resource
     */
    public function scopeByResource($query, string $resourceType, $resourceId = null)
    {
        $query = $query->where('resource_type', $resourceType);
        
        if ($resourceId !== null) {
            $query->where('resource_id', $resourceId);
        }
        
        return $query;
    }

    /**
     * Scope for filtering by user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope for filtering by severity
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope for security incidents
     */
    public function scopeSecurityIncidents($query)
    {
        return $query->whereIn('severity', ['high', 'critical'])
                    ->orWhereJsonContains('tags', 'security')
                    ->orWhereJsonContains('tags', 'unauthorized_access');
    }

    /**
     * Scope for failed actions
     */
    public function scopeFailedActions($query)
    {
        return $query->where('status_code', '>=', 400);
    }

    /**
     * Get formatted action description
     */
    public function getActionDescriptionAttribute(): string
    {
        $descriptions = [
            'create' => 'Crear',
            'update' => 'Actualizar', 
            'delete' => 'Eliminar',
            'view' => 'Ver',
            'login' => 'Iniciar sesión',
            'logout' => 'Cerrar sesión',
            'export' => 'Exportar',
            'import' => 'Importar',
            'close_visit' => 'Cerrar visita',
            'assign_carnet' => 'Asignar carnet',
            'send_notification' => 'Enviar notificación'
        ];

        return $descriptions[$this->action] ?? $this->action;
    }

    /**
     * Get severity color for UI
     */
    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity) {
            'critical' => 'red-600',
            'high' => 'red-500',
            'medium' => 'yellow-500',
            'low' => 'green-500',
            default => 'gray-500'
        };
    }

    /**
     * Check if this is a security-related log
     */
    public function isSecurityRelated(): bool
    {
        $securityActions = ['login', 'logout', 'unauthorized_access', 'rate_limit_exceeded'];
        $securityTags = ['security', 'suspicious', 'unauthorized_access'];
        
        if (in_array($this->action, $securityActions)) {
            return true;
        }
        
        if ($this->tags && array_intersect($this->tags, $securityTags)) {
            return true;
        }
        
        return false;
    }

    /**
     * Check if this represents a data change
     */
    public function isDataChange(): bool
    {
        return in_array($this->action, ['create', 'update', 'delete']) && 
               !empty($this->new_values);
    }

    /**
     * Get changes summary
     */
    public function getChangesSummary(): array
    {
        if (!$this->isDataChange()) {
            return [];
        }

        $changes = [];
        $oldValues = $this->old_values ?? [];
        $newValues = $this->new_values ?? [];

        foreach ($newValues as $field => $newValue) {
            $oldValue = $oldValues[$field] ?? null;
            
            if ($oldValue !== $newValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue
                ];
            }
        }

        return $changes;
    }

    /**
     * Static method to log action
     */
    public static function logAction(array $data): self
    {
        // Valores por defecto
        $defaults = [
            'severity' => 'low',
            'status_code' => 200,
            'tags' => [],
            'metadata' => []
        ];

        return self::create(array_merge($defaults, $data));
    }

    /**
     * Static method to log security incident
     */
    public static function logSecurityIncident(string $incident, array $data = []): self
    {
        $logData = array_merge([
            'action' => $incident,
            'severity' => 'high',
            'tags' => ['security', 'incident'],
            'resource_type' => 'security',
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'user_id' => request()?->user()?->id,
        ], $data);

        return self::logAction($logData);
    }

    /**
     * Static method to log data change
     */
    public static function logDataChange(string $action, string $resourceType, $resourceId, array $oldValues, array $newValues, array $metadata = []): self
    {
        return self::logAction([
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
            'severity' => 'medium',
            'tags' => ['data_change'],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'user_id' => request()?->user()?->id,
        ]);
    }
}