<?php

namespace App\Http\Requests\Visit;

use Illuminate\Foundation\Http\FormRequest;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Request para validar cierre de visitas
 * 
 * Responsabilidades:
 * - Validar datos de cierre (placa de vehículo)
 * - Verificar autorización para cerrar
 * - Formatear datos de entrada
 */
class CloseVisitRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para esta acción
     */
    public function authorize(): bool
    {
        try {
            // Política será la fuente de verdad para permisos; aquí solo exigimos usuario autenticado
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
            'vehicle_plate' => [
                'nullable',
                'string',
                'max:20',
                // Patrón permisivo: alfanumérico y guiones
                'regex:/^[A-Za-z0-9\-]+$/'
            ],
            'notes' => [
                'nullable',
                'string',
                'max:500'
            ]
        ];
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'vehicle_plate.regex' => 'La placa solo puede contener letras, números y guiones (-).',
            'vehicle_plate.max' => 'La placa no puede exceder 20 caracteres',
            'notes.max' => 'Las notas no pueden exceder 500 caracteres'
        ];
    }

    /**
     * Atributos personalizados
     */
    public function attributes(): array
    {
        return [
            'vehicle_plate' => 'placa del vehículo',
            'notes' => 'notas adicionales'
        ];
    }

    /**
     * Preparar datos para validación
     */
    protected function prepareForValidation(): void
    {
        // Formatear placa: mayúsculas y sin espacios
        if ($this->has('vehicle_plate') && $this->vehicle_plate) {
            $this->merge([
                'vehicle_plate' => strtoupper(trim(str_replace(' ', '', $this->vehicle_plate)))
            ]);
        }

        // Limpiar notas
        if ($this->has('notes') && $this->notes) {
            $this->merge([
                'notes' => trim($this->notes)
            ]);
        }
    }

    /**
     * Validación adicional después de reglas básicas
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            try {
                $user = JWTAuth::parseToken()->authenticate();
                
                // Validar horario para guardias
                if ($user->roles->contains('name', 'Guardia')) {
                    $currentHour = now()->format('H:i');
                    if ($currentHour < '16:00' || $currentHour > '23:59') {
                        $validator->errors()->add(
                            'authorization', 
                            'Los guardias solo pueden cerrar visitas entre las 4:00 PM y 11:59 PM'
                        );
                    }
                }
                
            } catch (\Exception $e) {
                $validator->errors()->add('authorization', 'Error de autenticación');
            }
        });
    }
}