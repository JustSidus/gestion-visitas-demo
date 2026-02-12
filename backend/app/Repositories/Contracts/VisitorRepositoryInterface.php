<?php

namespace App\Repositories\Contracts;

use App\Models\Visitor;
use Illuminate\Database\Eloquent\Collection;

/**
 * Contrato para el repositorio de visitantes
 */
interface VisitorRepositoryInterface
{
    /**
     * Obtiene todos los visitantes
     * 
     * @return Collection
     */
    public function getAll(): Collection;

    /**
     * Encuentra un visitante por ID
     * 
     * @param int $id ID del visitante
     * @return Visitor|null
     */
    public function findById(int $id): ?Visitor;

    /**
     * Encuentra un visitante por documento de identidad
     * 
     * @param string $identityDocument Documento de identidad
     * @return Visitor|null
     */
    public function findByIdentityDocument(string $identityDocument): ?Visitor;

    /**
     * Busca visitantes por nombre o apellido
     * 
     * @param string $search Término de búsqueda
     * @return Collection
     */
    public function searchByName(string $search): Collection;

    /**
     * Crea un nuevo visitante
     * 
     * @param array $data Datos del visitante
     * @return Visitor
     */
    public function create(array $data): Visitor;

    /**
     * Actualiza un visitante existente
     * 
     * @param int $id ID del visitante
     * @param array $data Datos a actualizar
     * @return Visitor
     */
    public function update(int $id, array $data): Visitor;

    /**
     * Elimina un visitante
     * 
     * @param int $id ID del visitante
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Verifica si un visitante tiene visitas activas
     * 
     * @param int $id ID del visitante
     * @return bool
     */
    public function hasActiveVisits(int $id): bool;

    /**
     * Obtiene el historial de visitas de un visitante
     * 
     * @param int $id ID del visitante
     * @return Collection
     */
    public function getVisitHistory(int $id): Collection;

    /**
     * Obtener estadísticas generales de visitantes
     */
    public function getStatistics(): array;

    /**
     * Obtener visitantes frecuentes
     */
    public function getFrequentVisitors(int $limit = 10): array;
}