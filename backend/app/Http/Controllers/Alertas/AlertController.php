<?php

namespace App\Http\Controllers\Alertas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Alertas\StoreAlertRequest;
use App\Services\AlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AlertController extends Controller
{
    protected AlertService $alertService;

    public function __construct(AlertService $alertService)
    {
        $this->alertService = $alertService;
    }

    /**
     * Registrar una nueva alerta
     */
    public function store(StoreAlertRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            // SEGURIDAD: Siempre requerir usuario autenticado
            $userId = auth()->id();
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }
            
            // Extraer visit_id y visitor_id del alert_detail
            $visitId = $validated['alert_detail']['visit_id'];
            $visitorId = $validated['alert_detail']['visitor_id'];
            
            $result = $this->alertService->registerAlert(
                $validated,
                $visitId,
                $visitorId,
                $userId
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => [
                        'case_id' => $result['case_id']
                    ]
                ], 201);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 500);

        } catch (\Exception $e) {
            Log::error('Error en AlertController@store', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener detalles de una alerta por case_id
     */
    public function show(int $caseId): JsonResponse
    {
        try {
            $alertDetails = $this->alertService->getAlertDetails($caseId);

            if (!$alertDetails) {
                return response()->json([
                    'success' => false,
                    'message' => 'Alerta no encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $alertDetails
            ]);

        } catch (\Exception $e) {
            Log::error('Error en AlertController@show', [
                'case_id' => $caseId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los detalles de la alerta'
            ], 500);
        }
    }

    /**
     * Verificar si una visita ya tiene alerta registrada
     */
    public function checkAlertStatus(Request $request): JsonResponse
    {
        $request->validate([
            'visit_id' => 'required|integer|exists:visits,id',
            'visitor_id' => 'required|integer|exists:visitors,id'
        ]);

        try {
            $hasAlert = $this->alertService->hasAlertRegistered(
                $request->visit_id,
                $request->visitor_id
            );

            $caseId = null;
            if ($hasAlert) {
                $caseId = $this->alertService->getCaseIdForVisit(
                    $request->visit_id,
                    $request->visitor_id
                );
            }

            return response()->json([
                'success' => true,
                'has_alert' => $hasAlert,
                'case_id' => $caseId
            ]);

        } catch (\Exception $e) {
            Log::error('Error en AlertController@checkAlertStatus', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al verificar el estado de la alerta'
            ], 500);
        }
    }

    /**
     * Obtener alerta por visit_id y visitor_id
     */
    public function getByVisit(Request $request): JsonResponse
    {
        $request->validate([
            'visit_id' => 'required|integer|exists:visits,id',
            'visitor_id' => 'required|integer|exists:visitors,id'
        ]);

        try {
            $caseId = $this->alertService->getCaseIdForVisit(
                $request->visit_id,
                $request->visitor_id
            );

            if (!$caseId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró alerta registrada para esta visita'
                ], 404);
            }

            $alertDetails = $this->alertService->getAlertDetails($caseId);

            return response()->json([
                'success' => true,
                'data' => $alertDetails
            ]);

        } catch (\Exception $e) {
            Log::error('Error en AlertController@getByVisit', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la alerta de la visita'
            ], 500);
        }
    }
}
