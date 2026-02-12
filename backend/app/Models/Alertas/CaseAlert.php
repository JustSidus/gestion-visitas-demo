<?php

namespace App\Models\Alertas;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la tabla 'cases' del sistema externo de alertas
 * Representa un caso/alerta registrado
 */
class CaseAlert extends Model
{
    protected $connection = 'alerts_db';
    protected $table = 'cases';

    protected $fillable = [
        'code',
        'case_manager_id',
        'OriginCaseId',
        'DepartamentoRecibe',
        'municipality_id',
        'nna_id',
        'status_id',
        'start_date',
        'end_date',
        'unit_id',
        'program_id',
        'description',
        'previous_situation_description',
        'notes',
        'case_confirmation_reason',
        'case_confirmation_date',
        'case_confirmation_status_by',
        'status_description',
        'user_create_id',
        'user_update_id',
        'case_reason_id',
        'migration_case_type',
        'located_family',
        'received_help',
        'helper_name',
        'entry_times',
        'return_times',
        'entry_point',
        'intervention_authority',
        'intervention_date',
        'migration_center',
        'place_of_detention',
        'companions_at_detention',
        'dominican_parent',
        'refugee_candidate',
        'basic_needs_satisfied',
        'refugee_reasons',
        'was_accompanied',
        'companion_full_name',
        'companion_gender_id',
        'companion_relationship_id',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'case_confirmation_date' => 'datetime',
        'intervention_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'located_family' => 'boolean',
        'received_help' => 'boolean',
        'dominican_parent' => 'boolean',
        'refugee_candidate' => 'boolean',
        'basic_needs_satisfied' => 'boolean',
        'was_accompanied' => 'boolean',
    ];

    /**
     * Relación con NNAs (muchos a muchos)
     */
    public function nnas()
    {
        return $this->belongsToMany(
            Nna::class,
            'case_nna',
            'case_id',
            'nna_id'
        )->withTimestamps();
    }

    /**
     * Relación con detalles de alerta
     */
    public function alertDetails()
    {
        return $this->hasMany(CaseAlertDetail::class, 'case_id');
    }

    /**
     * Relación con entidades relacionadas
     */
    public function relatedEntities()
    {
        return $this->hasMany(AlertRelatedEntity::class, 'case_id');
    }
}
