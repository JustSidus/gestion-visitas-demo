<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use App\Enums\EnumVisitStatuses;
use App\Enums\EnumDocumentType;

/**
 * Resource para transformar datos de Visitor
 * 
 * Responsabilidades:
 * - Formatear salida consistente de datos de visitante
 * - Incluir información de visitas cuando sea necesario
 * - Optimizar datos enviados al frontend
 * - Aplicar transformaciones de formato
 */
class VisitorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $router = app('router');

        return [
            'id' => $this->id,
            
            // Información personal
            'name' => $this->name,
            'lastName' => $this->lastName,
            // Información de documento de identidad
            'identity_document' => $this->identity_document,
            'document_type' => $this->when(isset($this->attributes['document_type']), function() {
                try {
                    return $this->attributes['document_type'];
                } catch (\ValueError $e) {
                    return null;
                }
            }),
            'document_type_label' => $this->obtenerEtiquetaTipoDocumento(),
            'carnet' => $this->when(isset($this->attributes['carnet']), $this->attributes['carnet'] ?? null),
            'company' => $this->when(isset($this->attributes['company']), $this->attributes['company'] ?? null),
            'phone' => $this->phone,
            'email' => $this->email,
            'institution' => $this->institution,
            
            // Información del pivot (relación con visita)
            'has_alert' => $this->when($this->pivot, !empty($this->pivot?->case_id)),
            'case_id' => $this->when($this->pivot, $this->pivot?->case_id ?? null),
            
            // Fechas formateadas
            'created_at' => [
                'raw' => $this->created_at,
                'formatted' => $this->created_at?->format('d/m/Y H:i:s'),
                'date' => $this->created_at?->format('d/m/Y'),
                'human' => $this->created_at?->diffForHumans()
            ],
            'updated_at' => [
                'raw' => $this->updated_at,
                'formatted' => $this->updated_at?->format('d/m/Y H:i:s'),
                'human' => $this->updated_at?->diffForHumans()
            ],
            
            // Información adicional calculada
            'has_phone' => !empty($this->phone),
            'has_email' => !empty($this->email),
            'has_company' => !empty($this->institution),
            'is_frequent_visitor' => $this->whenLoaded('visits', function() {
                return $this->visits?->count() > 5;
            }),
            
            // Estadísticas de visitas (solo si se carga la relación)
            'visits_count' => $this->when(
                $this->relationLoaded('visits'),
                $this->visits?->count() ?? 0
            ),
            'last_visit' => $this->whenLoaded('visits', function() {
                $lastVisit = $this->visits?->sortByDesc('created_at')->first();
                return $lastVisit ? [
                    'id' => $lastVisit->id,
                    'date' => $lastVisit->created_at?->format('d/m/Y H:i:s'),
                    'person_to_visit' => $lastVisit->namePersonToVisit,
                    'department' => $lastVisit->department,
                    'human' => $lastVisit->created_at?->diffForHumans()
                ] : null;
            }),
            'active_visits_count' => $this->whenLoaded('visits', function() {
                return $this->visits
                    ?->filter(function($visit) {
                        return $visit->status_id === EnumVisitStatuses::ABIERTO->value;
                    })
                    ->count() ?? 0;
            }),
            
            // Visitas recientes (últimas 5)
            'recent_visits' => $this->whenLoaded('visits', function() {
                return $this->visits?->sortByDesc('created_at')->take(5)->map(function($visit) {
                    return [
                        'id' => $visit->id,
                        'date' => $visit->created_at?->format('d/m/Y H:i:s'),
                        'person_to_visit' => $visit->namePersonToVisit,
                        'department' => $visit->department,
                        'reason' => $visit->reason,
                        'is_active' => $visit->status_id === EnumVisitStatuses::ABIERTO->value,
                        'duration' => $visit->end_at ? 
                            $visit->created_at->diffForHumans($visit->end_at, true) : 
                            $visit->created_at->diffForHumans() . ' (activa)'
                    ];
                })->values();
            }),
            
            // Usuario que creó el visitante
            'created_by' => $this->whenLoaded('creator', function() {
                return [
                    'id' => $this->creator?->id,
                    'name' => $this->creator?->name,
                    'email' => $this->creator?->email
                ];
            }),
            
            // Información de frecuencia de visitas
            'visit_frequency' => $this->whenLoaded('visits', function() {
                $visitsCount = $this->visits?->count() ?? 0;
                
                if ($visitsCount === 0) return 'Sin visitas';
                if ($visitsCount === 1) return 'Primera visita';
                if ($visitsCount <= 3) return 'Visitante ocasional';
                if ($visitsCount <= 10) return 'Visitante regular';
                return 'Visitante frecuente';
            }),
            
            // Departamentos más visitados
            'favorite_departments' => $this->whenLoaded('visits', function() {
                return $this->visits
                    ?->groupBy('department')
                    ->map(function($visits, $department) {
                        return [
                            'department' => $department,
                            'count' => $visits->count(),
                            'last_visit' => $visits->sortByDesc('created_at')->first()?->created_at?->format('d/m/Y')
                        ];
                    })
                    ->sortByDesc('count')
                    ->take(3)
                    ->values();
            }),
            
            // Metadatos para el frontend
            'can_edit' => $this->when($request->user(), function() {
                return Gate::allows('update', $this->resource);
            }),
            'can_delete' => $this->when($request->user(), function() {
                $canDelete = Gate::allows('delete', $this->resource);
                // Respetar restricción de no eliminar si tiene visitas activas
                $hasNoActiveVisits = ! $this->relationLoaded('visits') || ($this->visits
                    ?->every(function($visit) {
                        return $visit->status_id !== EnumVisitStatuses::ABIERTO->value;
                    }) ?? true);
                return $canDelete && $hasNoActiveVisits;
            }),
            
            // Enlaces relacionados (HATEOAS)
            'links' => [
                'self' => $router->has('visitors.show') ? route('visitors.show', $this->id) : null,
                'update' => $router->has('visitors.update') ? route('visitors.update', $this->id) : null,
                'visits' => $router->has('visitors.visits') ? route('visitors.visits', $this->id) : null,
            ]
        ];
    }

    /**
     * Customize the outgoing response for the resource.
     */
    public function withResponse(Request $request, $response): void
    {
        // Agregar headers personalizados si es necesario
        $response->header('X-Visitor-Version', '1.0');
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'generated_at' => now()->toISOString()
            ]
        ];
    }

    /**
     * Obtiene la etiqueta legible del tipo de documento
     * 
     * Utiliza el Enum EnumDocumentType para una transformación segura y mantenible
     * Evita conflictos con el accessor del modelo accediendo directamente a attributes
     * 
     * @return string Etiqueta del tipo de documento o 'No capturado'
     */
    private function obtenerEtiquetaTipoDocumento(): string
    {
        // Obtener el valor bruto sin pasar por el accessor, de forma segura
        try {
            $tipoDocumento = (int) ($this->resource?->getRawOriginal('document_type') ?? 0);
        } catch (\Exception $e) {
            return 'No capturado';
        }
        
        if ($tipoDocumento === 0) {
            return 'No capturado';
        }

        try {
            // Convertir el valor numérico al Enum
            $tipoEnum = EnumDocumentType::tryFrom($tipoDocumento);
            
            if ($tipoEnum === null) {
                return 'No capturado';
            }

            // Retornar el nombre legible del tipo de documento
            return match ($tipoEnum) {
                EnumDocumentType::CEDULA => 'Cédula',
                EnumDocumentType::PASAPORTE => 'Pasaporte',
                EnumDocumentType::SIN_IDENTIFICACION => 'Sin Identificación',
                default => 'No capturado'
            };
        } catch (\Exception $e) {
            return 'No capturado';
        }
    }
}