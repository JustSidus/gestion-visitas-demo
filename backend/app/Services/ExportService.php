<?php

namespace App\Services;

use App\Models\Visit;
use App\Repositories\Contracts\VisitRepositoryInterface;
use App\Exports\VisitsExport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\DomPDF;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\View;

/**
 * Servicio especializado en exportaciones de visitas
 * 
 * Responsabilidades:
 * - Generar exportaciones Excel
 * - Generar exportaciones PDF
 * - Aplicar filtros de búsqueda
 * - Logging de operaciones de exportación
 * - Validar permisos de exportación
 */
class ExportService
{
    protected $visitRepository;

    public function __construct(VisitRepositoryInterface $visitRepository)
    {
        $this->visitRepository = $visitRepository;
    }

    /**
     * Exporta visitas a Excel con filtros aplicados
     * 
     * @param array $filters Filtros de búsqueda
     * @return array Información del archivo generado
     * @throws \Exception Si no hay datos para exportar
     */
    public function exportToExcel(array $filters): array
    {
        // Log de depuración
        Log::info('ExportService::exportToExcel - Filtros recibidos', [
            'filters' => $filters,
            'filter_count' => count($filters)
        ]);
        
        // 1. Obtener datos con filtros
        $visits = $this->getFilteredVisits($filters);
        
        // Log de depuración
        Log::info('ExportService::exportToExcel - Visitas obtenidas', [
            'count' => $visits->count(),
            'filters_applied' => $filters
        ]);
        
        // 2. Validar que hay datos
        if ($visits->isEmpty()) {
            throw new \Exception('No hay visitas para exportar con los filtros aplicados');
        }
        
        // 3. Generar exportación
        $export = new VisitsExport($visits);
        $result = $export->generateExcel();
        
        // 4. Log de operación
        $this->logExportOperation('excel', $visits->count(), $filters);
        
        return [
            'filepath' => $result['filepath'],
            'filename' => $result['filename'],
            'records_count' => $visits->count(),
            'filters_applied' => $filters,
            'generated_at' => now()->toISOString()
        ];
    }

