<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;
use App\Enums\EnumVisitStatuses;

class Visit extends Model
{
    use HasFactory, Auditable;

    /**
     * Campos asignables masivamente
     * 
     * NOTA: send_email indica si se INTENTÓ enviar email de notificación
     * - true = Se envió o se intentó enviar email
     * - false = No se solicitó envío de email
     * Este campo NO garantiza que el email llegó, solo registra la intención
     */
    protected $fillable = [
        'user_id',
        'closed_by',
        'namePersonToVisit',
        'department',
        'building',
        'floor',
        'reason',
        'status_id',
        'created_at',
        'updated_at',
        'end_at',
        'assigned_carnet',
        'mission_case',
        'vehicle_plate',
        'person_to_visit_email',
        'send_email'  // Registra intención de envío (no garantiza éxito)
    ];

    /**
     * Casts de atributos
     */
    protected $casts = [
        'send_email' => 'boolean',
        'mission_case' => 'boolean',
        'building' => 'integer',
        'floor' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function visitors()
    {
        return $this->belongsToMany(Visitor::class)
            ->withPivot('case_id', 'created_at', 'updated_at')
            ->withTimestamps();
    }

    /**
    * Relación con alertas del sistema externo a través de visit_visitor
     * Una visita puede tener múltiples alertas registradas (una por visitante)
     */
    public function caseAlerts()
    {
        return $this->hasManyThrough(
            \App\Models\Alertas\CaseAlert::class,
            Visitor::class,
            'id',            // Foreign key en visitors
            'id',            // Foreign key en cases (alerts_db)
            'id',            // Local key en visits
            'case_id'        // Local key en visit_visitor (a través de pivot)
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function closedByUser()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Alias requerido por consultas antiguas (mantiene compatibilidad).
     */
    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    // relación 'departament' removida

    public function status()
    {
        return $this->belongsTo(VisitStatus::class, 'status_id');
    }

    public function visitStatus()
    {
        return $this->belongsTo(VisitStatus::class, 'status_id');
    }

    // Relación con el usuario que creó la visita
    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relación con el usuario que cerró la visita
    public function closer()
    {
        // Usamos 'closed_by' como fuente única de verdad para el usuario que cierra la visita
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * SCOPES PARA OPTIMIZAR CONSULTAS COMUNES
     */

    /**
     * Scope para visitas activas
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', EnumVisitStatuses::ABIERTO->value);
    }

    /**
     * Scope para visitas cerradas
     */
    public function scopeClosed($query)
    {
        return $query->where('status_id', EnumVisitStatuses::CERRADO->value);
    }

    /**
     * Scope para visitas de hoy
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope para visitas de esta semana
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    /**
     * Scope para visitas de este mes
     */
    public function scopeThisMonth($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfMonth(),
            now()->endOfMonth()
        ]);
    }

    /**
     * Scope para visitas por rango de fechas
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope para visitas por departamento
     */
    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    /**
     * Scope para visitas con vehículo
     */
    public function scopeWithVehicle($query)
    {
        return $query->whereNotNull('vehicle_plate')
                    ->where('vehicle_plate', '!=', '');
    }

    /**
     * Scope para visitas con email
     */
    public function scopeWithEmail($query)
    {
        return $query->whereNotNull('person_to_visit_email')
                    ->where('person_to_visit_email', '!=', '');
    }

    /**
     * Scope para búsqueda por visitante
     */
    public function scopeByVisitor($query, $searchTerm)
    {
        return $query->whereHas('visitors', function($q) use ($searchTerm) {
            $q->where('name', 'like', "%{$searchTerm}%")
              ->orWhere('carnet', 'like', "%{$searchTerm}%")
              ->orWhere('identity_document', 'like', "%{$searchTerm}%");
        });
    }

    /**
     * Scope para búsqueda general
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function($q) use ($searchTerm) {
            $q->where('namePersonToVisit', 'like', "%{$searchTerm}%")
              ->orWhere('department', 'like', "%{$searchTerm}%")
              ->orWhere('reason', 'like', "%{$searchTerm}%")
              ->orWhere('vehicle_plate', 'like', "%{$searchTerm}%");
        })->orWhereHas('visitors', function($q) use ($searchTerm) {
            $q->where('name', 'like', "%{$searchTerm}%")
              ->orWhere('carnet', 'like', "%{$searchTerm}%");
        });
    }

    /**
     * Scope para cargar relaciones optimizadas
     */
    public function scopeWithOptimizedRelations($query)
    {
        return $query->with([
            'creator:id,name,email',
            'closer:id,name,email',
            'visitStatus:id,status,description',
            'visitors:id,name,carnet,company,identity_document'
        ]);
    }

    /**
     * Scope para visitas por usuario específico
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope para visitas cerradas por usuario específico
     */
    public function scopeClosedByUser($query, $userId)
    {
        return $query->where('closed_by', $userId);
    }

    /**
     * Scope para visitas por año
     */
    public function scopeByYear($query, $year)
    {
        return $query->whereYear('created_at', $year);
    }

    /**
     * Scope para visitas por mes
     */
    public function scopeByMonth($query, $year, $month)
    {
        return $query->whereYear('created_at', $year)
                    ->whereMonth('created_at', $month);
    }

    /**
     * Scope para estadísticas con conteos
     */
    public function scopeWithCounts($query)
    {
        return $query->withCount(['visitors']);
    }

    /**
     * ACCESSORS Y MUTATORS
     */

    /**
     * Accessor para verificar si la visita está activa
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status_id === EnumVisitStatuses::ABIERTO->value;
    }

    /**
     * Accessor para obtener la duración de la visita
     */
    public function getDurationAttribute(): ?string
    {
        // Unificamos en 'end_at' como marca de cierre
        if (!$this->end_at) {
            return null;
        }
        $endAt = $this->end_at instanceof \Carbon\Carbon ? $this->end_at : \Carbon\Carbon::parse($this->end_at);
        return $this->created_at->diffForHumans($endAt, true);
    }

    /**
     * Accessor para verificar si la visita es reciente (menos de 2 horas)
     */
    public function getIsRecentAttribute(): bool
    {
        return $this->created_at->isAfter(now()->subHours(2));
    }

    /**
     * Mutator para formatear la placa del vehículo
     */
    public function setVehiclePlateAttribute($value): void
    {
        $this->attributes['vehicle_plate'] = $value ? strtoupper(trim($value)) : null;
    }

        /**
     * Mutator para email de persona a visitar
     */
    public function setPersonToVisitEmailAttribute($value): void
    {
        $this->attributes['person_to_visit_email'] = $value ? strtolower(trim($value)) : null;
    }

    // Configuración de auditoría
    protected array $auditableFields = [
        'namePersonToVisit',
        'department',
        'building',
        'floor',
        'reason',
        'status_id',
        'end_at',
        'assigned_carnet',
        'observations',
        'closed_by'
    ];

    protected array $auditSeverityMap = [
        'create' => 'medium',
        'update' => 'low',
        'delete' => 'high',
        'close' => 'medium'
    ];

    protected string $auditResourceType = 'visit';

    /**
     * Get custom audit tags
     */
    protected function getCustomAuditTags(string $action): array
    {
        $tags = [];
        
        if ($action === 'update' && $this->isDirty('status_id')) {
            $tags[] = 'status_change';
        }
        // Detectar cierre por cambio de end_at
        if ($action === 'update' && $this->isDirty('end_at') && $this->end_at) {
            $tags[] = 'visit_closure';
        }
        // Detectar asignación de carnet
        if ($action === 'update' && $this->isDirty('assigned_carnet')) {
            $tags[] = 'carnet_assignment';
        }
        
        return $tags;
    }

    /**
     * Get audit metadata
     */
    protected function getAuditMetadata(): array
    {
        return [
            'visit_status' => $this->visitStatus?->name,
            'visitor_count' => $this->visitors()->count(),
            'has_vehicle' => !empty($this->vehicle_plate),
        ];
    }
}
