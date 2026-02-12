<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Alertas\CaseAlert;
use App\Models\Alertas\Nna;
use App\Models\Alertas\CaseAlertDetail;
use App\Models\Alertas\AlertRelatedEntity;
use Exception;

/**
 * Servicio para gestionar el registro de alertas
 * Maneja transacciones duales entre gestion_visitas y alerts_db
 */
class AlertService
{
    /**
     * Registrar una alerta completa con NNAs y entidades relacionadas
     * 
     * Nueva estructura:
     * {
     *   alert_detail: { formData },
     *   nna_list: [...],
     *   related_entities: [...]
     * }
     * 
     * @param array $data Datos de la alerta
     * @param int $visitId ID de la visita
     * @param int $visitorId ID del visitante
     * @param int $userId ID del usuario que registra
     * @return array ['success' => bool, 'case_id' => int|null, 'message' => string]
     */
    public function registerAlert(array $data, int $visitId, int $visitorId, int $userId): array
    {
        // Iniciar transacciones en ambas bases de datos
        DB::connection('mysql')->beginTransaction();
        DB::connection('alerts_db')->beginTransaction();

        try {
            $alertDetail = $data['alert_detail'];
            
            // 1. Crear el caso en el sistema externo de alertas
            $caseId = $this->createCaseFromAlert($alertDetail, $userId);

            // 2. Crear o vincular NNAs
            $nnaIds = $this->processNnas($data['nna_list'] ?? [], $caseId, $userId);

            // 3. Crear detalles de alerta
            $alertDetailId = $this->createAlertDetailsFromData($alertDetail, $caseId, $userId);

            // 4. Crear entidades relacionadas
            $this->createRelatedEntitiesFromData($data['related_entities'] ?? [], $alertDetailId, $userId);

            // 5. Vincular el caso con la visita en visit_visitor
            $this->linkCaseToVisit($visitId, $visitorId, $caseId);

            // Commit en ambas bases de datos
            DB::connection('alerts_db')->commit();
            DB::connection('mysql')->commit();

            return [
                'success' => true,
                'case_id' => $caseId,
                'message' => 'Alerta registrada exitosamente'
            ];

        } catch (Exception $e) {
            // Rollback en ambas bases de datos
            DB::connection('alerts_db')->rollBack();
            DB::connection('mysql')->rollBack();

            Log::error('Error al registrar alerta', [
                'error' => $e->getMessage(),
                'visit_id' => $visitId,
                'visitor_id' => $visitorId,
            ]);

            return [
                'success' => false,
                'case_id' => null,
                'message' => 'Error al registrar la alerta: ' . $e->getMessage()
            ];
        }
    }

