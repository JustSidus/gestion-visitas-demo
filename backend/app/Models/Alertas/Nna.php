<?php

namespace App\Models\Alertas;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la tabla 'nna' del sistema externo de alertas
 * Representa un Niño, Niña o Adolescente
 */
class Nna extends Model
{
    protected $connection = 'alerts_db';
    protected $table = 'nna';

    protected $fillable = [
        'code',
        'firebase_id',
        'name',
        'surname',
        'nickname',
        'gender_id',
        'birth_date',
        'age',
        'nationality_id',
        'language_id',
        'address',
        'ageCalculatedBy',
        'ageMeasuredIn',
        'isPregnant',
        'lastMenstruationDate',
        'isDeclared',
        'birthPlace',
        'relevantCondition',
        'case_type_id',
        'user_create_id',
        'user_update_id',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'lastMenstruationDate' => 'date',
        'isPregnant' => 'boolean',
        'isDeclared' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con casos (muchos a muchos)
     */
    public function cases()
    {
        return $this->belongsToMany(
            CaseAlert::class,
            'case_nna',
            'nna_id',
            'case_id'
        )->withTimestamps();
    }
}
