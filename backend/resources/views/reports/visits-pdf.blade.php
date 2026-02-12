<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Visitas - Institución Demo</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            position: relative;
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 15px;
        }
        .logo {
            position: absolute;
            text-align: left;
            left: 30;
            top: -20;
            width: 120px;
            height: auto;
        }
        .header h1 {
            color: #2563eb;
            font-size: 20px;
            margin: 0 0 5px 0;
        }
        .header h2 {
            color: #64748b;
            font-size: 14px;
            margin: 0;
            font-weight: normal;
        }
        .info-section {
            margin-bottom: 20px;
            background-color: #f8fafc;
            padding: 10px;
            border-left: 4px solid #2563eb;
        }
        .info-section p {
            margin: 3px 0;
            font-size: 9px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            /* Use fixed layout so column widths are respected and long words wrap inside cells */
            table-layout: fixed;
        }
        thead {
            background-color: #2563eb;
            color: white;
        }
        th {
            padding: 8px 4px;
            text-align: left;
            font-size: 9px;
            font-weight: bold;
        }
        td {
            padding: 6px 4px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 8px;
            /* Allow long/continuous strings to break/wrap inside the cell */
            word-wrap: break-word;
            overflow-wrap: anywhere;
            word-break: break-all;
            white-space: normal;
        }
        tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        tbody tr:hover {
            background-color: #eff6ff;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 7px;
            font-weight: bold;
        }
        .badge-open {
            background-color: #dcfce7;
            color: #166534;
        }
        .badge-closed {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 8px;
            color: #64748b;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
        }
        .summary {
            margin: 15px 0;
            padding: 10px;
            background-color: #eff6ff;
            border-radius: 5px;
        }
        .summary-item {
            display: inline-block;
            margin-right: 20px;
            font-size: 9px;
        }
        .summary-item strong {
            color: #2563eb;
        }
    </style>
