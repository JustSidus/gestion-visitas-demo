<?php

namespace App\Repositories\Contracts;

use App\Models\Visit;
use Illuminate\Database\Eloquent\Collection;

/**
 * Contrato para el repositorio de visitas
 * 
 * Define todas las operaciones de acceso a datos para visitas.
 * Esto permite intercambiar implementaciones sin afectar el código que usa el repositorio.
 */
interface VisitRepositoryInterface
{
    /**
     * Obtiene todas las visitas con relaciones optimizadas
     *
     * @param array $relations Relaciones adicionales a cargar
     * @param int|null $limit Límite opcional de resultados
     * @return Collection
     */
    public function getAllWithRelations(array $relations = [], ?int $limit = null): Collection;

    /**
     * Obtiene visitas de hoy con filtros opcionales
     * 
     * @param string|null $search Término de búsqueda para filtrar por visitantes
     * @return Collection
     */
    public function getTodayVisits(?string $search = null): Collection;

    /**
     * Obtiene visitas activas (abiertas) con filtros opcionales
     * 
     * @param string|null $search Término de búsqueda para filtrar por visitantes
     * @return Collection
     */
    public function getActiveVisits(?string $search = null): Collection;

    /**
     * Obtiene visitas activas misionales (abiertas y mission_case = true)
     * 
     * @param string|null $search Término de búsqueda para filtrar por visitantes
     * @return Collection
     */
    public function getActiveMissionVisits(?string $search = null): Collection;

    /**
     * Obtiene visitas activas NO misionales (abiertas y mission_case = false)
     * 
     * @param string|null $search Término de búsqueda para filtrar por visitantes
     * @return Collection
     */
    public function getActiveNonMissionVisits(?string $search = null): Collection;

    /**
     * Obtiene visitas cerradas de hoy con filtros opcionales
     * 
     * @param string|null $search Término de búsqueda para filtrar por visitantes
     * @return Collection
     */
    public function getClosedTodayVisits(?string $search = null): Collection;

    /**
     * Búsqueda avanzada con múltiples filtros
     * 
     * @param array $filters Filtros a aplicar (fechas, estado, departamento, etc.)
     * @return Collection
     */
    public function advancedSearch(array $filters): Collection;

    /**
     * Obtiene estadísticas para el dashboard
     * 
     * @return array Estadísticas calculadas
     */
    public function getDashboardStats(): array;

    /**
     * Obtiene estadísticas del dashboard solo para visitas misionales
     * 
     * @return array Estadísticas calculadas (solo misionales)
     */
    public function getMissionStatsOnly(): array;

    /**
     * Obtiene estadísticas del dashboard solo para visitas NO misionales
     * 
     * @return array Estadísticas calculadas (solo no misionales)
     */
    public function getNonMissionStatsOnly(): array;

    /**
     * Encuentra una visita por ID con relaciones cargadas
     *
     * @param int $id ID de la visita
     * @param array $relations Relaciones adicionales a cargar
     * @return Visit|null
     */
    public function findWithRelations(int $id, array $relations = []): ?Visit;

    /**
     * Crea una nueva visita
     * 
     * @param array $data Datos de la visita
     * @return Visit
     */
    public function create(array $data): Visit;

    /**
     * Actualiza una visita existente
     * 
     * @param int $id ID de la visita
     * @param array $data Datos a actualizar
     * @return Visit
     */
    public function update(int $id, array $data): Visit;

    /**
     * Cierra una visita
     * 
     * @param int $id ID de la visita
     * @param array $data Datos adicionales (placa, usuario que cierra, etc.)
     * @return Visit
     */
    public function closeVisit(int $id, array $data = []): Visit;

    /**
     * Elimina una visita
     * 
     * @param int $id ID de la visita
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Verifica si un carnet está en uso por una visita activa
     * 
     * @param int $carnet Número de carnet
     * @return bool
     */
    public function isCarnetInUse(int $carnet): bool;

    /**
     * Verifica si un visitante tiene una visita activa
     * 
     * @param int $visitorId ID del visitante
     * @return bool
     */
    public function visitorHasActiveVisit(int $visitorId): bool;

    /**
     * Busca visitas por documento de identidad del visitante
     * 
     * @param string $identityDocument Documento de identidad
     * @return Collection
     */
    public function findByVisitorIdentity(string $identityDocument): Collection;

    /**
     * Obtiene visitas filtradas por rango de fechas
     * 
     * @param string $filter Filtro de fecha (today, this_week, last_week, etc.)
     * @return Collection
     */
    public function getVisitsByDateFilter(string $filter): Collection;

    /**
     * Obtener estadísticas generales de visitas
     */
    public function getStatistics(): array;

    /**
     * Obtener departamentos únicos
     */
    public function getUniqueDepartments(): array;

    /**
     * Obtener visitas del día actual
     */
    public function getVisitsToday();

    /**
     * Obtener visitas cerradas hoy
     */
    public function getClosedToday();

    /**
     * Obtener top departamentos del día
     */
    public function getTopDepartmentsToday(int $limit = 5): array;

    /**
     * Obtener horas más ocupadas
     */
    public function getBusiestHours(): array;

    /**
     * Obtener duración promedio de visitas
     */
    public function getAverageVisitDuration(): float;

    /**
     * Obtener visitas de un mes específico
     */
    public function getVisitsInMonth(int $year, int $month);

    /**
     * Obtener visitantes únicos en un mes
     */
    public function getUniqueVisitorsInMonth(int $year, int $month): int;

    /**
     * Obtener estadísticas de departamentos por mes
     */
    public function getDepartmentStatsForMonth(int $year, int $month): array;

    /**
     * Obtener distribución diaria para un mes
     */
    public function getDailyDistributionForMonth(int $year, int $month): array;

    /**
     * Obtener duración promedio de visitas por mes
     */
    public function getAverageVisitDurationForMonth(int $year, int $month): float;

    /**
     * Obtener horas pico de un mes
     */
    public function getPeakHoursForMonth(int $year, int $month): array;

    /**
     * Obtener todas las visitas con paginación
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15, int $page = 1);

    /**
     * Obtener visitas pasadas con paginación
     */
    public function getPastVisits(int $perPage = 15);

    /**
     * Buscar visitas por término y filtros
     */
    public function search(string $searchTerm, array $filters = []);

    /**
     * Obtener visitas por fecha específica
     */
    public function findByDate(string $date);

    /**
     * Obtener visitas para exportación
     */
    public function getForExport(array $filters = []);
}