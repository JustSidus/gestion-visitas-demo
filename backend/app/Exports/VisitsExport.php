<?php

namespace App\Exports;

use App\Models\Visit;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class VisitsExport
{
    protected $visits;

    public function __construct($visits)
    {
        $this->visits = $visits;
    }

    public function generateExcel()
    {
        $filename = 'visitas_' . Carbon::now()->format('Y-m-d_His') . '.xlsx';
        $filepath = storage_path('app/public/exports/' . $filename);

        // Crear directorio si no existe
        if (!file_exists(storage_path('app/public/exports'))) {
            mkdir(storage_path('app/public/exports'), 0755, true);
        }

        // Crear nuevo Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Visitas');

        // Encabezados
        $headers = [
            'ID',
            'Visitante',
            'Apellido',
            'Institución',
            'Tipo de Documento',
            'Número de Documento',
            'Teléfono',
            'Email',
            'Persona a Visitar',
            'Departamento',
            'Edificio',
            'Piso',
            'Email Persona a Visitar',
            'Notificación Enviada',
            'Motivo de Visita',
            'Tipo de Visita',
            'Carnet Asignado',
            'Placa Vehículo',
            'Fecha de Entrada',
            'Fecha de Salida',
            'Registrado Por',
            'Cerrado Por'
        ];

        // Escribir encabezados
        $column = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($column . '1', $header);
            $column++;
        }

        // Estilo de encabezados (A1:V1 para 22 columnas)
        $sheet->getStyle('A1:V1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 12
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // Escribir datos
        $row = 2;
        foreach ($this->visits as $visit) {
            $visitor = $visit->visitors->first();
            
            // Obtener etiqueta del tipo de documento
            $documentTypeLabel = '';
            if ($visitor && $visitor->document_type) {
                $documentTypeLabel = match($visitor->document_type->value) {
                    1 => 'Cédula',
                    2 => 'Pasaporte',
                    3 => 'Sin Identificación',
                    default => ''
                };
            }
            
            $sheet->setCellValue('A' . $row, $visit->id);
            $sheet->setCellValue('B' . $row, $visitor->name ?? '');
            $sheet->setCellValue('C' . $row, $visitor->lastName ?? '');
            $sheet->setCellValue('D' . $row, $visitor->institution ?? '');
            $sheet->setCellValue('E' . $row, $documentTypeLabel);
            $sheet->setCellValue('F' . $row, $visitor->identity_document ?? '');
            $sheet->setCellValue('G' . $row, $visitor->phone ?? '');
            $sheet->setCellValue('H' . $row, $visitor->email ?? '');
            $sheet->setCellValue('I' . $row, $visit->namePersonToVisit);
            $sheet->setCellValue('J' . $row, $visit->department ?? '—');
            $sheet->setCellValue('K' . $row, $visit->building ?? '');
            $sheet->setCellValue('L' . $row, $visit->floor ?? '');
            $sheet->setCellValue('M' . $row, $visit->person_to_visit_email ?? '—');
            $sheet->setCellValue('N' . $row, $visit->send_email ? 'Sí' : 'No');
            $sheet->setCellValue('O' . $row, $visit->reason);
            $sheet->setCellValue('P' . $row, $visit->mission_case ? 'Caso Misional' : 'Regular');
            $sheet->setCellValue('Q' . $row, $visit->assigned_carnet);
            $sheet->setCellValue('R' . $row, $visit->vehicle_plate ?? '—');
            $sheet->setCellValue('S' . $row, Carbon::parse($visit->created_at)->format('d/m/Y H:i'));
            $sheet->setCellValue('T' . $row, $visit->end_at ? Carbon::parse($visit->end_at)->format('d/m/Y H:i') : '');
            $sheet->setCellValue('U' . $row, $visit->user->name ?? '');
            $sheet->setCellValue('V' . $row, $visit->closedByUser->name ?? '—');
            
            $row++;
        }

        // Aplicar bordes a todas las celdas con datos (hasta columna V)
        $sheet->getStyle('A1:V' . ($row - 1))->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ]
        ]);

        // Autoajustar ancho de columnas (hasta V - 22 columnas)
        foreach (range('A', 'V') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Ajustar altura de la fila de encabezados
        $sheet->getRowDimension(1)->setRowHeight(25);

        // Guardar archivo
        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);

        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => asset('storage/exports/' . $filename)
        ];
    }

    // Mantener método CSV por si se necesita en el futuro
    public function generateCSV()
    {
        $filename = 'visitas_' . Carbon::now()->format('Y-m-d_His') . '.csv';
        $filepath = storage_path('app/public/exports/' . $filename);

        // Crear directorio si no existe
        if (!file_exists(storage_path('app/public/exports'))) {
            mkdir(storage_path('app/public/exports'), 0755, true);
        }

        $file = fopen($filepath, 'w');

        // Agregar BOM para UTF-8 en Excel
        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

        // Encabezados
        fputcsv($file, [
            'ID',
            'Visitante',
            'Apellido',
            'Institución',
            'Tipo de Documento',
            'Número de Documento',
            'Teléfono',
            'Email',
            'Persona a Visitar',
            'Departamento',
            'Edificio',
            'Piso',
            'Email Persona a Visitar',
            'Notificación Enviada',
            'Motivo de Visita',
            'Tipo de Visita',
            'Carnet Asignado',
            'Placa Vehículo',
            'Fecha de Entrada',
            'Fecha de Salida',
            'Registrado Por',
            'Cerrado Por'
        ], ';');

        // Datos
        foreach ($this->visits as $visit) {
            $visitor = $visit->visitors->first();
            
            // Obtener etiqueta del tipo de documento
            $documentTypeLabel = '';
            if ($visitor && $visitor->document_type) {
                $documentTypeLabel = match($visitor->document_type->value) {
                    1 => 'Cédula',
                    2 => 'Pasaporte',
                    3 => 'Sin Identificación',
                    default => ''
                };
            }
            
            fputcsv($file, [
                $visit->id,
                $visitor->name ?? '',
                $visitor->lastName ?? '',
                $visitor->institution ?? '',
                $documentTypeLabel,
                $visitor->identity_document ?? '',
                $visitor->phone ?? '',
                $visitor->email ?? '',
                $visit->namePersonToVisit,
                $visit->department ?? '—',
                $visit->building ?? '',
                $visit->floor ?? '',
                $visit->person_to_visit_email ?? '—',
                $visit->send_email ? 'Sí' : 'No',
                $visit->reason,
                $visit->mission_case ? 'Caso Misional' : 'Regular',
                $visit->assigned_carnet,
                $visit->vehicle_plate ?? '—',
                Carbon::parse($visit->created_at)->format('d/m/Y H:i'),
                $visit->end_at ? Carbon::parse($visit->end_at)->format('d/m/Y H:i') : '',
                $visit->user->name ?? '',
                $visit->closedByUser->name ?? '—'
            ], ';');
        }

        fclose($file);

        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => asset('storage/exports/' . $filename)
        ];
    }
}