</head>
<body>
    <div class="header">
        @php
            $logoPath = public_path('frontend\\src\\assets\\logo-institucion-demo.png');
            if (file_exists($logoPath)) {
                echo '<img src="' . $logoPath . '" alt="Logo Institución Demo" class="logo">';
            }
        @endphp
        <h1>Institución Demo</h1>
        <h2>Reporte de Visitas</h2>
    </div>

    <div class="info-section">
        <p><strong>Fecha de generación:</strong> {{ $generated_date }}</p>
        <p><strong>Total de registros:</strong> {{ $total_visits }}</p>
        @if($filters_applied)
            <p><strong>Filtros aplicados:</strong> 
                @if(isset($filters_applied['start_date']) && $filters_applied['start_date'])
                    Desde: {{ $filters_applied['start_date'] }}
                @endif
                @if(isset($filters_applied['end_date']) && $filters_applied['end_date'])
                    Hasta: {{ $filters_applied['end_date'] }}
                @endif
                @if(isset($filters_applied['person_visited']) && $filters_applied['person_visited'])
                    Persona: {{ $filters_applied['person_visited'] }}
                @endif
                @if(isset($filters_applied['visitor_search']) && $filters_applied['visitor_search'])
                    Visitante: {{ $filters_applied['visitor_search'] }}
                @endif
                @if(isset($filters_applied['mission_case']) && ($filters_applied['mission_case'] === true || $filters_applied['mission_case'] === 1 || $filters_applied['mission_case'] === '1' || $filters_applied['mission_case'] === 'on'))
                    Solo Casos de Misión
                @endif
            </p>
        @endif
    </div>

    <div class="summary">
        <div class="summary-item">
            <strong>Total de Registros:</strong> {{ $total_visits }}
        </div>
        <div class="summary-item">
            <strong>Casos de Misión:</strong> {{ $mission_cases }}
        </div>
        <div class="summary-item">
            <strong>Visitas Regulares:</strong> {{ $total_visits - $mission_cases }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 2%;">ID</th>
                <th style="width: 6%;">Visitante</th>
                <th style="width: 6%;">Apellido</th>
                <th style="width: 5%;">Institución</th>
                <th style="width: 4%;">Tipo Doc.</th>
                <th style="width: 5%;">Documento</th>
                <th style="width: 5%;">Teléfono</th>
                <th style="width: 6%;">Email</th>
                <th style="width: 7%;">Persona Visitada</th>
                <th style="width: 6%;">Departamento</th>
                <th style="width: 4%;">Edificio</th>
                <th style="width: 3%;">Piso</th>
                <th style="width: 6%;">Email Visitado</th>
                <th style="width: 3%;">Notif.</th>
                <th style="width: 10%;">Motivo</th>
                <th style="width: 4%;">Tipo</th>
                <th style="width: 3%;">Carnet</th>
                <th style="width: 4%;">Placa</th>
                <th style="width: 6%;">Entrada</th>
                <th style="width: 6%;">Salida</th>
                <th style="width: 5%;">Registró</th>
                <th style="width: 5%;">Cerró</th>
            </tr>
        </thead>
        <tbody>
            @foreach($visits as $visit)
                @php
                    $visitor = $visit->visitors->first();
                @endphp
                <tr>
                    <td>{{ $visit->id }}</td>
                    <td>{{ $visitor->name ?? '' }}</td>
                    <td>{{ $visitor->lastName ?? '' }}</td>
                    <td style="font-size: 7px;">{{ $visitor->institution ?? '—' }}</td>
                    <td style="font-size: 7px;">
                        @if($visitor && $visitor->document_type)
                            @switch($visitor->document_type->value)
                                @case(1) Cédula @break
                                @case(2) Pasaporte @break
                                @case(3) Sin Identificación @break
                                @default — @break
                            @endswitch
                        @else
                            —
                        @endif
                    </td>
                    <td style="font-size: 7px;">{{ $visitor->identity_document ?? '' }}</td>
                    <td style="font-size: 6px;">{{ $visitor->phone ?? '—' }}</td>
                    <td style="font-size: 6px;">{{ $visitor->email ?? '—' }}</td>
                    <td style="font-size: 7px;">{{ $visit->namePersonToVisit }}</td>
                    <td style="font-size: 7px;">{{ $visit->department ?? '—' }}</td>
                    <td style="font-size: 7px;">{{ $visit->building ?? '—' }}</td>
                    <td style="font-size: 7px;">{{ $visit->floor ?? '—' }}</td>
                    <td style="font-size: 6px;">{{ $visit->person_to_visit_email ?? '—' }}</td>
                    <td style="font-size: 7px;">{{ $visit->send_email ? 'Sí' : 'No' }}</td>
                    @php
                        // Insert <wbr> every 40 non-space characters to allow breaking very long sequences
                        $rawReason = $visit->reason ?? '';
                        $escaped = e($rawReason);
                        $withWbr = preg_replace('/([^\s]{40})/', '$1<wbr>', $escaped);
                    @endphp
                    <td style="font-size: 6px;">{!! $withWbr !!}</td>
                    <td style="font-size: 7px;">
                        @if($visit->mission_case)
                            Misional
                        @else
                            Regular
                        @endif
                    </td>
                    <td style="font-size: 7px;">{{ $visit->assigned_carnet ?? '—' }}</td>
                    <td style="font-size: 7px;">{{ $visit->vehicle_plate ?? '—' }}</td>
                    <td style="font-size: 6px;">{{ \Carbon\Carbon::parse($visit->created_at)->format('d/m/Y H:i') }}</td>
                    <td style="font-size: 6px;">{{ $visit->end_at ? \Carbon\Carbon::parse($visit->end_at)->format('d/m/Y H:i') : '—' }}</td>
                    <td style="font-size: 6px;">{{ $visit->user->name ?? '—' }}</td>
                    <td style="font-size: 6px;">{{ $visit->closedByUser->name ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Departamento de Tecnologías de la Información y Comunicación (TIC) - Institución Demo</p>
        <p>Sistema Digital de Registro y Control de Visitantes © {{ date('Y') }}</p>
    </div>
</body>
</html>
