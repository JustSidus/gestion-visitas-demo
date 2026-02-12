<?php

namespace App\Models;

use App\Enums\EnumDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\Auditable;

class Visitor extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'identity_document',
        'document_type',
        'name',
        'lastName',
        'phone',
        'email',
        'institution',
        'user_id',
        'created_at',
        'updated_at',
    ];

    // Usar el Enum para obtener el valor
    public function getDocumentTypeAttribute($value)
    {
        return EnumDocumentType::from((int)$value); // aqui traera el valor numerico
    }

    // Usar el Enum para establecer el valor
    public function setDocumentTypeAttribute($value)
    {
        $this->attributes['document_type'] = EnumDocumentType::from($value)->value; // Guarda el valor del enum
    }

    /**
     * Obtiene la etiqueta legible del tipo de documento
     * 
     * Proporciona un método seguro para obtener el nombre del tipo de documento
     * sin conflictos con el accessor, diseñado específicamente para Resources.
     * 
     * @return string Etiqueta del tipo de documento (ej: 'Cédula', 'Pasaporte', 'Sin Identificación', 'No capturado')
     */
    public function getDocumentTypeLabel(): string
    {
        $tipoDocumento = (int) ($this->attributes['document_type'] ?? 0);
        
        if ($tipoDocumento === 0) {
            return 'No capturado';
        }

        try {
            $tipoEnum = EnumDocumentType::tryFrom($tipoDocumento);
            
            if ($tipoEnum === null) {
                return 'No capturado';
            }

            return match ($tipoEnum) {
                EnumDocumentType::CEDULA => 'Cédula',
                EnumDocumentType::PASAPORTE => 'Pasaporte',
                EnumDocumentType::SIN_IDENTIFICACION => 'Sin Identificación',
                default => 'No capturado'
            };
        } catch (\Exception $e) {
            return 'No capturado';
        }
    }

    public function visits()
    {
        return $this->belongsToMany(Visit::class);
    }

    /**
     * Relación directa con el case_id de la alerta en la tabla pivot
     * Permite obtener el ID del caso asociado a este visitante en una visita específica
     */
    public function getCaseIdForVisit($visitId)
    {
        return $this->visits()
            ->where('visit_id', $visitId)
            ->first()
            ?->pivot
            ?->case_id;
    }

    /**
     * Relación con las alertas registradas de este visitante
     */
    public function caseAlerts()
    {
        return $this->hasManyThrough(
            \App\Models\Alertas\CaseAlert::class,
            \Illuminate\Database\Eloquent\Relations\Pivot::class,
            'visitor_id',   // Foreign key en visit_visitor
            'id',           // Foreign key en cases (alerts_db)
            'id',           // Local key en visitors
            'case_id'       // Local key en visit_visitor
        );
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * SCOPES PARA OPTIMIZAR CONSULTAS COMUNES
     */

    /**
     * Scope para búsqueda por nombre
     */
    public function scopeByName($query, $name)
    {
        return $query->where('name', 'like', "%{$name}%")
                    ->orWhere('lastName', 'like', "%{$name}%");
    }

    /**
     * Scope para búsqueda por carnet
     * NOTA: El campo 'carnet' no existe en la tabla visitors, está como 'assigned_carnet' en visits
     */
    // public function scopeByCarnet($query, $carnet)
    // {
    //     return $query->where('carnet', 'like', "%{$carnet}%");
    // }

    /**
     * Scope para búsqueda por documento de identidad
     */
    public function scopeByIdentityDocument($query, $document)
    {
        return $query->where('identity_document', 'like', "%{$document}%");
    }

    /**
     * Scope para búsqueda por empresa
     * NOTA: El campo 'company' no existe en la tabla visitors, se llama 'institution'
     */
    public function scopeByInstitution($query, $institution)
    {
        return $query->where('institution', 'like', "%{$institution}%");
    }

    /**
     * Scope para visitantes con teléfono
     */
    public function scopeWithPhone($query)
    {
        return $query->whereNotNull('phone')
                    ->where('phone', '!=', '');
    }

    /**
     * Scope para visitantes con email
     */
    public function scopeWithEmail($query)
    {
        return $query->whereNotNull('email')
                    ->where('email', '!=', '');
    }

    /**
     * Scope para visitantes con institución
     */
    public function scopeWithInstitution($query)
    {
        return $query->whereNotNull('institution')
                    ->where('institution', '!=', '');
    }

    /**
     * Scope para visitantes con visitas activas
     */
    public function scopeWithActiveVisits($query)
    {
        return $query->whereHas('visits', function($q) {
            $q->whereHas('visitStatus', function($sq) {
                $sq->where('status', 'active');
            });
        });
    }

    /**
     * Scope para visitantes frecuentes (más de 5 visitas)
     */
    public function scopeFrequent($query, $minVisits = 5)
    {
        return $query->withCount('visits')
                    ->having('visits_count', '>', $minVisits);
    }

    /**
     * Scope para visitantes nuevos (registrados esta semana)
     */
    public function scopeNewThisWeek($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    /**
     * Scope para visitantes por rango de fechas
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope para búsqueda general
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function($q) use ($searchTerm) {
            $q->where('name', 'like', "%{$searchTerm}%")
              ->orWhere('lastName', 'like', "%{$searchTerm}%")
              ->orWhere('identity_document', 'like', "%{$searchTerm}%")
              ->orWhere('institution', 'like', "%{$searchTerm}%")
              ->orWhere('phone', 'like', "%{$searchTerm}%")
              ->orWhere('email', 'like', "%{$searchTerm}%");
        });
    }

    /**
     * Scope para cargar relaciones optimizadas
     */
    public function scopeWithOptimizedRelations($query)
    {
        return $query->with([
            'creator:id,name,email',
            'visits' => function($q) {
                $q->latest()->limit(5)->with('visitStatus:id,status');
            }
        ]);
    }

    /**
     * Scope con conteos de visitas
     */
    public function scopeWithCounts($query)
    {
        return $query->withCount([
            'visits',
            'visits as active_visits_count' => function($q) {
                $q->whereHas('visitStatus', function($sq) {
                    $sq->where('status', 'active');
                });
            }
        ]);
    }

    /**
     * ACCESSORS Y MUTATORS
     */

    /**
     * Accessor para nombre completo
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->name . ' ' . $this->lastName);
    }

    /**
     * Accessor para verificar si es visitante frecuente
     */
    public function getIsFrequentAttribute(): bool
    {
        return $this->visits_count > 5;
    }

    /**
     * Accessor para verificar si es nuevo (registrado esta semana)
     */
    public function getIsNewAttribute(): bool
    {
        return $this->created_at->isAfter(now()->subWeek());
    }

    /**
     * Mutator para formatear el nombre
     */
    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = $value ? ucwords(strtolower(trim($value))) : null;
    }

    /**
     * Mutator para formatear el apellido
     */
    public function setLastNameAttribute($value): void
    {
        $this->attributes['lastName'] = $value ? ucwords(strtolower(trim($value))) : null;
    }

    /**
     * Mutator para formatear el email
     */
    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = $value ? strtolower(trim($value)) : null;
    }

    /**
     * Mutator para formatear el teléfono
     */
    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = $value ? preg_replace('/[^0-9+\-\s]/', '', $value) : null;
    }

    /**
     * Mutator para formatear el carnet
     * NOTA: El campo 'carnet' no existe en la tabla visitors
     */
    // public function setCarnetAttribute($value): void
    // {
    //     $this->attributes['carnet'] = $value ? strtoupper(trim($value)) : null;
    // }

    /**
     * Mutator para formatear la institución
     */
    public function setInstitutionAttribute($value): void
    {
        $this->attributes['institution'] = $value ? ucwords(strtolower(trim($value))) : null;
    }

    // Configuración de auditoría
    protected array $auditableFields = [
        'name',
        'identity_document',
        'document_type',
        'phone',
        'email',
        'institution',
        'photo_path',
        'observations'
    ];

    protected array $auditSeverityMap = [
        'create' => 'medium',
        'update' => 'medium',  // Datos de visitantes son sensibles
        'delete' => 'high'
    ];

    protected string $auditResourceType = 'visitor';

    /**
     * Get custom audit tags
     */
    protected function getCustomAuditTags(string $action): array
    {
        $tags = [];
        
        if ($action === 'update' && $this->isDirty('identity_document')) {
            $tags[] = 'document_change';
        }
        
        if ($action === 'update' && $this->isDirty('photo_path')) {
            $tags[] = 'photo_change';
        }
        
        if ($action === 'create' || $action === 'update') {
            $tags[] = 'personal_data';
        }
        
        return $tags;
    }

    /**
     * Get audit metadata
     */
    protected function getAuditMetadata(): array
    {
        return [
            'document_type' => $this->document_type?->value,
            'has_photo' => !empty($this->photo_path),
            'total_visits' => $this->visits()->count(),
            'active_visits' => $this->visits()->where('status_id', \App\Enums\EnumVisitStatuses::ABIERTO->value)->count(),
        ];
    }

    /**
     * Sanitizar datos sensibles para auditoría
     */
    protected function customSanitizeAuditValues(array $values): array
    {
        // Mantener solo los últimos 4 dígitos del documento
        if (isset($values['identity_document'])) {
            $document = (string) $values['identity_document'];
            if (strlen($document) > 4) {
                $values['identity_document'] = '****' . substr($document, -4);
            }
        }

        // Parcialmente ocultar email
        if (isset($values['email']) && $values['email']) {
            $email = $values['email'];
            $atPos = strpos($email, '@');
            if ($atPos !== false && $atPos > 2) {
                $values['email'] = substr($email, 0, 2) . '****' . substr($email, $atPos);
            }
        }

        // Parcialmente ocultar teléfono
        if (isset($values['phone']) && $values['phone']) {
            $phone = (string) $values['phone'];
            if (strlen($phone) > 4) {
                $values['phone'] = '****' . substr($phone, -4);
            }
        }

        return $values;
    }
}
