<?php

namespace App\Http\Requests\Alertas;

use Illuminate\Foundation\Http\FormRequest;

class StoreAlertRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Solo usuarios autenticados pueden crear alertas.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     * 
    * Nueva estructura basada en el sistema externo de alertas:
     * {
     *   alert_detail: { formData },
     *   nna_list: [...],
     *   related_entities: [...]
     * }
     */
    public function rules(): array
    {
        return [
            // Detalles de la alerta (formData)
            'alert_detail' => 'required|array',
            'alert_detail.visit_id' => 'required|integer|exists:visits,id',
            'alert_detail.visitor_id' => 'required|integer|exists:visitors,id',
            'alert_detail.type_origin_case_id' => 'required|integer',
            'alert_detail.origin_case_id' => 'required|integer',
            'alert_detail.alert_type_id' => 'required|integer',
            'alert_detail.receiver_departament' => 'nullable|string|max:255',
            'alert_detail.province_id' => 'required|integer',
            'alert_detail.municipality_id' => 'required|integer',
            'alert_detail.description' => 'required|string',
            'alert_detail.media_link' => 'nullable|string|max:500',
            'alert_detail.start_date' => 'required|date',
            'alert_detail.localition_description' => 'nullable|string|max:500',
            'alert_detail.alert_details_option_id' => 'nullable|integer',
            'alert_detail.employee_position_id' => 'nullable|integer',

            // Lista de NNAs
            'nna_list' => 'required|array|min:1',
            'nna_list.*.id' => 'nullable|integer', // Si existe, vincular NNA existente
            'nna_list.*.code' => 'nullable|string|max:50',
            'nna_list.*.name' => 'required|string|max:255',
            'nna_list.*.lastname' => 'required|string|max:255',
            'nna_list.*.gender_id' => 'required|integer',
            'nna_list.*.birth_date' => 'nullable|date',
            'nna_list.*.age' => 'nullable|integer|min:0|max:120',
            'nna_list.*.ageMeasuredIn' => 'nullable|integer',
            'nna_list.*.ageCalculatedBy' => 'nullable|integer',
            'nna_list.*.has_birth_certificate' => 'nullable|integer|in:1,2',

            // Entidades relacionadas
            'related_entities' => 'nullable|array',
            'related_entities.*.name' => 'required|string|max:255',
            'related_entities.*.phone' => 'nullable|string|max:20',
            'related_entities.*.relation_type' => 'required|string|max:100',
            'related_entities.*.description' => 'nullable|string',
            'related_entities.*.employee_position_id' => 'nullable|integer',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'alert_detail.required' => 'Los detalles de la alerta son obligatorios',
            'alert_detail.visit_id.required' => 'El ID de la visita es obligatorio',
            'alert_detail.visit_id.exists' => 'La visita especificada no existe',
            'alert_detail.visitor_id.required' => 'El ID del visitante es obligatorio',
            'alert_detail.visitor_id.exists' => 'El visitante especificado no existe',
            'alert_detail.type_origin_case_id.required' => 'El tipo de origen del caso es obligatorio',
            'alert_detail.origin_case_id.required' => 'El origen del caso es obligatorio',
            'alert_detail.alert_type_id.required' => 'El tipo de alerta es obligatorio',
            'alert_detail.province_id.required' => 'La provincia es obligatoria',
            'alert_detail.municipality_id.required' => 'El municipio es obligatorio',
            'alert_detail.description.required' => 'La descripción de la situación es obligatoria',
            'alert_detail.start_date.required' => 'La fecha de inicio es obligatoria',
            
            'nna_list.required' => 'Debe registrar al menos un NNA',
            'nna_list.min' => 'Debe registrar al menos un NNA',
            'nna_list.*.name.required' => 'El nombre del NNA es obligatorio',
            'nna_list.*.lastname.required' => 'El apellido del NNA es obligatorio',
            'nna_list.*.gender_id.required' => 'El género del NNA es obligatorio',
            
            'related_entities.*.name.required' => 'El nombre de la entidad relacionada es obligatorio',
            'related_entities.*.relation_type.required' => 'El tipo de relación es obligatorio',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'alert_detail.visit_id' => 'visita',
            'alert_detail.visitor_id' => 'visitante',
            'alert_detail.description' => 'descripción de la situación',
            'alert_detail.start_date' => 'fecha de inicio',
            'nna_list.*.name' => 'nombre del NNA',
            'nna_list.*.lastname' => 'apellido del NNA',
            'nna_list.*.gender_id' => 'género',
            'related_entities.*.name' => 'nombre',
            'related_entities.*.relation_type' => 'tipo de relación',
        ];
    }
}
