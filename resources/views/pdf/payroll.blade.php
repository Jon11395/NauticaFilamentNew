<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planilla de Salarios - {{ $project->name }}</title>
    <style>
        html, body { 
            font-family: "DejaVu Sans", sans-serif; 
            font-size: 12px; 
            line-height: 1.4;
            color: #222; 
        }
        
        /* Ensure proper character encoding for currency symbols */
        .currency {
            font-family: "DejaVu Sans", Arial, sans-serif;
        }
        body { margin-top: 80; padding: 0; }

        header, footer {
            position: fixed;
            left: 0; right: 0;
           
        }
        header {
            top: 0mm; height: 18mm;
            padding-bottom: 25px;
            border-bottom: 1px solid #ddd;
        }
        footer {
            bottom: 0mm; height: 14mm;
            border-top: 1px solid #ddd;
            font-size: 10px; color: #555;
        }
        .pagenum:before { content: counter(page); }

        .container { padding: 28mm 10mm 22mm 10mm; }
        
        h1 { font-size: 18px; margin: 0 0 8px 0; }
        h2 { font-size: 14px; margin: 16px 0 8px 0; color:#333; }
        h3 { font-size: 12px; margin: 10px 0 6px 0; color:#444; }
        p.meta { margin: 0; font-size: 11px; color:#444; }

        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 12px; 
            table-layout: fixed;
        }
        th, td { 
            padding: 12px 15px; 
            border: 1px solid #e0e0e0; 
            vertical-align: top; 
            font-size: 11px;
            line-height: 1.4;
        }
        th { 
            background: #f7f7f7; 
            font-weight: 600; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
            text-align: left;
        }
        tbody tr:nth-child(even) { background: #fafafa; }
        
        /* Two column layout */
        .col-employee { width: 35%; }
        .col-details { width: 65%; min-width: 300px; }
        
        /* Simple Breakdown styling */
        .breakdown {
            font-size: 11px;
            line-height: 1.3;
            font-family: "DejaVu Sans", Arial, sans-serif;
            background: #ffffff;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .breakdown-section {
            margin-bottom: 8px;
        }
        
        .breakdown-section:last-child {
            margin-bottom: 0;
        }
        
        .breakdown-section-title {
            font-size: 10px;
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        
        .breakdown-row {
            display: table;
            width: 100%;
            table-layout: fixed;
        }
        
        .breakdown-label {
            color: #555;
            font-weight: 500;
            font-size: 10px;
            text-align: left;
            display: table-cell;
            width: 70%;
            vertical-align: middle;
        }
        
        .breakdown-value {
            font-weight: 600;
            color: #222;
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 11px;
            text-align: right;
            display: table-cell;
            width: 30%;
            vertical-align: middle;
        }
        
        .breakdown-total {
            background: #f5f5f5;
            color: #222;
            padding: 6px 8px;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            display: table;
            width: 100%;
            table-layout: fixed;
            border-top: 2px solid #333;
        }
        
        .breakdown-total .currency {
            font-size: 12px;
            font-weight: 800;
        }
        
        .summary-row { 
            background: #e8f5e8 !important; 
            border-top: 2px solid #28a745;
            font-weight: 700;
        }
        .summary-row td { 
            background: #e8f5e8 !important; 
            border-top: 2px solid #28a745;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .muted { color: #777; font-size: 11px; }
        .summary-row { font-weight: 700; background: #f0f0f0; }
        .section { margin-bottom: 14px; }

        .estado-pagado { color: green; font-weight: 600; }
        .estado-pendiente { color: red; font-weight: 600; }

        .page-break { page-break-after: always; }
        .nested-table th, .nested-table td { border: 1px dashed #ccc; font-size: 11px; }
    </style>
</head>
<body>
    <header>
        <table width="100%" style="border-collapse: collapse; border: none;">
            <tr style="border: none;">
                <!-- Left section: Company info -->
                <td style="vertical-align: middle; border: none;">
                    <strong style="font-size: 16px;">NAUTICA</strong><br>
                    <span style="color: #666; font-size: 12px;">Planilla de Salarios</span><br>
                    <span style="color: #666; font-size: 11px;">{{ $project->name }}</span>
                </td>
    
                <!-- Right section: Logo -->
                <td style="text-align: right; vertical-align: middle; border: none;">
                    @if($recordImage)
                        <img src="{{ $recordImage }}" alt="Nautica Logo" width="80" style="height: auto; border: none;">
                    @endif
                </td>
            </tr>
        </table>
    </header>
    
    <footer>
        <div style="display:flex; justify-content:space-between;">
            <div class="muted">Generado: {{ now()->format('d-m-Y H:i') }}</div>
            <div class="muted">Página <span class="pagenum"></span></div>
        </div>
    </footer>

    <div class="container">
        <h1>PLANILLA DE SALARIOS</h1>
        <p class="meta">Proyecto: {{ $project->name }}</p>
        <p class="meta">Período: {{ $period }}</p>
        <p class="meta">Tipo: {{ $payrollType === 'hourly' ? 'Por Horas' : 'Salario Fijo' }}</p>
        <p class="meta">Fecha de Generación: {{ now()->format('d-m-Y H:i') }}</p>
        <p class="meta">Total de Empleados: {{ $totals['total_employees'] }}</p>

    <div class="section">
        <h2>Empleados ({{ $totals['total_employees'] }})</h2>
        <table>
            <thead>
                <tr>
                    <th class="col-employee">EMPLEADO</th>
                    <th class="col-details">DETALLE DE PAGO</th>
                </tr>
            </thead>
        <tbody>
            @foreach($employees as $employee)
                <tr>
                    <td>
                        <strong>{{ $employee['name'] }}</strong>
                        @if($payrollType === 'hourly')
                            <br><small style="color: #666;">Tarifa: <span class="currency">₡ {{ number_format($employee['hourly_rate'], 2) }}/h</span></small>
                        @endif
                    </td>
                    <td>
                        @if($payrollType === 'hourly')
                            <div class="breakdown-row">
                                <span class="breakdown-label">Horas Regulares ({{ number_format($employee['hours'], 1) }}h)</span>
                                ₡<span class="breakdown-value currency"> {{ number_format($employee['hours'] * $employee['hourly_rate'], 2) }}</span>
                            </div>
                            <div class="breakdown-row">
                                <span class="breakdown-label">Horas Extra ({{ number_format($employee['extra_hours'], 1) }}h)</span>
                                ₡<span class="breakdown-value currency"> {{ number_format($employee['extra_hours'] * $employee['hourly_rate'] * 1.5, 2) }}</span>
                            </div>
                            <div class="breakdown-row">
                                <span class="breakdown-label">Trabajo Nocturno ({{ $employee['night_days'] }} días)</span>
                                ₡<span class="breakdown-value currency"> {{ number_format($employee['night_days'] * \App\Models\GlobalConfig::getValue('night_work_bonus', 0), 2) }}</span>
                            </div>
                            <div class="breakdown-row">
                                <span class="breakdown-label">CCSS</span>
                                ₡<span class="breakdown-value currency"> {{ number_format($employee['ccss'], 2) }}</span>
                            </div>
                            <div class="breakdown-row">
                                <span class="breakdown-label">Adicionales</span>
                                ₡<span class="breakdown-value currency"> {{ number_format($employee['adicionales'], 2) }}</span>
                            </div>
                            <div class="breakdown-row">
                                <span class="breakdown-label">Rebajas</span>
                                ₡<span class="breakdown-value currency"> {{ number_format($employee['rebajos'], 2) }}</span>
                            </div>
                        @else
                            <div class="breakdown-row">
                                <span class="breakdown-label">Salario Fijo</span>
                                ₡<span class="breakdown-value currency"> {{ number_format($employee['salario_base'], 2) }}</span>
                            </div>
                            <div class="breakdown-row">
                                <span class="breakdown-label">Horas Regulares</span>
                                <span class="breakdown-value">N/A</span>
                            </div>
                            <div class="breakdown-row">
                                <span class="breakdown-label">Horas Extra</span>
                                <span class="breakdown-value">N/A</span>
                            </div>
                            <div class="breakdown-row">
                                <span class="breakdown-label">Trabajo Nocturno</span>
                                <span class="breakdown-value">N/A</span>
                            </div>
                            <div class="breakdown-row">
                                <span class="breakdown-label">CCSS</span>
                                <span class="breakdown-value">N/A</span>
                            </div>
                            <div class="breakdown-row">
                                <span class="breakdown-label">Adicionales</span>
                                <span class="breakdown-value">N/A</span>
                            </div>
                            <div class="breakdown-row">
                                <span class="breakdown-label">Rebajas</span>
                                <span class="breakdown-value">N/A</span>
                            </div>
                        @endif
                        <div class="breakdown-total">
                            <span class="breakdown-label">TOTAL A PAGAR</span>
                            ₡<span class="breakdown-value currency"> {{ number_format($employee['salario_total'], 2) }}</span>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="summary-row">
                <td class="col-employee">
                    <strong>TOTALES GENERALES</strong>
                    @if($payrollType === 'hourly')
                        <br><small>Total empleados: {{ $totals['total_employees'] }}</small>
                    @endif
                </td>
                <td class="col-details">
                    @if($payrollType === 'hourly')
                        <div class="breakdown-row">
                            <span class="breakdown-label">Total Horas Regulares</span>
                            <span class="breakdown-value">{{ number_format($employees->sum('hours'), 1) }}h</span>
                        </div>
                        <div class="breakdown-row">
                            <span class="breakdown-label">Total Horas Extra</span>
                            <span class="breakdown-value">{{ number_format($employees->sum('extra_hours'), 1) }}h</span>
                        </div>
                        <div class="breakdown-row">
                            <span class="breakdown-label">Total Trabajo Nocturno</span>
                            <span class="breakdown-value">{{ $employees->sum('night_days') }} días</span>
                        </div>
                        <div class="breakdown-row">
                            <span class="breakdown-label">Total Adicionales</span>
                            ₡<span class="breakdown-value currency"> {{ number_format($employees->sum('adicionales'), 2) }}</span>
                        </div>
                        <div class="breakdown-row">
                            <span class="breakdown-label">Total Rebajas</span>
                            ₡<span class="breakdown-value currency"> {{ number_format($employees->sum('rebajos'), 2) }}</span>
                        </div>
                        <div class="breakdown-row">
                            <span class="breakdown-label">Total CCSS</span>
                            ₡<span class="breakdown-value currency"> {{ number_format($employees->sum('ccss'), 2) }}</span>
                        </div>
                    @endif
                    <div class="breakdown-total">
                        <span class="breakdown-label">TOTAL GENERAL A PAGAR</span>
                        ₡<span class="breakdown-value currency"> {{ number_format($totals['total_salario'], 2) }}</span>
                    </div>
                </td>
            </tr>
        </tfoot>
        </table>
    </div>

    </div>
</body>
</html>
