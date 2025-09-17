<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>Resumen - Record #{{ $record->id }}</title>
    <style>

        html, body { 
            font-family: "DejaVu Sans", sans-serif; 
            font-size: 12px; 
            line-height: 1.4;
            color: #222; 
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

        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { padding: 6px 8px; border: 1px solid #e8e8e8; vertical-align: top; font-size: 11px; }
        th { 
            background: #f7f7f7; 
            font-weight: 600; 
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        tbody tr:nth-child(even) { background: #fafafa; }

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
                <!-- Left section: Project info -->
                <td style="vertical-align: middle; border: none;">
                    <strong style="font-size: 16px;">Proyecto - {{ $record->name }}</strong><br>
                    <span style="color: #666; font-size: 12px;">Resumen financiero</span>
                </td>
    
                <!-- Right section: Logo -->
                <td style="text-align: right; vertical-align: middle; border: none;">
                    @if($recordImage)
                        <img src="{{ $recordImage }}" alt="Logo" width="100" style="height: auto; border: none;">
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
    <h1>Resumen del registro</h1>
    <p class="meta">Nombre: {{ $record->name }}</p>
    <p class="meta">
        Fecha inicio: {{ $record->start_date ? \Carbon\Carbon::parse($record->start_date)->format('d-m-Y') : '—' }}
    </p>
    
    <p class="meta">
        Monto oferta: ₡ {{ number_format($record->offer_amount, 2, ',', '.') }} 
    </p>
    @php
        switch($record->status) {
            case 'in_progress':
                $statusText = 'En progreso';
                $statusClass = 'color:orange;'; // yellow
                break;
            case 'stopped':
                $statusText = 'Detenido';
                $statusClass = 'color:red;';// red
                break;
            case 'finished':
                $statusText = 'Terminado';
                $statusClass = 'color:green;'; // green
                break;
            default:
                $statusText = $record->status;
                $statusClass = 'color:yellow;';
        }
    @endphp
    <p class="meta" style="{{ $statusClass }}">Status: {{ $statusText }}</p>

    {{-- =========================
         1) Totales
         ========================= --}}


    <div class="section">
        <h2>Totales</h2>
        <table>
            <table>
                <thead>
                    <tr>
                        <th>Concepto</th>
                        <th class="right">Detalle</th>
                        <th class="right">Total (CRC)</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Ingresos row (2 columns, span the second for consistency) -->
                    <tr>
                        <td>Ingresos</td>
                        <td></td>
                        <td colspan="2" class="right">{{ number_format($incomes['totalIncomeDeposited'], 2, ',', '.') }}</td>
                    </tr>
                    <!-- Gastos row with 3 columns -->
                    <tr>
                        <td>Gastos</td>
                        <td class="right">
                            <div>Pagados: {{ number_format($expenses['totalPaidExpenses'], 2, ',', '.') }}</div>
                            <div>Pendientes: {{ number_format($expenses['totalUnpaidExpenses'], 2, ',', '.') }}</div>
                        </td>
                        <td class="right">{{ number_format($expenses['totalExpenses'], 2, ',', '.') }}</td>
                    </tr>
                    <!-- Pagos en Contratos row -->
                    <tr>
                        <td>Pagos en Contratos</td>
                        <td></td>
                        <td colspan="2" class="right">{{ number_format($contracts['totalContractExpenses'], 2, ',', '.') }}</td>
                    </tr>
                    <!-- Pagos en Planillas row -->
                    <tr>
                        <td>Pagos en Planillas</td>
                        <td></td>
                        <td colspan="2" class="right">{{ number_format($spreadsheets['totalSpreadsheetsPayments'], 2, ',', '.') }}</td>
                    </tr>
                    <!-- Balance neto row -->
                    <tr class="summary-row">
                        <td>Balance neto (Ingresos - Gastos)</td>
                        <td></td>
                        @php
                            $balanceNeto = $incomes['totalIncomeDeposited'] 
                                            - $expenses['totalExpenses'] 
                                            - $contracts['totalContractExpenses'] 
                                            - $spreadsheets['totalSpreadsheetsPayments'];
                        @endphp
                        <td colspan="2" class="right">
                            @if($balanceNeto >= 0)
                                <span style="color:green;">{{ number_format($balanceNeto, 2, ',', '.') }}</span>
                            @else
                                <span style="color:red;">{{ number_format($balanceNeto, 2, ',', '.') }}</span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
            
        
    </div>

    {{-- SALTO DE PÁGINA PARA DETALLES --}}
    <div class="page-break"></div>

    {{-- =========================
         2) Ingresos
         ========================= --}}
    <div class="section">
        <h2>Ingresos ({{ count($incomes['incomes']) }})</h2>
        <table>
            <thead>
                <tr>
                    <th>No. Factura</th>
                    <th>Fecha</th>
                    <th>Descripción</th>
                    <th class="right">Monto (CRC)</th>
                    <th class="right">IVA</th>
                    <th class="right">Retenciones</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody>

                @php
                    $totalDeposited = collect($incomes['incomes'])->sum('total_deposited');
                @endphp

                @forelse($incomes['incomes'] as $ing)
                    <tr>
                        <td>{{ $ing['bill_number'] ?? '—' }}</td>
                        <td>{{ \Carbon\Carbon::parse($ing['date'])->format('d-m-Y') }}</td>
                        <td>{{ $ing['description'] ?? '—' }}</td>
                        <td class="right">{{ number_format($ing['bill_amount'], 2, ',', '.') }}</td>
                        <td class="right">{{ number_format($ing['IVA'], 2, ',', '.') }}</td>
                        <td class="right">{{ number_format($ing['retentions'], 2, ',', '.') }}</td>
                        <td class="right">{{ number_format($ing['total_deposited'], 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="muted" style="text-align: center;">No hay ingresos registrados.</td>
                    </tr>
                @endforelse

                <!-- Total row -->
                @if(count($incomes['incomes']) > 0)
                    <tr class="summary-row">
                        <td colspan="6" class="text-right"><strong>Total</strong></td>
                        <td class="right"><strong>{{ number_format($totalDeposited, 2, ',', '.') }}</strong></td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
    

    {{-- =========================
         3) Gastos
         ========================= --}}
    <div class="section">
        <h2>Gastos ({{ count($expenses['expenses']) }})</h2>
        <table>
            <thead>
                <tr>
                    <th>No. Factura</th>
                    <th>Fecha</th>
                    <th>Proveedor</th>
                    <th>Concepto</th>
                    <th class="right">Monto (CRC)</th>
                    <th class="text-center">Estado</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalAmount = collect($expenses['expenses'])->sum('amount');
                @endphp
                @forelse($expenses['expenses'] as $g)
                    <tr>
                        <td>{{ $g['voucher'] }}</td>
                        <td>{{ \Carbon\Carbon::parse($g['date'])->format('d-m-Y') }}</td>
                        <td>{{ $g['provider_name'] ?? '—' }}</td>
                        <td>{{ $g['concept'] ?? '—' }}</td>
                        <td class="right">{{ number_format($g['amount'], 2, ',', '.') }}</td>
                        <td class="text-center">
                            @if($g['type'] === 'paid')
                                <span class="estado-pagado">Pagado</span>
                            @else
                                <span class="estado-pendiente">Pendiente</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="muted" style="text-align: center;">No hay gastos registrados.</td>
                    </tr>
                @endforelse

                <!-- Total row -->
                @if(count($expenses['expenses']) > 0)
                    <tr class="summary-row">
                        <td colspan="4" class="text-right"><strong>Total</strong></td>
                        <td class="right"><strong>{{ number_format($totalAmount, 2, ',', '.') }}</strong></td>
                        <td></td>
                    </tr>
                @endif
            </tbody>
            
        </table>
    </div>
    

    {{-- =========================
         4) Contratos
         ========================= --}}
         <div class="section">
            <h2>Contratos ({{ count($contracts['contract_expenses']) }})</h2>
        
            @forelse($contracts['contract_expenses'] as $contrato)
                <h3>
                    {{ $contrato['contract_name'] ?? 'Contrato' }}
                    ({{ count($contrato['expenses']) }})
                </h3>
        
                @if(!empty($contrato['expenses']))

                    @php
                        $totalContractDeposited = collect($contrato['expenses'])->sum('total_deposited');
                    @endphp

                    <table style="margin-bottom:8px;">
                        <thead>
                            <tr>
                                <th>No. Factura<</th>
                                <th>Fecha</th>
                                <th>Concepto</th>
                                <th>Retenciones</th>
                                <th>CCSS</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>

                            
                            @foreach($contrato['expenses'] as $expense)
                                <tr>
                                    <td>{{ $expense['voucher'] }}</td>
                                    <td>{{ \Carbon\Carbon::parse($expense['date'])->format('d-m-Y') }}</td>
                                    <td>{{ $expense['concept'] }}</td>
                                    <td>{{ number_format($expense['retentions'], 2, ',', '.') }}</td>
                                    <td>{{ number_format($expense['CCSS'], 2, ',', '.') }}</td>
                                    <td>{{ number_format($expense['total_deposited'], 2, ',', '.') }}</td>
                                </tr>
                            @endforeach

                            <!-- Total row -->
                            <tr class="summary-row">
                                <td colspan="5" class="text-right"><strong>Total</strong></td>
                                <td class="right"><strong>{{ number_format($totalContractDeposited, 2, ',', '.') }}</strong></td>
                            </tr>

                        </tbody>
                    </table>
                @else
                    <p class="muted" style="text-align: center;">No hay gastos registrados para este contrato.</p>
                @endif
        
            @empty
                <p class="muted">No hay contratos registrados.</p>
            @endforelse
        </div>
        
   

    {{-- =========================
         5) Planillas
         ========================= --}}
         <div class="section">
            <h2>Planillas ({{ count($spreadsheets['spreadsheet_payments']) }})</h2>
        
            @forelse($spreadsheets['spreadsheet_payments'] as $planilla)
                <h3>
                    {{ $planilla['spreadsheet_date'] ? \Carbon\Carbon::parse($planilla['spreadsheet_date'])->format('d-m-Y') : '—' }}
                    ({{ count($planilla['payments']) }} pagos)
                </h3>
        
                @if(!empty($planilla['payments']))

                    @php
                        $totalSalary = collect($planilla['payments'])->sum('salary');
                    @endphp

                    <table style="margin-bottom:8px;">
                        <thead>
                            <tr>
                                <th>Empleado</th>
                                <th>Descripción</th>
                                <th>Salario</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($planilla['payments'] as $payment)
                                <tr>
                                    <td>{{ $payment['employee_name'] }}</td>
                                    <td>{{ $payment['description'] }}</td>
                                    <td>{{ number_format($payment['salary'], 2, ',', '.') }}</td>
                                </tr>
                            @endforeach

                            <!-- Total row -->
                            <tr class="summary-row">
                                <td colspan="2" class="text-right"><strong>Total</strong></td>
                                <td class="right"><strong>{{ number_format($totalSalary, 2, ',', '.') }}</strong></td>
                            </tr>

                        </tbody>
                    </table>
                @else
                    <p class="muted" >No hay pagos registrados para esta planilla.</p>
                @endif
        
            @empty
                <p class="muted">No hay planillas registradas.</p>
            @endforelse
        </div>
        
</div>
</body>
</html>
