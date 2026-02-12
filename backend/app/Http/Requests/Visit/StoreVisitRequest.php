<?php

namespace App\Http\Requests\Visit;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Visit;
use App\Enums\EnumVisitStatuses;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Request para validar creación de visitas
 * 
 * Responsabilidades:
 * - Validar estructura y formato de datos
 * - Aplicar reglas de negocio en validación
 * - Preparar datos antes de validar
 * - Proporcionar mensajes de error personalizados
 */
class StoreVisitRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para esta acción
     */
    public function authorize(): bool
    {
        // Solo usuarios autenticados pueden crear visitas
        try {
            return JWTAuth::parseToken()->authenticate() !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Reglas de validación detalladas
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id'
            ],
            'assigned_carnet' => [
                $this->boolean('mission_case') ? 'nullable' : 'required',
                'nullable',
                'integer',
                'min:1',
                'max:9999',
                //  REMOVIDO: Ya no validamos si el carnet está en uso
                // Ahora múltiples visitas pueden tener el mismo carnet
            ],
            // Ubicación física (requeridas si no es caso misional)
            'building' => [
                $this->boolean('mission_case') ? 'nullable' : 'required',
                'integer',
                'min:1',
                'max:4'
            ],
            'floor' => [
                $this->boolean('mission_case') ? 'nullable' : 'required',
                'integer',
                'min:1',
                'max:4'
            ],
            'namePersonToVisit' => [
                $this->boolean('mission_case') ? 'nullable' : 'required',
                'string',
                'max:255',
                'min:2',
                // Solo letras, espacios, acentos y algunos caracteres especiales
                'regex:/^[a-zA-ZáéíóúñÁÉÍÓÚÑüÜ\s\.\-\']+$/'
            ],
            'department' => [
                $this->boolean('mission_case') ? 'nullable' : 'required',
                'string',
                'max:255',
                'min:2'
            ],
            'reason' => [
                $this->boolean('mission_case') ? 'nullable' : 'required',
                'string',
                'max:500'
            ],
            'mission_case' => [
                'boolean'
            ],
            'person_to_visit_email' => [
                'nullable',
                'email:rfc,dns',
                'max:255',
                // Solo requerido si send_email es true
                'required_if:send_email,true'
            ],
            'send_email' => [
                'boolean'
            ],
            'visitor_ids' => [
                'required',
                'array',
                'min:1',  // Mínimo 1 visitante
                'max:10'  // Límite máximo de visitantes
            ],
            'visitor_ids.*' => [
                'required',
                'integer',
                'exists:visitors,id',
                function ($attribute, $value, $fail) {
                    if ($this->visitorHasActiveVisit($value)) {
                        $visitor = \App\Models\Visitor::find($value);
                        $name = $visitor ? "{$visitor->name} {$visitor->lastName}" : "Visitante #{$value}";
                        $fail("El visitante {$name} ya tiene una visita activa.");
                    }
                }
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
            'visitor_ids.required' => 'Debe seleccionar al menos un visitante.',
            'visitor_ids.min' => 'Debe seleccionar al menos un visitante.',
            'visitor_ids.max' => 'No puede seleccionar más de 10 visitantes por visita.',
            'person_to_visit_email.email' => 'El formato del email no es válido.',
            'person_to_visit_email.required_if' => 'El email es requerido cuando se marca envío de notificación.',
            'assigned_carnet.min' => 'El carnet debe ser mayor a 0.',
            'assigned_carnet.max' => 'El carnet no puede exceder 9999.',
            'department.min' => 'El departamento debe tener al menos 2 caracteres.',
        ];
    }

    /**
     * Atributos personalizados para errores
     */
    public function attributes(): array
    {
        return [
            'namePersonToVisit' => 'persona a visitar',
            'department' => 'departamento',
            'building' => 'edificio',
            'floor' => 'piso',
            'reason' => 'motivo de la visita',
            'assigned_carnet' => 'carnet asignado',
            'person_to_visit_email' => 'email de la persona a visitar',
            'visitor_ids' => 'visitantes',
            'user_id' => 'usuario'
        ];
    }

    /**
     * Preparar datos para validación
     */
    protected function prepareForValidation(): void
    {
        // Limpiar y formatear datos básicos
        $this->merge([
            'mission_case' => $this->boolean('mission_case'),
            'send_email' => $this->boolean('send_email'),
        ]);

        // Limpiar campos de texto
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

        // Si es caso misional, sobrescribir datos automáticamente
        if ($this->boolean('mission_case')) {
            $this->merge([
                'namePersonToVisit' => 'Unidad de Gestión de Casos',
                'department' => 'Gestión de Casos'
            ]);
        }

        // Limpiar email si se proporciona
        if ($this->has('person_to_visit_email') && $this->person_to_visit_email) {
            $this->merge([
                'person_to_visit_email' => strtolower(trim($this->person_to_visit_email))
            ]);
        }

        // Asegurar que visitor_ids sea array
        if ($this->has('visitor_ids') && !is_array($this->visitor_ids)) {
            $this->merge([
                'visitor_ids' => []
            ]);
        }
    }

    /**
     * Validar después de las reglas estándar
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validación adicional: si send_email es true, debe haber email
            if ($this->boolean('send_email') && empty($this->person_to_visit_email)) {
                $validator->errors()->add(
                    'person_to_visit_email', 
                    'Debe proporcionar un email para enviar la notificación.'
                );
            }

            // Validar que los visitor_ids no tengan duplicados
            $visitorIds = $this->input('visitor_ids', []);
            if (count($visitorIds) !== count(array_unique($visitorIds))) {
                $validator->errors()->add(
                    'visitor_ids', 
                    'No puede seleccionar el mismo visitante múltiples veces.'
                );
            }
        });
    }

    /**
     * Validar que el visitante no tenga visita activa
     */
    private function visitorHasActiveVisit(int $visitorId): bool
    {
        return Visit::where('status_id', EnumVisitStatuses::ABIERTO->value)
                   ->whereHas('visitors', function ($query) use ($visitorId) {
                       $query->where('visitors.id', $visitorId);
                   })
                   ->exists();
    }
}
