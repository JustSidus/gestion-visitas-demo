<?php

namespace App\Http\Requests\Visit;

use Illuminate\Foundation\Http\FormRequest;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Request para validar búsquedas y filtros de visitas
 * 
 * Responsabilidades:
 * - Validar parámetros de búsqueda
 * - Verificar permisos de consulta
 * - Preparar filtros para queries
 */
class SearchVisitsRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para esta acción
     */
    public function authorize(): bool
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            // Todos los usuarios autenticados pueden buscar visitas
            // Los permisos específicos se manejan en el controlador/servicio
            return !is_null($user);
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Reglas de validación
     */
    public function rules(): array
    {
        return [
            // Paginación
            'page' => [
                'sometimes',
                'integer',
                'min:1'
            ],
            'per_page' => [
                'sometimes',
                'integer',
                'min:5',
                'max:100'
            ],
            
            // Ordenamiento
            'sort_by' => [
                'sometimes',
                'string',
                'in:id,namePersonToVisit,department,reason,created_at,closed_at,visitor_count'
            ],
            'sort_direction' => [
                'sometimes',
                'string',
                'in:asc,desc'
            ],
            
            // Filtros de búsqueda
            'search' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'min:2'
            ],
            'visitor_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'min:2'
            ],
            'visitor_carnet' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                'regex:/^[0-9\-]+$/'
            ],
            'vehicle_plate' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9\-]+$/'
            ],
            'department' => [
                'sometimes',
                'nullable',
                'string',
                'max:255'
            ],
            
            // Filtros de estado
            'status' => [
                'sometimes',
                'nullable',
                'string',
                'in:active,closed,all'
            ],
            
            // Filtros de fecha
            'date_from' => [
                'sometimes',
                'nullable',
                'date',
                'before_or_equal:today'
            ],
            'date_to' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:date_from',
                'before_or_equal:today'
            ],
            'created_today' => [
                'sometimes',
                'boolean'
            ],
            'created_this_week' => [
                'sometimes',
                'boolean'
            ],
            'created_this_month' => [
                'sometimes',
                'boolean'
            ],
            
            // Filtros adicionales
            'has_vehicle' => [
                'sometimes',
                'boolean'
            ],
            'has_email' => [
                'sometimes',
                'boolean'
            ],
            'visitor_count_min' => [
                'sometimes',
                'integer',
                'min:1'
            ],
            'visitor_count_max' => [
                'sometimes',
                'integer',
                'min:1',
                'gte:visitor_count_min'
            ]
        ];
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'search.min' => 'La búsqueda debe tener al menos 2 caracteres.',
            'visitor_name.min' => 'El nombre del visitante debe tener al menos 2 caracteres.',
            'visitor_carnet.regex' => 'El carnet solo puede contener números y guiones.',
            'vehicle_plate.regex' => 'La placa solo puede contener letras, números y guiones (-).',
            'date_from.before_or_equal' => 'La fecha inicial no puede ser futura.',
            'date_to.after_or_equal' => 'La fecha final debe ser posterior a la fecha inicial.',
            'date_to.before_or_equal' => 'La fecha final no puede ser futura.',
            'visitor_count_max.gte' => 'El máximo de visitantes debe ser mayor o igual al mínimo.',
            'per_page.max' => 'No se pueden mostrar más de 100 elementos por página.',
            'sort_by.in' => 'El campo de ordenamiento no es válido.',
            'sort_direction.in' => 'La dirección de ordenamiento debe ser asc o desc.',
            'status.in' => 'El estado debe ser: active, closed o all.'
        ];
    }

    /**
     * Atributos personalizados
     */
    public function attributes(): array
    {
        return [
            'search' => 'búsqueda',
            'visitor_name' => 'nombre del visitante',
            'visitor_carnet' => 'carnet del visitante',
            'vehicle_plate' => 'placa del vehículo',
            'department' => 'departamento',
            'date_from' => 'fecha inicial',
            'date_to' => 'fecha final',
            'visitor_count_min' => 'mínimo de visitantes',
            'visitor_count_max' => 'máximo de visitantes',
            'per_page' => 'elementos por página',
            'sort_by' => 'ordenar por',
            'sort_direction' => 'dirección del ordenamiento'
        ];
    }

    /**
     * Preparar datos para validación
     */
    protected function prepareForValidation(): void
    {
        // Valores por defecto
        $this->merge([
            'page' => $this->input('page', 1),
            'per_page' => $this->input('per_page', 15),
            'sort_by' => $this->input('sort_by', 'created_at'),
            'sort_direction' => $this->input('sort_direction', 'desc'),
            'status' => $this->input('status', 'all')
        ]);

        // Limpiar campos de búsqueda
        if ($this->has('search')) {
            $this->merge([
                'search' => trim($this->search)
            ]);
        }

        if ($this->has('visitor_name')) {
            $this->merge([
                'visitor_name' => trim($this->visitor_name)
            ]);
        }

        if ($this->has('department')) {
            $this->merge([
                'department' => trim($this->department)
            ]);
        }

        // Formatear placa
        if ($this->has('vehicle_plate') && $this->vehicle_plate) {
            $this->merge([
                'vehicle_plate' => strtoupper(trim(str_replace(' ', '', $this->vehicle_plate)))
            ]);
        }

        // Formatear carnet
        if ($this->has('visitor_carnet') && $this->visitor_carnet) {
            $this->merge([
                'visitor_carnet' => trim($this->visitor_carnet)
            ]);
        }
    }

    /**
     * Obtener filtros preparados para el servicio
     */
    public function getFilters(): array
    {
        return [
            'search' => $this->input('search'),
            'visitor_name' => $this->input('visitor_name'),
            'visitor_carnet' => $this->input('visitor_carnet'),
            'vehicle_plate' => $this->input('vehicle_plate'),
            'department' => $this->input('department'),
            'status' => $this->input('status'),
            'date_from' => $this->input('date_from'),
            'date_to' => $this->input('date_to'),
            'created_today' => $this->boolean('created_today'),
            'created_this_week' => $this->boolean('created_this_week'),
            'created_this_month' => $this->boolean('created_this_month'),
            'has_vehicle' => $this->has('has_vehicle') ? $this->boolean('has_vehicle') : null,
            'has_email' => $this->has('has_email') ? $this->boolean('has_email') : null,
            'visitor_count_min' => $this->input('visitor_count_min'),
            'visitor_count_max' => $this->input('visitor_count_max')
        ];
    }

    /**
     * Obtener parámetros de paginación
     */
    public function getPaginationParams(): array
    {
        return [
            'page' => $this->integer('page'),
            'per_page' => $this->integer('per_page')
        ];
    }

    /**
     * Obtener parámetros de ordenamiento
     */
    public function getSortParams(): array
    {
        return [
            'sort_by' => $this->input('sort_by'),
            'sort_direction' => $this->input('sort_direction')
        ];
    }
}