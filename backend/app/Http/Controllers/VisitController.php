<?php

namespace App\Http\Controllers;

use App\Http\Requests\Visit\StoreVisitRequest;
use App\Http\Requests\Visit\UpdateVisitRequest;
use App\Http\Requests\Visit\CloseVisitRequest;
use App\Http\Requests\Visit\SearchVisitsRequest;
use App\Http\Resources\VisitResource;
use App\Http\Resources\VisitCollection;
use App\Repositories\Contracts\VisitRepositoryInterface;
use App\Services\VisitService;
use App\Services\ExportService;
use App\Services\LoggerService;
use App\Services\CacheService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controller refactorizado para gestión de visitas
 * 
 * Responsabilidades:
 * - Recibir requests HTTP
 * - Validar mediante Form Requests
 * - Delegar lógica a Services
 * - Transformar respuestas con Resources
 * - Manejar autenticación y autorización
 */
class VisitController extends Controller
{
    public function __construct(
        protected VisitRepositoryInterface $visitRepository,
        protected VisitService $visitService,
        protected ExportService $exportService,
        protected LoggerService $logger,
        protected CacheService $cache
    ) {}

    /**
     * Listar todas las visitas (con paginación)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->input('per_page', 15);
            $page = (int) $request->input('page', 1);

            $filters = $request->only([
                'search',
                'status',
                'status_id',
                'department',
                'date_from',
                'date_to',
                'visitor_name',
                'visitor_carnet',
                'vehicle_plate',
                'created_today',
                'created_this_week',
                'created_this_month',
                'has_vehicle',
                'has_email',
                'visitor_count_min',
                'visitor_count_max',
            ]);

            foreach (['created_today', 'created_this_week', 'created_this_month', 'has_vehicle', 'has_email'] as $booleanKey) {
                if ($request->has($booleanKey)) {
                    $filters[$booleanKey] = filter_var($request->input($booleanKey), FILTER_VALIDATE_BOOLEAN);
                }
            }

            if ($request->filled('visitor_count_min')) {
                $filters['visitor_count_min'] = (int) $request->input('visitor_count_min');
            }

            if ($request->filled('visitor_count_max')) {
                $filters['visitor_count_max'] = (int) $request->input('visitor_count_max');
            }

            $filters['sort_by'] = $request->input('sort_by');
            $filters['sort_direction'] = $request->input('sort_direction');

            $visits = $this->visitRepository->getAllPaginated($filters, $perPage, $page);

            return response()->json(new VisitCollection($visits));

        } catch (\Exception $e) {
            $this->logger->error($e, ['action' => 'index']);
            
            return response()->json([
                'error' => 'Error al obtener visitas'
            ], 500);
        }
    }

    /**
     * Obtener visitas de hoy
     */
    public function getTodayVisits(SearchVisitsRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();
            $filters['date'] = now()->toDateString();
            
            $visits = $this->visitService->searchVisits($filters);

            $this->logger->visit('get_today_visits', null, [
                'count' => $visits->count(),
                'has_search' => isset($filters['search']),
            ]);

            return response()->json(
                VisitResource::collection($visits)
            );

        } catch (\Exception $e) {
            $this->logger->error($e, ['action' => 'getTodayVisits']);
            
            return response()->json([
                'error' => 'Error al obtener visitas de hoy'
            ], 500);
        }
    }

    /**
     * Obtener visitas activas
     * 
     * Devuelve todas las visitas con estado activo (abiertas).
     * Implementa caché de 5 minutos para optimizar rendimiento.
     * Las relaciones se cargan antes del caché para garantizar datos completos.
     */
    public function getActiveVisits(Request $request): JsonResponse
    {
        try {
            $search = $request->input('q');
            
            // Obtener visitas activas del repositorio
            $visits = $this->visitRepository->getActiveVisits($search);

            // Transformar con Resource
            return response()->json(
                VisitResource::collection($visits)
            );

        } catch (\Exception $e) {
            $this->logger->error($e, ['action' => 'getActiveVisits']);
            
            return response()->json([
                'error' => 'Error al obtener visitas activas'
            ], 500);
        }
    }

    /**
     * Obtener visitas activas misionales (solo mission_case = true)
     */
    public function getActiveMissionVisits(Request $request): JsonResponse
    {
        try {
            $search = $request->input('q');
            
            $visits = $this->visitRepository->getActiveMissionVisits($search);

            return response()->json(
                VisitResource::collection($visits)
            );

        } catch (\Exception $e) {
            $this->logger->error($e, ['action' => 'getActiveMissionVisits']);
            
            // SEGURIDAD: No exponer file/line en producción
            return response()->json([
                'error' => 'Error al obtener visitas activas misionales'
            ], 500);
        }
    }

    /**
     * Obtener visitas activas NO misionales (solo mission_case = false o null)
     */
    public function getActiveNonMissionVisits(Request $request): JsonResponse
    {
        try {
            $search = $request->input('q');
            
            $visits = $this->visitRepository->getActiveNonMissionVisits($search);

            return response()->json(
                VisitResource::collection($visits)
            );

        } catch (\Exception $e) {
            $this->logger->error($e, ['action' => 'getActiveNonMissionVisits']);
            
            return response()->json([
                'error' => 'Error al obtener visitas activas no misionales'
            ], 500);
        }
    }

    /**
     * Obtener visitas cerradas de hoy
     */
    public function getClosedTodayVisits(Request $request): JsonResponse
    {
        try {
            $search = $request->input('q');
            
            $visits = $this->visitRepository->getClosedTodayVisits($search);

            $this->logger->visit('get_closed_today_visits', null, [
                'count' => $visits->count(),
                'has_search' => !empty($search),
            ]);

            return response()->json(
                VisitResource::collection($visits)
            );

        } catch (\Exception $e) {
            $this->logger->error($e, ['action' => 'getClosedTodayVisits']);
            
            return response()->json([
                'error' => 'Error al obtener visitas cerradas de hoy'
            ], 500);
        }
    }

    /**
     * Obtener una visita específica
     */
    public function show(int $id): JsonResponse
    {
        try {
            $visit = $this->visitRepository->findWithRelations($id, [
                'user',
                'closedBy',
                'visitStatus',
                'visitors'
            ]);

            if (!$visit) {
                return response()->json([
                    'error' => 'Visita no encontrada'
                ], 404);
            }

            // Verificar autorización
            $this->authorize('view', $visit);

            return response()->json([
                'data' => new VisitResource($visit)
            ]);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'error' => 'No autorizado para ver esta visita'
            ], 403);
            
        } catch (\Exception $e) {
            $this->logger->error($e, [
                'action' => 'show',
                'visit_id' => $id
            ]);
            
            return response()->json([
                'error' => 'Error al obtener visita'
            ], 500);
        }
    }

    /**
     * Crear una nueva visita
     */
    public function store(StoreVisitRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            // Verificar autorización
            $this->authorize('create', \App\Models\Visit::class);

            // Crear visita usando el servicio
            $visit = $this->visitService->createVisit($validated);

            $this->logger->visit('created', $visit->id, [
                'visitor_count' => count($validated['visitors'] ?? []),
                'has_vehicle' => !empty($validated['vehicle_plate']),
            ]);

            return response()->json([
                'message' => 'Visita creada exitosamente',
                'data' => new VisitResource($visit)
            ], 201);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'error' => 'No autorizado para crear visitas'
            ], 403);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            $this->logger->error($e, ['action' => 'store']);
            
            return response()->json([
                'error' => 'Error al crear visita'
            ], 500);
        }
    }

    /**
     * Actualizar una visita
     */
    public function update(UpdateVisitRequest $request, int $id): JsonResponse
    {
        try {
            $visit = $this->visitRepository->findWithRelations($id);

            if (!$visit) {
                return response()->json([
                    'error' => 'Visita no encontrada'
                ], 404);
            }

            // Verificar autorización
            $this->authorize('update', $visit);

            $validated = $request->validated();
            
            // Actualizar usando el servicio
            $visit = $this->visitService->updateVisit($visit, $validated);

            return response()->json([
                'message' => 'Visita actualizada exitosamente',
                'data' => new VisitResource($visit)
            ]);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'error' => 'No autorizado para actualizar esta visita'
            ], 403);
            
        } catch (\Exception $e) {
            $this->logger->error($e, [
                'action' => 'update',
                'visit_id' => $id
            ]);
            
            return response()->json([
                'error' => 'Error al actualizar visita'
            ], 500);
        }
    }

    /**
     * Cerrar una visita
     */
    public function close(CloseVisitRequest $request, int $id): JsonResponse
    {
        try {
            $visit = $this->visitRepository->findWithRelations($id);

            if (!$visit) {
                return response()->json([
                    'error' => 'Visita no encontrada'
                ], 404);
            }

            // Verificar autorización
            $this->authorize('close', $visit);

            $validated = $request->validated();
            
            // Cerrar usando el servicio
            $visit = $this->visitService->closeVisit(
                $id,                  // ID de la visita
                $validated,           // Datos validados (incluye observations si existe)
                $request->user()      // Usuario autenticado
            );

            return response()->json([
                'message' => 'Visita cerrada exitosamente',
                'data' => new VisitResource($visit)
            ]);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'error' => 'No autorizado para cerrar esta visita'
            ], 403);
            
        } catch (\Exception $e) {
            $this->logger->error($e, [
                'action' => 'close',
                'visit_id' => $id
            ]);
            
            return response()->json([
                'error' => 'Error al cerrar visita'
            ], 500);
        }
    }

    /**
     * Actualizar la placa del vehículo de una visita
     */
    public function updateVehiclePlate(Request $request, int $id): JsonResponse
    {
        try {
            $visit = $this->visitRepository->findWithRelations($id);

            if (!$visit) {
                return response()->json([
                    'error' => 'Visita no encontrada'
                ], 404);
            }

            // Validar que solo Admin, Asist_adm, y Guardia puedan actualizar la placa
            $user = $request->user();
            if (!($user->isAdmin() || $user->isAsistAdm() || $user->isGuardia())) {
                return response()->json([
                    'error' => 'No autorizado para actualizar la placa'
                ], 403);
            }

            // Validar entrada (regex permisiva y consistente con los demás puntos de entrada)
            $validated = $request->validate([
                'vehicle_plate' => 'nullable|string|max:20|regex:/^[A-Za-z0-9\-]+$/'
            ]);

            // Actualizar la placa (se mantiene validación permisiva en todo el sistema)
            $visit->update([
                'vehicle_plate' => $validated['vehicle_plate'] ?? null
            ]);

            // Log de la acción
            $this->logger->visit('vehicle_plate_updated', $id, [
                'new_plate' => $validated['vehicle_plate'] ?? null
            ]);

            return response()->json([
                'message' => 'Placa del vehículo actualizada exitosamente',
                'data' => new VisitResource($visit->fresh(['visitors', 'visitStatus', 'creator', 'closer']))
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Datos inválidos',
                'messages' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            $this->logger->error($e, [
                'action' => 'updateVehiclePlate',
                'visit_id' => $id
            ]);
            
            return response()->json([
                'error' => 'Error al actualizar la placa del vehículo'
            ], 500);
        }
    }

    /**
     * Eliminar una visita (soft delete)
     * 
     * PUNTO CIEGO #9: Cascada con soft delete y auditoría
     * PUNTO CIEGO #8: Invalida caché tras cambios
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $visit = $this->visitRepository->findWithRelations($id);

            if (!$visit) {
                return response()->json([
                    'error' => 'Visita no encontrada'
                ], 404);
            }

            // Verificar autorización
            $this->authorize('delete', $visit);

            // Log PRE-eliminación con todos los datos para auditoría
            $this->logger->visit('deleted', $id, [
                'department' => $visit->department,
                'status_was' => $visit->status_id,
                'visitor_count' => $visit->visitors->count(),
                'deleted_by' => $request->user()?->id,
                'soft_delete' => true,
            ]);
            
            $this->visitRepository->delete($id);
            
            // Invalidar caché de visitas activas (PUNTO CIEGO #8)
            $this->cache->forget('visits_active');
            $this->cache->forget('visits_mission_active');
            $this->cache->forget('visits_non_mission_active');

            return response()->json([
                'message' => 'Visita eliminada exitosamente (puede ser restaurada)'
            ]);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'error' => 'No autorizado para eliminar esta visita'
            ], 403);
            
        } catch (\Exception $e) {
            $this->logger->error($e, [
                'action' => 'destroy',
                'visit_id' => $id
            ]);
            
            return response()->json([
                'error' => 'Error al eliminar visita'
            ], 500);
        }
    }

    /**
     * Búsqueda avanzada de visitas
     */
    /**
     * Búsqueda de visitas con paginación
     * 
     * PUNTO CIEGO #4: Implementa paginación para evitar OOM
     */
    public function search(SearchVisitsRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();
            $perPage = min((int)$request->input('per_page', 50), 200); // Max 200 por página
            $page = max(1, (int)$request->input('page', 1));
            
            $visits = $this->visitService->searchVisitsPaginated($filters, $perPage, $page);

            $this->logger->visit('search', null, [
                'filters' => array_keys($filters),
                'total_count' => $visits->total(),
                'current_page' => $page,
            ]);

            return response()->json([
                'data' => VisitResource::collection($visits->items()),
                'pagination' => [
                    'total' => $visits->total(),
                    'per_page' => $visits->perPage(),
                    'current_page' => $visits->currentPage(),
                    'last_page' => $visits->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error($e, ['action' => 'search']);
            
            return response()->json([
                'error' => 'Error en la búsqueda'
            ], 500);
        }
    }

    /**
     * Búsqueda avanzada con múltiples filtros
     * Usado por el dashboard y generador de reportes
     */
    public function advancedSearch(Request $request): JsonResponse
    {
        try {
            $filters = $request->all();
            
            $visits = $this->visitRepository->advancedSearch($filters);

            $this->logger->visit('advanced_search', null, [
                'filters' => array_keys($filters),
                'results_count' => $visits->count(),
            ]);

            return response()->json(
                VisitResource::collection($visits)
            );

        } catch (\Exception $e) {
            $this->logger->error($e, ['action' => 'advancedSearch']);
            
            return response()->json([
                'error' => 'Error en la búsqueda avanzada'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de visitas
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            // Verificar autorización
            $this->authorize('viewStatistics', \App\Models\Visit::class);

            $period = $request->input('period', 'today');
            
            $stats = $this->visitService->getStatistics($period);

            return response()->json([
                'data' => $stats
            ]);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'error' => 'No autorizado para ver estadísticas'
            ], 403);
            
        } catch (\Exception $e) {
            $this->logger->error($e, ['action' => 'statistics']);
            
            return response()->json([
                'error' => 'Error al obtener estadísticas'
            ], 500);
        }
    }

    /**
     * Dashboard quick stats summary.
     */
    public function getDashboardStats(): JsonResponse
    {
        try {
            $stats = $this->visitService->getDashboardStats();

            return response()->json($stats);

        } catch (\Exception $e) {
            $this->logger->error($e, ['action' => 'getDashboardStats']);

            return response()->json([
                'error' => 'Error al obtener estadísticas del dashboard'
            ], 500);
        }
    }

    /**
     * Dashboard stats solo para visitas misionales
     */
    public function getMissionStatsOnly(): JsonResponse
    {
        try {
            $stats = $this->visitService->getMissionStatsOnly();

            return response()->json($stats);

        } catch (\Exception $e) {
            $this->logger->error($e, ['action' => 'getMissionStatsOnly']);

            return response()->json([
                'error' => 'Error al obtener estadísticas de visitas misionales'
            ], 500);
        }
    }

    /**
     * Dashboard stats solo para visitas NO misionales
     */
    public function getNonMissionStatsOnly(): JsonResponse
    {
        try {
            $stats = $this->visitService->getNonMissionStatsOnly();

            return response()->json($stats);

        } catch (\Exception $e) {
            $this->logger->error($e, ['action' => 'getNonMissionStatsOnly']);

            return response()->json([
                'error' => 'Error al obtener estadísticas de visitas no misionales'
            ], 500);
        }
    }

    /**
     * Exportar visitas a Excel
     */
    public function export(Request $request)
    {
        try {
            // Verificar autorización
            $this->authorize('export', \App\Models\Visit::class);

            $filters = $request->all();
            
            $this->logger->export('visits_excel', 0, $filters);

            $result = $this->exportService->exportToExcel($filters);
            
            // Retornar el archivo Excel
            $file = file_get_contents($result['filepath']);
            return response()->make($file, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $result['filename'] . '"'
            ]);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'error' => 'No autorizado para exportar'
            ], 403);
            
        } catch (\Exception $e) {
            $this->logger->error($e, ['action' => 'export']);
            
            return response()->json([
                'error' => 'Error al exportar visitas'
            ], 500);
        }
    }

    /**
     * Generar PDF de visitas con filtros
     */
    public function generatePDF(Request $request)
    {
        try {
            $this->authorize('export', \App\Models\Visit::class);

            $filters = $request->all();
            
            $this->logger->export('visits_pdf', 0, $filters);

            $result = $this->exportService->exportToPDF($filters);
            
            // Retornar el PDF
            return $result['pdf']->download($result['filename']);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'error' => 'No autorizado para exportar'
            ], 403);
            
        } catch (\Exception $e) {
            $this->logger->error($e, ['action' => 'generatePDF']);
            
            return response()->json([
                'error' => 'Error al generar PDF'
            ], 500);
        }
    }

    /**
     * Asignar carnet a visita
     */
    public function assignCarnet(Request $request, int $id): JsonResponse
    {
        try {
            $visit = $this->visitRepository->findWithRelations($id);

            if (!$visit) {
                return response()->json([
                    'error' => 'Visita no encontrada'
                ], 404);
            }

            // Verificar autorización
            $this->authorize('assignCarnet', \App\Models\Visit::class);

            $request->validate([
                'carnet_number' => 'required|integer|min:1'
            ]);

            $visit = $this->visitService->assignCarnet(
                $visit,
                $request->input('carnet_number')
            );

            return response()->json([
                'message' => 'Carnet asignado exitosamente',
                'data' => new VisitResource($visit)
            ]);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'error' => 'No autorizado para asignar carnets'
            ], 403);
            
        } catch (\Exception $e) {
            $this->logger->error($e, [
                'action' => 'assignCarnet',
                'visit_id' => $id
            ]);
            
            return response()->json([
                'error' => 'Error al asignar carnet'
            ], 500);
        }
    }

    /**
     * Enviar notificación de visita
     */
    public function sendNotification(int $id): JsonResponse
    {
        try {
            $visit = $this->visitRepository->findWithRelations($id, [
                'visitors',
                'user'
            ]);

            if (!$visit) {
                return response()->json([
                    'error' => 'Visita no encontrada'
                ], 404);
            }

            // Verificar autorización
            $this->authorize('sendNotification', [$visit, $visit]);

            $result = $this->visitService->sendVisitNotification($visit);

            if ($result['success']) {
                return response()->json([
                    'message' => 'Notificación enviada exitosamente'
                ]);
            } else {
                return response()->json([
                    'error' => 'Error al enviar notificación',
                    'message' => $result['message'] ?? 'Error desconocido'
                ], 500);
            }

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'error' => 'No autorizado para enviar notificaciones'
            ], 403);
            
        } catch (\Exception $e) {
            $this->logger->error($e, [
                'action' => 'sendNotification',
                'visit_id' => $id
            ]);
            
            return response()->json([
                'error' => 'Error al enviar notificación'
            ], 500);
        }
    }
}
