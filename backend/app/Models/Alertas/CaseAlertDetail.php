<?php

namespace App\Models\Alertas;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la tabla 'case_alert_details' del sistema externo de alertas
 * Representa los detalles específicos de una alerta
 */
class CaseAlertDetail extends Model
{
    protected $connection = 'alerts_db';
    protected $table = 'case_alert_details';

    protected $fillable = [
        'case_id',
        'alert_type_id',
        'alert_subtype_id',
        'alert_detail',
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
