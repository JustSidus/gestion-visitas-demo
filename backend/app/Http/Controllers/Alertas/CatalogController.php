<?php

namespace App\Http\Controllers\Alertas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    /**
     * Obtener todos los tipos de origen (origin_types)
     */
    public function getOriginTypes()
    {
        try {
            $originTypes = DB::connection('alerts_db')
                ->table('origin_types')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            return response()->json($originTypes);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener tipos de origen',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener casos de origen por tipo (origin_cases)
     * @param int $typeId - ID del tipo de origen
     */
    public function getOriginCasesByType($typeId)
    {
        try {
            $originCases = DB::connection('alerts_db')
                ->table('origin_cases')
                ->select('id', 'name', 'OriginTypesId as type_origin_case_id')
                ->where('OriginTypesId', $typeId)
                ->orderBy('name')
                ->get();

            return response()->json($originCases);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener casos de origen',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener todos los tipos de alerta (alert_types)
     */
    public function getAlertTypes()
    {
        try {
            $alertTypes = DB::connection('alerts_db')
                ->table('alert_types')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            return response()->json($alertTypes);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener tipos de alerta',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener todas las provincias
     */
    public function getProvinces()
    {
        try {
            $provinces = DB::connection('alerts_db')
                ->table('provinces')
                ->select('id', 'name', 'region_id')
                ->orderBy('name')
                ->get();

            return response()->json($provinces);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener provincias',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener todos los municipios
     */
    public function getAllMunicipalities()
    {
        try {
            $municipalities = DB::connection('alerts_db')
                ->table('municipalities')
                ->select('id', 'name', 'province_id')
                ->orderBy('name')
                ->get();

            return response()->json($municipalities);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener municipios',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener municipios por provincia
     * @param int $provinceId - ID de la provincia
     */
    public function getMunicipalities($provinceId)
    {
        try {
            $municipalities = DB::connection('alerts_db')
                ->table('municipalities')
                ->select('id', 'name', 'province_id')
                ->where('province_id', $provinceId)
                ->orderBy('name')
                ->get();

            return response()->json($municipalities);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener municipios',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener instituciones que dan alertas
     */
    public function getInstitutionsWhoGiveAlerts()
    {
        try {
            // alert_type_id 3 = Instituciones
            $institutions = DB::connection('alerts_db')
                ->table('alert_details_options')
                ->select('id', 'name')
                ->where('alert_type_id', 3)
                ->orderBy('name')
                ->get();

            return response()->json($institutions);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener instituciones',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener redes sociales o canales de noticias
     */
    public function getSocialMediaOrNewChannels()
    {
        try {
            // alert_type_id 4 = Redes sociales
            $socialMedia = DB::connection('alerts_db')
                ->table('alert_details_options')
                ->select('id', 'name')
                ->where('alert_type_id', 4)
                ->orderBy('name')
                ->get();

            return response()->json($socialMedia);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener redes sociales',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener protocolos institucionales
     */
    public function getInstitutionalProtocols()
    {
        try {
            // alert_type_id 5 = Protocolos institucionales
            $protocols = DB::connection('alerts_db')
                ->table('alert_details_options')
                ->select('id', 'name')
                ->where('alert_type_id', 5)
                ->orderBy('name')
                ->get();

            return response()->json($protocols);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener protocolos institucionales',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener posiciones de empleados
     */
    public function getEmployeePositions()
    {
        try {
            $positions = DB::connection('alerts_db')
                ->table('employee_positions')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            return response()->json($positions);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener posiciones de empleados',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener géneros
     */
    public function getGenders()
    {
        try {
            $genders = DB::connection('alerts_db')
                ->table('genders')
                ->select('id', 'gender as name')
                ->orderBy('gender')
                ->get();

            return response()->json($genders);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener géneros',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar NNA por nombre y/o apellido
     */
    public function searchNNA(Request $request)
    {
        try {
            $name = $request->input('name');
            $surname = $request->input('surname');
            $nickname = $request->input('nickname');

            $query = DB::connection('alerts_db')
                ->table('nna')
                ->select(
                    'id',
                    'code',
                    'name',
                    'surname',
                    'nickname',
                    'gender_id',
                    'birth_date',
                    'age',
                    'ageMeasuredIn',
                    'ageCalculatedBy'
                );

            // Filtrar por nombre si se proporciona
            if ($name) {
                $query->where('name', 'LIKE', "%{$name}%");
            }

            // Filtrar por apellido si se proporciona
            if ($surname) {
                $query->where('surname', 'LIKE', "%{$surname}%");
            }

            // Filtrar por apodo si se proporciona
            if ($nickname) {
                $query->where('nickname', 'LIKE', "%{$nickname}%");
            }

            $results = $query->limit(10)->get();

            return response()->json($results);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al buscar NNA',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener todos los datos maestros consolidados en una única petición
     * Optimiza la carga del formulario de alertas
     */
    public function getAllMasterDataConsolidated()
    {
        try {
            // Obtener todos los datos en paralelo dentro del servidor
            $originTypes = DB::connection('alerts_db')
                ->table('origin_types')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            $alertTypes = DB::connection('alerts_db')
                ->table('alert_types')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            $provinces = DB::connection('alerts_db')
                ->table('provinces')
                ->select('id', 'name', 'region_id')
                ->orderBy('name')
                ->get();

            $municipalities = DB::connection('alerts_db')
                ->table('municipalities')
                ->select('id', 'name', 'province_id')
                ->orderBy('name')
                ->get();

            $institutions = DB::connection('alerts_db')
                ->table('alert_details_options')
                ->select('id', 'name')
                ->where('alert_type_id', 3)
                ->orderBy('name')
                ->get();

            $socialMedia = DB::connection('alerts_db')
                ->table('alert_details_options')
                ->select('id', 'name')
                ->where('alert_type_id', 4)
                ->orderBy('name')
                ->get();

            $institutionalProtocols = DB::connection('alerts_db')
                ->table('alert_details_options')
                ->select('id', 'name')
                ->where('alert_type_id', 5)
                ->orderBy('name')
                ->get();

            $employeePositions = DB::connection('alerts_db')
                ->table('employee_positions')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            $genders = DB::connection('alerts_db')
                ->table('genders')
                ->select('id', 'gender as name')
                ->orderBy('gender')
                ->get();

            // Obtener todos los casos de origen agrupados
            $originCases = DB::connection('alerts_db')
                ->table('origin_cases')
                ->select('id', 'name', 'OriginTypesId as type_origin_case_id')
                ->orderBy('name')
                ->get();

            return response()->json([
                'originTypes' => $originTypes,
                'alertTypes' => $alertTypes,
                'provinces' => $provinces,
                'municipalities' => $municipalities,
                'institutions' => $institutions,
                'socialMedia' => $socialMedia,
                'institutionalProtocols' => $institutionalProtocols,
                'employeePositions' => $employeePositions,
                'genders' => $genders,
                'originCases' => $originCases
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener datos maestros consolidados',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