    /**
    * Crear el caso en la tabla cases del sistema externo desde alert_detail
     */
    private function createCaseFromAlert(array $alertDetail, int $userId)
    {
        return DB::connection('alerts_db')->table('cases')->insertGetId([
            'OriginCaseId' => $alertDetail['origin_case_id'],
            'DepartamentoRecibe' => $alertDetail['receiver_departament'] ?? null,
            'municipality_id' => $alertDetail['municipality_id'],
            'status_id' => 1, // Estado inicial: Recepción
            'start_date' => $alertDetail['start_date'],
            'description' => $alertDetail['description'],
            'previous_situation_description' => $alertDetail['localition_description'] ?? null,
            'user_create_id' => $userId,
            'user_update_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Procesar NNAs: crear nuevos o vincular existentes
     * Nueva estructura de nna_list
     */
    private function processNnas(array $nnasList, int $caseId, int $userId): array
    {
        $nnaIds = [];

        foreach ($nnasList as $nnaData) {
            // Si viene un ID, vincular NNA existente
            if (!empty($nnaData['id'])) {
                $nna = DB::connection('alerts_db')
                    ->table('nna')
                    ->where('id', $nnaData['id'])
                    ->first();
                    
                if ($nna) {
                    $nnaIds[] = $nna->id;
                    continue;
                }
            }

            // Si no existe, crear nuevo NNA
            $nnaId = DB::connection('alerts_db')->table('nna')->insertGetId([
                'name' => $nnaData['name'],
                'surname' => $nnaData['lastname'],
                'gender_id' => $nnaData['gender_id'],
                'birth_date' => $nnaData['birth_date'] ?? null,
                'age' => $nnaData['age'] ?? null,
                'ageMeasuredIn' => $nnaData['ageMeasuredIn'] ?? null,
                'ageCalculatedBy' => $nnaData['ageCalculatedBy'] ?? null,
                'user_create_id' => $userId,
                'user_update_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $nnaIds[] = $nnaId;
        }

        // Vincular todos los NNAs con el caso en la tabla case_nna
        if (!empty($nnaIds)) {
            foreach ($nnaIds as $nnaId) {
                DB::connection('alerts_db')
                    ->table('case_nna')
                    ->insert([
                        'case_id' => $caseId,
                        'nna_id' => $nnaId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            }
        }

        return $nnaIds;
    }

    /**
     * Crear detalles de alerta desde alert_detail
     * @return int ID del registro creado en case_alert_details
     */
    private function createAlertDetailsFromData(array $alertDetail, int $caseId, int $userId): int
    {
        return DB::connection('alerts_db')->table('case_alert_details')->insertGetId([
            'case_id' => $caseId,
            'alert_type_id' => $alertDetail['alert_type_id'],
            'alert_details_option_id' => $alertDetail['alert_details_option_id'] ?? null,
            'media_link' => $alertDetail['media_link'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Crear entidades relacionadas desde related_entities
     * @param int $alertDetailId ID del registro en case_alert_details
     */
    private function createRelatedEntitiesFromData(array $entitiesData, int $alertDetailId, int $userId): void
    {
        foreach ($entitiesData as $entity) {
            DB::connection('alerts_db')->table('alert_related_entities')->insert([
                'case_alert_detail_id' => $alertDetailId,
                'name' => $entity['name'],
                'phone' => $this->normalizePhone($entity['phone'] ?? null),
                'relation_type' => $entity['relation_type'],
                'description' => $entity['description'] ?? null,
                'employee_position_id' => $entity['employee_position_id'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Normaliza el teléfono al formato 000-000-0000 si es posible
     */
    private function normalizePhone(?string $phone): ?string
    {
        if (!$phone) return null;

        // Quitar todo excepto dígitos
        $digits = preg_replace('/[^0-9]/', '', $phone);

        if (!$digits) return null;

        // Limitar a 10 dígitos
        $digits = substr($digits, 0, 10);

        if (strlen($digits) <= 3) return $digits;
        if (strlen($digits) <= 6) {
            return substr($digits, 0, 3) . '-' . substr($digits, 3);
        }

        return substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6);
    }

    /**
     * Vincular el caso con la visita en la tabla visit_visitor
     */
    private function linkCaseToVisit(int $visitId, int $visitorId, int $caseId): void
    {
        DB::connection('mysql')
            ->table('visit_visitor')
            ->where('visit_id', $visitId)
            ->where('visitor_id', $visitorId)
            ->update([
                'case_id' => $caseId,
                'updated_at' => now()
            ]);
    }

    /**
     * Obtener información completa de una alerta por case_id
     */
    public function getAlertDetails(int $caseId): ?array
    {
        try {
            $case = CaseAlert::with([
                'nnas',
                'alertDetails',
                'relatedEntities'
            ])->find($caseId);

            if (!$case) {
                return null;
            }

            return [
                'case' => $case->toArray(),
                'nnas' => $case->nnas->toArray(),
                'alert_details' => $case->alertDetails->toArray(),
                'related_entities' => $case->relatedEntities->toArray(),
            ];

        } catch (Exception $e) {
            Log::error('Error al obtener detalles de alerta', [
                'case_id' => $caseId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Verificar si una visita ya tiene alerta registrada
     */
    public function hasAlertRegistered(int $visitId, int $visitorId): bool
    {
        $pivot = DB::connection('mysql')
            ->table('visit_visitor')
            ->where('visit_id', $visitId)
            ->where('visitor_id', $visitorId)
            ->first();

        return $pivot && $pivot->case_id !== null;
    }

    /**
     * Obtener case_id asociado a una visita y visitante
     */
    public function getCaseIdForVisit(int $visitId, int $visitorId): ?int
    {
        $pivot = DB::connection('mysql')
            ->table('visit_visitor')
            ->where('visit_id', $visitId)
            ->where('visitor_id', $visitorId)
            ->first();

        return $pivot?->case_id;
    }
}
