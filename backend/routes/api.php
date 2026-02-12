<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MicrosoftAuthController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\VisitorController;
use App\Http\Controllers\VisitController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\Alertas\AlertController;
use App\Http\Controllers\Alertas\CatalogController;
use App\Http\Controllers\DiagnosticsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// ============================================================================
// AUTENTICACIÓN
// ============================================================================

// Login tradicional (se mantiene por compatibilidad)
// Rate limiting: 10 intentos por minuto por IP
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1');

// Autenticación con Microsoft 365 (SSO)
Route::prefix('auth')->group(function () {
    // Rate limiting: 10 intentos por minuto por IP en login
    Route::post('/microsoft-login', [MicrosoftAuthController::class, 'login'])
        ->middleware('throttle:10,1');
    Route::post('/demo-login', [AuthController::class, 'demoLogin'])
        ->middleware('throttle:10,1');
    Route::post('/microsoft-refresh', [MicrosoftAuthController::class, 'refreshWithMicrosoft']);
    
    // Rutas protegidas de autenticación
    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [MicrosoftAuthController::class, 'logout']);
        Route::post('/refresh', [MicrosoftAuthController::class, 'refresh']);
        Route::get('/me', [MicrosoftAuthController::class, 'me']);
    });
});

// ============================================================================
// RUTAS PROTEGIDAS (Requieren autenticación)
// ============================================================================

