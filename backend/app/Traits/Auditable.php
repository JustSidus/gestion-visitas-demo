<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait para auditoría automática de modelos
 * 
 * Responsabilidades:
 * - Registrar automáticamente cambios en modelos
 * - Capturar eventos de creación, actualización y eliminación
 * - Proporcionar contexto completo para auditoría
 * - Facilitar trazabilidad de datos sensibles
 */
trait Auditable
{
    /**
     * Boot the auditable trait
     */
    public static function bootAuditable(): void
    {
        // Registrar evento de creación
        static::created(function (Model $model) {
            $model->auditAction('create', [], $model->getAuditableAttributes());
        });

        // Registrar evento de actualización
        static::updated(function (Model $model) {
            $changes = $model->getChanges();
            $original = $model->getOriginal();
            
            if (!empty($changes)) {
                $oldValues = [];
                $newValues = [];
                
                foreach ($changes as $key => $newValue) {
                    if ($model->isAuditable($key)) {
                        $oldValues[$key] = $original[$key] ?? null;
                        $newValues[$key] = $newValue;
                    }
                }
                
                if (!empty($newValues)) {
                    $model->auditAction('update', $oldValues, $newValues);
                }
            }
        });

        // Registrar evento de eliminación
        static::deleted(function (Model $model) {
            $model->auditAction('delete', $model->getAuditableAttributes(), []);
        });

        // Registrar evento de restauración (soft deletes)
        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                $model->auditAction('restore', [], $model->getAuditableAttributes());
            });
        }
    }

    /**
     * Log an audit action
     */
    protected function auditAction(string $action, array $oldValues = [], array $newValues = []): void
    {
        // Evitar loops infinitos
        if ($this instanceof AuditLog) {
            return;
        }

        $metadata = [
            'model_class' => get_class($this),
            'timestamp' => now()->toISOString(),
        ];

        // Agregar metadata específico del modelo
        if (method_exists($this, 'getAuditMetadata')) {
            $metadata = array_merge($metadata, $this->getAuditMetadata());
        }

        // Determinar severidad basada en el modelo y acción
        $severity = $this->getAuditSeverity($action);

        // Obtener tags específicos del modelo
        $tags = $this->getAuditTags($action);

        AuditLog::create([
            'user_id' => request()?->user()?->id,
            'action' => $action,
            'resource_type' => $this->getAuditResourceType(),
            'resource_id' => $this->getKey(),
            'old_values' => $this->sanitizeAuditValues($oldValues),
            'new_values' => $this->sanitizeAuditValues($newValues),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'session_id' => $this->getSessionId(),
            'request_method' => request()?->method(),
            'request_url' => request()?->fullUrl(),
            'metadata' => $metadata,
            'severity' => $severity,
            'tags' => $tags
        ]);
    }

    /**
     * Obtener session ID de forma segura
     */
    protected function getSessionId(): ?string
    {
        try {
            $request = request();
            if ($request && $request->hasSession()) {
                return $request->session()->getId();
            }
        } catch (\Exception $e) {
            // Ignorar error si no hay sesión (ej: seeders, comandos)
        }
        return null;
    }

    /**
     * Get auditable attributes (override in model if needed)
     */
    protected function getAuditableAttributes(): array
    {
        $attributes = $this->getAttributes();
        
        // Filtrar atributos auditables
        if (property_exists($this, 'auditableFields')) {
            return array_intersect_key($attributes, array_flip($this->auditableFields));
        }

        // Excluir campos sensibles o no relevantes
        $excludeFields = array_merge(
            ['password', 'remember_token', 'created_at', 'updated_at'],
            $this->getAuditExcludeFields()
        );

        return array_diff_key($attributes, array_flip($excludeFields));
    }

    /**
     * Check if field should be audited
     */
    protected function isAuditable(string $field): bool
    {
        // Si hay campos específicos definidos, usar solo esos
        if (property_exists($this, 'auditableFields')) {
            return in_array($field, $this->auditableFields);
        }

        // Excluir campos no auditables
        $excludeFields = array_merge(
            ['password', 'remember_token', 'created_at', 'updated_at'],
            $this->getAuditExcludeFields()
        );

        return !in_array($field, $excludeFields);
    }

    /**
     * Get fields to exclude from audit (override in model)
     */
    protected function getAuditExcludeFields(): array
    {
        return property_exists($this, 'auditExcludeFields') ? $this->auditExcludeFields : [];
    }

    /**
     * Get resource type for audit (override in model if needed)
     */
    protected function getAuditResourceType(): string
    {
        return property_exists($this, 'auditResourceType') ? 
            $this->auditResourceType : 
            strtolower(class_basename($this));
    }

    /**
     * Get audit severity for action (override in model if needed)
     */
    protected function getAuditSeverity(string $action): string
    {
        // Mapeo por defecto de acciones a severidad
        $severityMap = [
            'create' => 'medium',
            'update' => 'low',
            'delete' => 'high',
            'restore' => 'medium',
        ];

        // Permitir override específico del modelo
        if (property_exists($this, 'auditSeverityMap')) {
            $severityMap = array_merge($severityMap, $this->auditSeverityMap);
        }

        return $severityMap[$action] ?? 'low';
    }

    /**
     * Get audit tags for action (override in model if needed)
     */
    protected function getAuditTags(string $action): array
    {
        $baseTags = ['data_change', $this->getAuditResourceType()];

        // Tags específicos por acción
        $actionTags = [
            'create' => ['creation'],
            'update' => ['modification'],
            'delete' => ['deletion'],
            'restore' => ['restoration'],
        ];

        $tags = array_merge($baseTags, $actionTags[$action] ?? []);

        // Permitir tags específicos del modelo
        if (method_exists($this, 'getCustomAuditTags')) {
            $tags = array_merge($tags, $this->getCustomAuditTags($action));
        }

        return array_unique($tags);
    }

    /**
     * Sanitize audit values (remove sensitive data)
     */
    protected function sanitizeAuditValues(array $values): array
    {
        $sensitiveFields = [
            'password',
            'password_confirmation', 
            'remember_token',
            'api_token',
            'access_token',
            'refresh_token'
        ];

        foreach ($sensitiveFields as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = '[HIDDEN]';
            }
        }

        // Permitir sanitización específica del modelo
        if (method_exists($this, 'customSanitizeAuditValues')) {
            $values = $this->customSanitizeAuditValues($values);
        }

        return $values;
    }

    /**
     * Get audit logs for this model instance
     */
    public function auditLogs()
    {
        return AuditLog::where('resource_type', $this->getAuditResourceType())
                      ->where('resource_id', $this->getKey())
                      ->orderBy('created_at', 'desc');
    }

    /**
     * Get latest audit log
     */
    public function latestAuditLog()
    {
        return $this->auditLogs()->first();
    }

    /**
     * Check if model has been audited
     */
    public function hasAuditLogs(): bool
    {
        return $this->auditLogs()->exists();
    }

    /**
     * Get audit history summary
     */
    public function getAuditSummary(): array
    {
        $logs = $this->auditLogs()->get();
        
        return [
            'total_changes' => $logs->count(),
            'created_at' => $logs->where('action', 'create')->first()?->created_at,
            'last_updated' => $logs->where('action', 'update')->first()?->created_at,
            'unique_users' => $logs->pluck('user_id')->unique()->count(),
            'actions_count' => $logs->groupBy('action')->map->count(),
        ];
    }
}