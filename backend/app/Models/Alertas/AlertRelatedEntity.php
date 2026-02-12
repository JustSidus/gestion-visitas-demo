<?php

namespace App\Models\Alertas;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la tabla 'alert_related_entities' del sistema externo de alertas
 * Representa entidades relacionadas con una alerta (agresor, testigo, etc.)
 */
class AlertRelatedEntity extends Model
{
    protected $connection = 'alerts_db';
    protected $table = 'alert_related_entities';

    protected $fillable = [
        'case_id',
        'entity_type',
        'first_name',
        'middle_name',
        'first_last_name',
        'second_last_name',
        'identification_type_id',
        'identification_number',
        'relationship_nna',
        'phone',
        'address',
        'municipality_id',
        'province_id',
        'sector',
        'age',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con el caso
     */
    public function case()
    {
        return $this->belongsTo(CaseAlert::class, 'case_id');
    }
}
