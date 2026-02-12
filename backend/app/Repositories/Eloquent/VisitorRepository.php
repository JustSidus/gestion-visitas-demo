<?php

namespace App\Repositories\Eloquent;

use App\Models\Visitor;
use App\Repositories\Contracts\VisitorRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Implementación Eloquent del repositorio de visitantes
 */
class VisitorRepository implements VisitorRepositoryInterface
{
    /**
     * Obtiene todos los visitantes
     */
    public function getAll(): Collection
    {
        return Visitor::orderBy('name')->get();
    }

    /**
     * Encuentra un visitante por ID
     */
    public function findById(int $id): ?Visitor
    {
        return Visitor::find($id);
    }

    /**
     * Encuentra un visitante por documento de identidad
     * 
     * PUNTO CIEGO #3: Maneja correctamente búsquedas NULL para visitantes sin identificación
     * Evita que "WHERE identity_document = NULL" no encuentre registros NULL
     * 
     * @param string|null $identityDocument Documento de identidad o null
     * @return Visitor|null
     */
    public function findByIdentityDocument(?string $identityDocument): ?Visitor
    {
        if ($identityDocument === null || $identityDocument === '') {
            return Visitor::whereNull('identity_document')->first();
        }
        return Visitor::where('identity_document', $identityDocument)->first();
    }

    /**
     * Busca visitantes por nombre o apellido
     * 
     * PUNTO CIEGO #4: Limita resultados para evitar OOM (Out of Memory)
     * Búsquedas masivas sin límite pueden crashear el servidor
     * 
     * @param string $search Término de búsqueda
     * @param int $limit Máximo de resultados (default 100)
     * @return Collection
     */
    public function searchByName(string $search, int $limit = 100): Collection
    {
        return Visitor::where('name', 'like', "%{$search}%")
            ->orWhere('lastName', 'like', "%{$search}%")
            ->limit($limit)
            ->orderBy('name')
            ->get();
    }

    /**
     * Crea un nuevo visitante
     */
    public function create(array $data): Visitor
    {
        return Visitor::create($data);
    }

    /**
     * Actualiza un visitante existente
     */
    public function update(int $id, array $data): Visitor
    {
        $visitor = Visitor::findOrFail($id);
        $visitor->update($data);
        return $visitor->fresh();
    }

    /**
     * Elimina un visitante
     */
    public function delete(int $id): bool
    {
        $visitor = Visitor::findOrFail($id);
        return $visitor->delete();
    }

    /**
     * Verifica si un visitante tiene visitas activas
     */
    public function hasActiveVisits(int $id): bool
    {
        return Visitor::find($id)
            ->visits()
            ->where('status_id', 1) // ABIERTO
            ->exists();
    }

    /**
     * Obtiene el historial de visitas de un visitante
     */
    public function getVisitHistory(int $id): Collection
    {
        return Visitor::find($id)
            ->visits()
            ->with(['user:id,name', 'status:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Obtener estadísticas generales de visitantes
     */
    public function getStatistics(): array
    {
        $totalVisitors = Visitor::count();
        $withPhone = Visitor::whereNotNull('phone')->count();
        $withEmail = Visitor::whereNotNull('email')->count();
        $withCompany = Visitor::whereNotNull('company')->count();
        
        $recentVisitors = Visitor::where('created_at', '>=', now()->subWeek())->count();
        
        $frequentVisitors = Visitor::withCount('visits')
            ->having('visits_count', '>', 5)
            ->count();

        return [
            'total_visitors' => $totalVisitors,
            'with_phone' => $withPhone,
            'with_email' => $withEmail,
            'with_company' => $withCompany,
            'recent_visitors' => $recentVisitors,
            'frequent_visitors' => $frequentVisitors,
            'completion_rate' => $totalVisitors > 0 ? 
                round((($withPhone + $withEmail + $withCompany) / ($totalVisitors * 3)) * 100, 2) : 0
        ];
    }

    /**
     * Obtener visitantes frecuentes
     */
    public function getFrequentVisitors(int $limit = 10): array
    {
        return Visitor::withCount('visits')
            ->having('visits_count', '>', 3)
            ->orderByDesc('visits_count')
            ->limit($limit)
            ->get()
            ->map(function($visitor) {
                return [
                    'id' => $visitor->id,
                    'name' => $visitor->name,
                    'carnet' => $visitor->carnet,
                    'company' => $visitor->company,
                    'visits_count' => $visitor->visits_count,
                    'last_visit' => $visitor->visits()->latest()->first()?->created_at?->format('d/m/Y')
                ];
            })
            ->toArray();
    }
}