    /**
     * Exporta visitas a PDF con filtros aplicados
     * 
     * @param array $filters Filtros de búsqueda
     * @return array Información del PDF generado
     * @throws \Exception Si no hay datos para exportar
     */
    public function exportToPDF(array $filters): array
    {
        // Log de depuración
        Log::info('ExportService::exportToPDF - Filtros recibidos', [
            'filters' => $filters,
            'filter_count' => count($filters)
        ]);
        
        // 1. Obtener datos con filtros
        $visits = $this->getFilteredVisits($filters);
        
        // Log de depuración
        Log::info('ExportService::exportToPDF - Visitas obtenidas', [
            'count' => $visits->count(),
            'filters_applied' => $filters
        ]);
        
        // 2. Validar que hay datos
        if ($visits->isEmpty()) {
            throw new \Exception('No hay visitas para exportar con los filtros aplicados');
        }
        
        try {
            // 3. Preparar datos para la vista
            $data = $this->preparePDFData($visits, $filters);
            
            // 4. Renderizar la vista HTML primero
            $html = View::make('reports.visits-pdf', $data)->render();
            
            // 5. Generar PDF desde HTML renderizado
            $pdf = Pdf::loadHTML($html);
            $pdf->setPaper('a4', 'landscape');
            
            // 6. Generar nombre de archivo
            $filename = $this->generatePDFFilename($filters);
            
            // 7. Log de operación
            $this->logExportOperation('pdf', $visits->count(), $filters);
            
            return [
                'pdf' => $pdf,
                'filename' => $filename,
                'records_count' => $visits->count(),
                'filters_applied' => $filters,
                'generated_at' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error generando PDF: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Obtiene estadísticas de exportación para el dashboard
     * 
     * @return array Estadísticas de uso
     */
    public function getExportStats(): array
    {
        // En futuras versiones, esto vendría de una tabla de logs de exportaciones
        return [
            'service_available' => true,
            'supported_formats' => ['excel', 'pdf'],
            'max_records_per_export' => 5000,
            'last_check' => now()->toISOString()
        ];
    }

    /**
     * Valida que el usuario puede exportar con los filtros dados
     * 
     * @param array $filters Filtros solicitados
     * @param mixed $user Usuario que solicita
     * @return bool
     */
    public function canExportWithFilters(array $filters, $user): bool
    {
        // Por ahora, todos los usuarios autenticados pueden exportar
        // En futuras versiones se pueden agregar restricciones por rol
        
        $recordLimit = $filters['limit'] ?? 1000;
        
        // Validar límite máximo
        if ($recordLimit > 5000) {
            return false;
        }
        
        return true;
    }

    /**
     * Obtiene visitas filtradas usando el repositorio
     */
    private function getFilteredVisits(array $filters): Collection
    {
        // Para exportaciones NO aplicamos límite por defecto
        // Solo si el usuario explícitamente lo especifica
        // El límite de seguridad de 5000 se aplicará en el repositorio si se especifica
        
        return $this->visitRepository->advancedSearch($filters);
    }

    /**
     * Prepara los datos específicos para la vista PDF
     */
    private function preparePDFData(Collection $visits, array $filters): array
    {
        $stats = $this->calculateExportStatistics($visits);
        
        return [
            'visits' => $visits,
            'filters_applied' => $filters,
            'generated_date' => Carbon::now()->format('d/m/Y H:i:s'),
            'report_title' => $this->generateReportTitle($filters),
            'company_info' => $this->getCompanyInfo(),
            'total_visits' => $stats['total_visits'],
            'open_visits' => $stats['open_visits'],
            'closed_visits' => $stats['closed_visits'],
            'mission_cases' => $stats['mission_cases'],
            'today_visits' => $stats['today_visits'],
            'average_duration' => $stats['average_duration'],
            'most_visited_departments' => $stats['most_visited_departments']
        ];
    }

    /**
     * Calcula estadísticas para el reporte
     */
    private function calculateExportStatistics(Collection $visits): array
    {
        return [
            'total_visits' => $visits->count(),
            'open_visits' => $visits->where('status_id', 1)->count(),
            'closed_visits' => $visits->where('status_id', 2)->count(),
            'mission_cases' => $visits->where('mission_case', true)->count(),
            'today_visits' => $visits->filter(function ($visit) {
                return Carbon::parse($visit->created_at)->isToday();
            })->count(),
            'average_duration' => $this->calculateAverageDuration($visits->where('status_id', 2)),
            'most_visited_departments' => $this->getMostVisitedDepartments($visits)
        ];
    }

    /**
     * Calcula la duración promedio de visitas cerradas
     */
    private function calculateAverageDuration(Collection $closedVisits): ?string
    {
        if ($closedVisits->isEmpty()) {
            return null;
        }
        
        $totalMinutes = 0;
        $count = 0;
        
        foreach ($closedVisits as $visit) {
            if ($visit->end_at && $visit->created_at) {
                $totalMinutes += Carbon::parse($visit->created_at)->diffInMinutes($visit->end_at);
                $count++;
            }
        }
        
        if ($count === 0) {
            return null;
        }
        
        $avgMinutes = intval($totalMinutes / $count);
        
        if ($avgMinutes < 60) {
            return "{$avgMinutes} minutos";
        }
        
        $hours = intdiv($avgMinutes, 60);
        $minutes = $avgMinutes % 60;
        
        return "{$hours}h {$minutes}m";
    }

    /**
     * Obtiene los departamentos más visitados
     */
    private function getMostVisitedDepartments(Collection $visits): array
    {
        return $visits->groupBy('department')
            ->map(function ($group) {
                return $group->count();
            })
            ->sortDesc()
            ->take(5)
            ->toArray();
    }

    /**
     * Genera título del reporte basado en filtros
     */
    private function generateReportTitle(array $filters): string
    {
        $title = 'Reporte de Visitas';
        
        if (!empty($filters['start_date']) || !empty($filters['end_date'])) {
            $title .= ' - Período';
            if (!empty($filters['start_date'])) {
                $title .= ' desde ' . Carbon::parse($filters['start_date'])->format('d/m/Y');
            }
            if (!empty($filters['end_date'])) {
                $title .= ' hasta ' . Carbon::parse($filters['end_date'])->format('d/m/Y');
            }
        } else {
            $title .= ' - Todos los registros';
        }
        
        if (!empty($filters['department'])) {
            $title .= ' - Departamento: ' . $filters['department'];
        }
        
        if (isset($filters['mission_case']) && $filters['mission_case']) {
            $title .= ' - Solo casos misionales';
        }
        
        return $title;
    }

    /**
     * Genera nombre de archivo para PDF
     */
    private function generatePDFFilename(array $filters): string
    {
        $date = Carbon::now()->format('Y-m-d_His');
        $base = 'visitas_' . $date;
        
        if (!empty($filters['department'])) {
            $dept = strtolower(str_replace(' ', '_', $filters['department']));
            $base .= '_' . $dept;
        }
        
        if (isset($filters['mission_case']) && $filters['mission_case']) {
            $base .= '_misionales';
        }
        
        return $base . '.pdf';
    }

    /**
     * Obtiene información de la empresa para el reporte
     */
    private function getCompanyInfo(): array
    {
        return [
            'name' => 'Institución Demo',
            'full_name' => 'Institución Demo',
            'address' => 'Av. Demo #123, Centro, Ciudad Demo, República Dominicana',
            'phone' => '000-000-0000',
            'email' => 'info@demo.example.org',
            'website' => 'www.demo.example.org'
        ];
    }

    /**
     * Registra operación de exportación para auditoría
     */
    private function logExportOperation(string $format, int $recordCount, array $filters): void
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $userId = $user->id;
            $userName = $user->name;
        } catch (\Exception $e) {
            $userId = null;
            $userName = 'unknown';
        }
        
        Log::info('Exportación realizada', [
            'format' => $format,
            'records_exported' => $recordCount,
            'filters_applied' => $filters,
            'exported_by' => $userId,
            'exported_by_name' => $userName,
            'exported_at' => now()->toISOString(),
            'ip_address' => request()->ip()
        ]);
    }
}