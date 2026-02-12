<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Gate;
use App\Enums\EnumVisitStatuses;

/**
 * Resource para transformar datos de Visit
 * 
 * Responsabilidades:
 * - Formatear salida consistente de datos de visita
 * - Incluir relaciones según contexto
 * - Optimizar datos enviados al frontend
 * - Aplicar transformaciones de formato
 */
class VisitResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            
            // Información básica de la visita
            'namePersonToVisit' => $this->namePersonToVisit,
            'department' => $this->department,
            'building' => $this->building,
            'floor' => $this->floor,
            'reason' => $this->reason,
            'vehicle_plate' => $this->vehicle_plate,
            'assigned_carnet' => $this->assigned_carnet,
            'person_to_visit_email' => $this->person_to_visit_email,
            'send_email' => (bool) $this->send_email,  // Indica si se intentó enviar email
            'mission_case' => (bool) $this->mission_case,
            
            // Estado de la visita
            'status_id' => $this->status_id,
            'is_active' => $this->status_id === EnumVisitStatuses::ABIERTO->value,
            'status' => $this->whenLoaded('visitStatus', function() {
                return [
                    'id' => $this->visitStatus?->id,
                    'name' => $this->visitStatus?->name,
                    'status' => $this->status_id === EnumVisitStatuses::ABIERTO->value ? 'active' : 'closed'
                ];
            }),
            
            // Fechas formateadas
            'created_at_raw' => $this->created_at ? \Carbon\Carbon::parse($this->created_at)->toDateTimeString() : null,
            'created_at' => $this->created_at ? [
                'raw' => \Carbon\Carbon::parse($this->created_at)->toDateTimeString(),
                'formatted' => \Carbon\Carbon::parse($this->created_at)->format('d/m/Y H:i:s'),
                'date' => \Carbon\Carbon::parse($this->created_at)->format('d/m/Y'),
                'time' => \Carbon\Carbon::parse($this->created_at)->format('H:i:s'),
                'human' => \Carbon\Carbon::parse($this->created_at)->diffForHumans()
            ] : null,
            'end_at_raw' => $this->end_at ? \Carbon\Carbon::parse($this->end_at)->toDateTimeString() : null,
            'end_at' => $this->end_at ? [
                'raw' => \Carbon\Carbon::parse($this->end_at)->toDateTimeString(),
                'formatted' => \Carbon\Carbon::parse($this->end_at)->format('d/m/Y H:i:s'),
                'date' => \Carbon\Carbon::parse($this->end_at)->format('d/m/Y'),
                'time' => \Carbon\Carbon::parse($this->end_at)->format('H:i:s'),
                'human' => \Carbon\Carbon::parse($this->end_at)->diffForHumans()
            ] : null,
            'updated_at' => $this->updated_at ? [
                'raw' => \Carbon\Carbon::parse($this->updated_at)->toDateTimeString(),
                'formatted' => \Carbon\Carbon::parse($this->updated_at)->format('d/m/Y H:i:s'),
                'human' => \Carbon\Carbon::parse($this->updated_at)->diffForHumans()
            ] : null,
            
            // Duración de la visita
            'duration' => $this->when($this->end_at && $this->created_at, function() {
                $createdAt = \Carbon\Carbon::parse($this->created_at);
                $closedAt = \Carbon\Carbon::parse($this->end_at);
                return [
                    'minutes' => $createdAt->diffInMinutes($closedAt),
                    'hours' => $createdAt->diffInHours($closedAt),
                    'human' => $createdAt->diffForHumans($closedAt, true)
                ];
            }),
            
            // Usuario que creó la visita
            'created_by' => $this->whenLoaded('creator', function() {
                return [
                    'id' => $this->creator?->id,
                    'name' => $this->creator?->name,
                    'email' => $this->creator?->email,
                    'roles' => $this->creator?->roles->pluck('name')->toArray() ?? []
                ];
            }),
            
            // Usuario que cerró la visita
            'closed_by' => $this->whenLoaded('closer', function() {
                return $this->when($this->closed_by, [
                    'id' => $this->closer?->id,
                    'name' => $this->closer?->name,
                    'email' => $this->closer?->email,
                    'roles' => $this->closer?->roles->pluck('name')->toArray() ?? []
                ]);
            }),
            
            // Visitantes asociados
            'visitors' => VisitorResource::collection($this->whenLoaded('visitors')),
            'visitors_count' => $this->when(
                $this->relationLoaded('visitors'),
                $this->visitors?->count() ?? 0
            ),
            
            // Información adicional calculada
            'has_vehicle' => !empty($this->vehicle_plate),
            'has_email' => !empty($this->person_to_visit_email),
            'is_recent' => $this->created_at?->isAfter(now()->subHours(2)),
            'is_long_duration' => $this->when($this->end_at, function() {
                return $this->created_at->diffInHours($this->end_at) > 8;
            }),
            'has_alert' => $this->whenLoaded('visitors', function() {
                // Verificar si algún visitante tiene case_id en el pivot
                return $this->visitors->contains(function($visitor) {
                    return !empty($visitor->pivot->case_id);
                });
            }),
            
            // Metadatos para el frontend
            'can_edit' => $this->when($request->user(), function() {
                return Gate::allows('update', $this->resource);
            }),
            'can_close' => $this->when($request->user(), function() {
                return Gate::allows('close', $this->resource);
            }),
            
            // Enlaces relacionados (HATEOAS)
            'links' => [
                'self' => Route::has('visits.show') ? route('visits.show', $this->id) : null,
                'update' => Route::has('visits.update') ? route('visits.update', $this->id) : null,
                'close' => Route::has('visits.close') ? route('visits.close', $this->id) : null,
                'visitors' => Route::has('visits.visitors') ? route('visits.visitors', $this->id) : null,
            ]
        ];
    }

    /**
     * Customize the outgoing response for the resource.
     */
    public function withResponse(Request $request, $response): void
    {
        // Agregar headers personalizados si es necesario
        $response->header('X-Visit-Version', '1.0');
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
}