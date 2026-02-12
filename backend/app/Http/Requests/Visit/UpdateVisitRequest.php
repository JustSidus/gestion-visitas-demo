<?php

namespace App\Http\Requests\Visit;

use Illuminate\Foundation\Http\FormRequest;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Request para validar actualización de visitas
 * 
 * Responsabilidades:
 * - Validar campos editables de una visita
 * - Verificar permisos de edición
 * - Preparar datos para actualización
 */
class UpdateVisitRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para esta acción
     */
    public function authorize(): bool
    {
        try {
            // La autorización detallada se maneja vía Policy en el controlador.
            // Aquí solo verificamos que el usuario esté autenticado.
            return JWTAuth::parseToken()->authenticate() !== null;
            
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
            'namePersonToVisit' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'min:2',
                'regex:/^[a-zA-ZáéíóúñÁÉÍÓÚÑüÜ\s\.\-\']+$/'
            ],
            'department' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'min:2'
            ],
            'building' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:4'
            ],
            'floor' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:4'
            ],
            'reason' => [
                'sometimes',
                'required',
                'string',
                'max:500'
            ],
            'vehicle_plate' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9\-]+$/'
            ],
            'person_to_visit_email' => [
                'sometimes',
                'nullable',
                'email:rfc,dns',
                'max:255'
            ],
            'visitor_ids' => [
                'sometimes',
                'array',
                'min:1',
                'max:10'
            ],
            'visitor_ids.*' => [
                'integer',
                'exists:visitors,id'
            ]
        ];
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'namePersonToVisit.regex' => 'El nombre solo puede contener letras, espacios, acentos y algunos caracteres especiales (. - \').',
            'namePersonToVisit.min' => 'El nombre debe tener al menos 2 caracteres.',
            'reason.required' => 'El motivo de la visita es obligatorio.',
            'reason.max' => 'El motivo no puede exceder 500 caracteres.',
            'vehicle_plate.regex' => 'La placa solo puede contener letras, números y guiones (-).',
            'vehicle_plate.max' => 'La placa no puede exceder 20 caracteres',
            'person_to_visit_email.email' => 'El formato del email no es válido.',
            'visitor_ids.min' => 'Debe seleccionar al menos un visitante.',
            'visitor_ids.max' => 'No puede seleccionar más de 10 visitantes por visita.',
        ];
    }

    /**
     * Atributos personalizados
     */
    public function attributes(): array
    {
        return [
            'namePersonToVisit' => 'persona a visitar',
            'department' => 'departamento',
            'building' => 'edificio',
            'floor' => 'piso',
            'reason' => 'motivo de la visita',
            'vehicle_plate' => 'placa del vehículo',
            'person_to_visit_email' => 'email de la persona a visitar',
            'visitor_ids' => 'visitantes'
        ];
    }

    /**
     * Preparar datos para validación
     */
    protected function prepareForValidation(): void
    {
        // Limpiar campos de texto si están presentes
        if ($this->has('namePersonToVisit')) {
            $this->merge([
                'namePersonToVisit' => trim($this->namePersonToVisit)
            ]);
        }

        if ($this->has('department')) {
            $this->merge([
                'department' => trim($this->department)
            ]);
        }

        if ($this->has('building')) {
            $value = trim($this->building);
            $this->merge([
                'building' => $value !== '' ? (int)$value : null
            ]);
        }

        if ($this->has('floor')) {
            $value = trim($this->floor);
            $this->merge([
                'floor' => $value !== '' ? (int)$value : null
            ]);
        }

        if ($this->has('reason')) {
            $this->merge([
                'reason' => trim($this->reason)
            ]);
        }

        // Formatear placa si se proporciona
        if ($this->has('vehicle_plate') && $this->vehicle_plate) {
            $this->merge([
                'vehicle_plate' => strtoupper(trim(str_replace(' ', '', $this->vehicle_plate)))
            ]);
        }

        // Formatear email si se proporciona
        if ($this->has('person_to_visit_email') && $this->person_to_visit_email) {
            $this->merge([
                'person_to_visit_email' => strtolower(trim($this->person_to_visit_email))
            ]);
        }
    }

    /**
     * Validación adicional
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            // Validar que los visitor_ids no tengan duplicados si se proporciona
            if ($this->has('visitor_ids')) {
                $visitorIds = $this->input('visitor_ids', []);
                if (count($visitorIds) !== count(array_unique($visitorIds))) {
                    $v->errors()->add(
                        'visitor_ids', 
                        'No puede seleccionar el mismo visitante múltiples veces.'
                    );
                }
            }
        });
    }
}