Route::middleware('auth:api')->group(function () {
    
    // Ruta legacy
    Route::get('/getUser', [AuthController::class, 'getUser']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // ------------------------------------------------------------------------
    // GESTIÓN DE USUARIOS (Solo Admin)
    // ------------------------------------------------------------------------
    Route::prefix('admin/users')->middleware('role:Admin')->group(function () {
        Route::get('/', [UserManagementController::class, 'index']);
        Route::get('/roles', [UserManagementController::class, 'getRoles']);
        // Rate limiting: 30 búsquedas por minuto para evitar abuso
        Route::get('/search-microsoft', [UserManagementController::class, 'searchMicrosoftUsers'])
            ->middleware('throttle:30,1');
        Route::post('/', [UserManagementController::class, 'store']);
        Route::put('/{id}', [UserManagementController::class, 'update']);
        Route::patch('/{id}/toggle-active', [UserManagementController::class, 'toggleActive']);
        Route::delete('/{id}', [UserManagementController::class, 'destroy']);
    });

    // ------------------------------------------------------------------------
    // VISITANTES (Admin, Asist_adm)
    // ------------------------------------------------------------------------

    // ------------------------------------------------------------------------
    // VISITANTES (Admin, Asist_adm)
    // ------------------------------------------------------------------------
    Route::middleware('role:Admin,Asist_adm')->group(function () {
        Route::get('/visitors', [VisitorController::class, 'index']);
        Route::post('/visitors', [VisitorController::class, 'store']);
        Route::get('/visitors/{visitor}', [VisitorController::class, 'show']);
        Route::put('/visitors/{visitor}', [VisitorController::class, 'update']);
        Route::patch('/visitors/{visitor}', [VisitorController::class, 'update']);
        Route::delete('/visitors/{visitor}', [VisitorController::class, 'destroy']);
        Route::get('/visitor/{identity_document}', [VisitorController::class, 'search']);
        
        // Buscar usuarios de Microsoft 365 para campo "Persona a visitar"
        // Rate limiting: 30 búsquedas por minuto
        Route::get('/search-users', [UserManagementController::class, 'searchMicrosoftUsers'])
            ->middleware('throttle:30,1');
    });

    // ------------------------------------------------------------------------
    // VISITAS (Admin, Asist_adm, Guardia, aux_ugc)
    // ------------------------------------------------------------------------
    // Guardia puede ver y cerrar visitas en horario específico
    // aux_ugc puede ver solo visitas activas misionales
    Route::middleware('role:Admin,Asist_adm,Guardia,aux_ugc')->group(function () {
        // Rutas específicas PRIMERO antes de las dinámicas
        Route::get('/visits-active', [VisitController::class, 'getActiveVisits']); // Solo visitas abiertas
        Route::get('/visits-active-mission', [VisitController::class, 'getActiveMissionVisits']); // Solo visitas activas misionales
        Route::get('/visits-active-non-mission', [VisitController::class, 'getActiveNonMissionVisits']); // Solo visitas activas NO misionales
        Route::get('/visits-today', [VisitController::class, 'getTodayVisits']); // Todas las de hoy
        Route::get('/visits-closed-today', [VisitController::class, 'getClosedTodayVisits']); // Cerradas hoy
        
        // Búsqueda
        Route::get('/visits-search', [VisitController::class, 'search']);
        Route::get('/visits-advanced-search', [VisitController::class, 'advancedSearch']);
        
        // CRUD básico
        Route::get('/visits', [VisitController::class, 'index']);
        Route::get('/visits/{id}', [VisitController::class, 'show']);
        
        // Cerrar visitas
        Route::put('/visits/{id}/close', [VisitController::class, 'close']);
        
        // Actualizar placa del vehículo
        Route::patch('/visits/{id}/vehicle', [VisitController::class, 'updateVehiclePlate']);
        
        // Asignar carnet
        Route::post('/visits/{id}/carnet', [VisitController::class, 'assignCarnet']);
        
        // Enviar notificación
        Route::post('/visits/{id}/notification', [VisitController::class, 'sendNotification']);
        
        // Dashboard/Stats
        Route::get('/dashboard/stats', [VisitController::class, 'getDashboardStats']);
        Route::get('/dashboard/stats/mission', [VisitController::class, 'getMissionStatsOnly']);
        Route::get('/dashboard/stats/non-mission', [VisitController::class, 'getNonMissionStatsOnly']);
    });
    
    // Crear/editar visitas (Solo Admin, Asist_adm)
    Route::middleware('role:Admin,Asist_adm')->group(function () {
        Route::post('/visits', [VisitController::class, 'store']);
        Route::put('/visits/{id}', [VisitController::class, 'update']);
        Route::delete('/visits/{id}', [VisitController::class, 'destroy']);
        
        // Exportación
        Route::get('/visits/export/excel', [VisitController::class, 'export']);
        Route::get('/visits-export-excel', [VisitController::class, 'export']);
        Route::get('/visits-export-pdf', [VisitController::class, 'generatePDF']);
        Route::get('/visits/{id}/pdf', [VisitController::class, 'generatePDF']);
        
        // Estadísticas
        Route::get('/visits/statistics', [VisitController::class, 'statistics']);
        
        // Stats avanzadas (7 endpoints) - mantener para compatibilidad
        Route::prefix('stats')->group(function () {
            Route::get('/kpis', [StatsController::class, 'getKPIs']);
            Route::get('/daily', [StatsController::class, 'getDailyTrend']);
            Route::get('/by-department', [StatsController::class, 'getByDepartment']);
            Route::get('/duration', [StatsController::class, 'getAverageDuration']);
            Route::get('/hourly', [StatsController::class, 'getHourlyPeak']);
            Route::get('/weekday-average', [StatsController::class, 'getWeekdayAverage']);
            Route::get('/weekly-compare', [StatsController::class, 'getWeeklyCompare']);
        });
    });

    // ------------------------------------------------------------------------
    // SOLICITUDES DE VISITAS (Solicitante)
    // ------------------------------------------------------------------------
    // TODO: Implementar controlador de solicitudes
    // Route::middleware('role:Solicitante')->group(function () {
    //     Route::post('/visit-requests', [VisitRequestController::class, 'store']);
    //     Route::get('/visit-requests', [VisitRequestController::class, 'index']);
    // });

    // ------------------------------------------------------------------------
    // ALERTAS (Admin, Asist_adm, aux_ugc)
    // ------------------------------------------------------------------------
    Route::middleware('role:Admin,Asist_adm,aux_ugc')->prefix('alertas')->group(function () {
        // Registrar nueva alerta
        Route::post('/', [AlertController::class, 'store']);
        
        // Obtener detalles de una alerta por case_id
        Route::get('/{caseId}', [AlertController::class, 'show']);
        
        // Verificar si una visita ya tiene alerta
        Route::post('/check-status', [AlertController::class, 'checkAlertStatus']);
        
        // Obtener alerta por visit_id y visitor_id
        Route::post('/by-visit', [AlertController::class, 'getByVisit']);
    });

    // ------------------------------------------------------------------------
    // CATÁLOGOS (Admin, Asist_adm, aux_ugc)
    // ------------------------------------------------------------------------
    Route::middleware('role:Admin,Asist_adm,aux_ugc')->prefix('catalogos')->group(function () {
        // Endpoint consolidado (optimizado) - obtiene todos los datos maestros en una petición
        Route::get('/master-data', [CatalogController::class, 'getAllMasterDataConsolidated']);
        
        // Tipos de origen y casos de origen
        Route::get('/tipos-origen', [CatalogController::class, 'getOriginTypes']);
        Route::get('/casos-origen/{typeId}', [CatalogController::class, 'getOriginCasesByType']);
        
        // Tipos de alerta
        Route::get('/tipos-alerta', [CatalogController::class, 'getAlertTypes']);
        
        // Geografía
        Route::get('/provincias', [CatalogController::class, 'getProvinces']);
        Route::get('/municipios', [CatalogController::class, 'getAllMunicipalities']);
        Route::get('/municipios/{provinceId}', [CatalogController::class, 'getMunicipalities']);
        
        // Instituciones y organizaciones
        Route::get('/instituciones-alertas', [CatalogController::class, 'getInstitutionsWhoGiveAlerts']);
        Route::get('/redes-sociales', [CatalogController::class, 'getSocialMediaOrNewChannels']);
        Route::get('/protocolos-institucion', [CatalogController::class, 'getInstitutionalProtocols']);
        
        // Empleados y géneros
        Route::get('/posiciones-empleados', [CatalogController::class, 'getEmployeePositions']);
        Route::get('/generos', [CatalogController::class, 'getGenders']);
        
        // Búsqueda de NNA
        Route::get('/buscar-nna', [CatalogController::class, 'searchNNA']);
    });

    // Test route
    Route::get('/testy', function () {
        return response()->json(['message' => 'Ruta funcionando']);
    });
});

// Health check endpoint
Route::get('/', function () {
    try {
        // Verificar conexión a base de datos
        DB::connection()->getPdo();
        
        return response()->json([
            'status' => 'OK',
            'message' => 'Backend running',
            'timestamp' => now()->toIso8601String()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'ERROR',
            'message' => 'Database connection failed',
            'error' => $e->getMessage(),
            'timestamp' => now()->toIso8601String()
        ], 500);
    }
});

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        
        return response()->json([
            'status' => 'OK',
            'message' => 'Backend running',
            'timestamp' => now()->toIso8601String()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'ERROR',
            'message' => 'Database connection failed',
            'error' => $e->getMessage(),
            'timestamp' => now()->toIso8601String()
        ], 500);
    }
});

// ============================================================================
// DIAGNÓSTICOS (SOLO PARA DEBUGGING EN PRODUCCIÓN)
// ============================================================================
// Route::get('/diagnostics/status', [DiagnosticsController::class, 'status']);
// Route::get('/diagnostics/test-db', [DiagnosticsController::class, 'testDatabase']);
// Route::get('/diagnostics/debug-db', [DiagnosticsController::class, 'debugDatabase']);