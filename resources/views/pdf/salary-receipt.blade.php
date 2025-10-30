<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de Salario</title>
    <style>
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            margin: 0;
            padding: 15px;
            font-size: 11px;
            line-height: 1.3;
            background-color: #ffffff;
        }
        
        .document-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border: 2px solid #2c3e50;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .header {
            background: #f8f9fa;
            color: #2c3e50;
            text-align: center;
            padding: 15px;
            border-bottom: 3px solid #2c3e50;
        }
        
        .company-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
            color: #2c3e50;
        }
        
        .document-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .period {
            font-size: 12px;
            color: #7f8c8d;
        }
        
        .content {
            padding: 15px;
        }
        
        .employee-info {
            background: #ecf0f1;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 4px solid #3498db;
        }
        
        .employee-name {
            font-size: 13px;
            font-weight: bold;
            color: #2c3e50;
            text-transform: uppercase;
        }
        
        .main-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 10px;
        }
        
        .main-table th {
            background: #34495e;
            color: white;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
        }
        
        .main-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .main-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .section-header {
            background: #3498db;
            color: white;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
            text-align: center;
        }
        
        .amount {
            text-align: right;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .net-pay-row {
            background: #2c3e50 !important;
            color: white !important;
            font-weight: bold;
            font-size: 12px;
        }
        
        .net-pay-row .amount {
            color: #ffffff !important;
            font-size: 14px;
        }
        
        .signature-section {
            margin-top: 20px;
            text-align: center;
            background: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
        }
        
        .signature-line {
            border-bottom: 2px solid #2c3e50;
            width: 200px;
            margin: 0 auto 10px auto;
            height: 20px;
        }
        
        .signature-labels {
            display: flex;
            justify-content: space-around;
            margin-top: 15px;
            font-size: 9px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .footer {
            margin-top: 15px;
            text-align: center;
            font-size: 9px;
            color: #7f8c8d;
            border-top: 1px solid #ecf0f1;
            padding-top: 10px;
        }
        
        
        .rates-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 10px;
        }
        
        .rate-item {
            background: #f8f9fa;
            padding: 8px;
            border-radius: 3px;
            border-left: 3px solid #3498db;
        }
        
        .rate-label {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .rate-value {
            color: #2c3e50;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="document-container">
        <div class="header">
            <div class="company-name">{{ $company_name }}</div>
            <div class="document-title">COMPROBANTE DE SALARIO</div>
            <div class="period">{{ $period }}</div>
        </div>
        
        <div class="content">
            <div class="employee-info">
                <div class="employee-name">{{ $employee_name }}</div>
            </div>
            
            @if($employee_type === 'hourly')
            <div class="rates-info">
                <div class="rate-item">
                    <div class="rate-label">Hora Normal</div>
                    <div class="rate-value">CRC {{ $hourly_rate }}</div>
                </div>
                <div class="rate-item">
                    <div class="rate-label">Hora Extra</div>
                    <div class="rate-value">CRC {{ $extra_hour_rate }}</div>
                </div>
            </div>
            @endif
            
            <table class="main-table">
                <thead>
                    <tr class="section-header">
                        <th colspan="2">SALARIO BRUTO</th>
                    </tr>
                </thead>
                <tbody>
                    @if($employee_type === 'fixed')
                    <tr>
                        <td>Salario Base</td>
                        <td class="amount">CRC {{ $base_salary }}</td>
                    </tr>
                    @endif
                    @if($employee_type === 'hourly')
                    <tr>
                        <td>Horas extras ({{ $extra_hours }} hrs)</td>
                        <td class="amount">CRC {{ $extra_hours_amount }}</td>
                    </tr>
                    <tr>
                        <td>Horas ordinarias ({{ $normal_hours }} hrs)</td>
                        <td class="amount">CRC {{ $normal_hours_amount }}</td>
                    </tr>
                    <tr>
                        <td>GUARDA ({{ $guard_days }} días)</td>
                        <td class="amount">CRC {{ $guard_amount }}</td>
                    </tr>
                    @endif
                    @if(($holiday_amount ?? 0) > 0)
                    <tr>
                        <td>Feriados / Adicionales</td>
                        <td class="amount">CRC {{ $holiday_amount }}</td>
                    </tr>
                    @endif
                    <tr style="background-color: #e8f4f8; font-weight: bold; border-top: 2px solid #3498db;">
                        <td>TOTAL SALARIO BRUTO</td>
                        <td class="amount">CRC {{ $gross_salary }}</td>
                    </tr>
                </tbody>
            </table>
            
            <table class="main-table">
                <thead>
                    <tr class="section-header">
                        <th colspan="2">DEDUCCIONES</th>
                    </tr>
                </thead>
                <tbody>
                    @if($employee_type === 'hourly' && isset($ccss_rate))
                    <tr>
                        <td>CCSS ({{ $ccss_rate }})</td>
                        <td class="amount">CRC {{ $ccss_amount }}</td>
                    </tr>
                    @elseif($ccss_amount > 0)
                    <tr>
                        <td>CCSS</td>
                        <td class="amount">CRC {{ $ccss_amount }}</td>
                    </tr>
                    @endif
                    @if(($rebates_amount ?? 0) > 0)
                    <tr>
                        <td>Rebajos / Préstamos</td>
                        <td class="amount">CRC {{ $rebates_amount }}</td>
                    </tr>
                    @endif
                    <tr style="background-color: #e8f4f8; font-weight: bold; border-top: 2px solid #3498db;">
                        <td>TOTAL DEDUCCIONES</td>
                        <td class="amount">CRC {{ $total_deductions }}</td>
                    </tr>
                </tbody>
            </table>
            
            <table class="main-table">
                <tbody>
                    <tr class="net-pay-row">
                        <td>NETO A PAGAR</td>
                        <td class="amount">CRC {{ $net_pay }}</td>
                    </tr>
                </tbody>
            </table>
            
            <div class="signature-section">
                <div style="margin-bottom: 20px; font-weight: bold; color: #2c3e50; font-size: 12px;">RECIBIDO CONFORME</div>
                
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
                    <tr>
                        <td style="text-align: center; width: 33%; padding: 5px;">
                            <div style="font-size: 9px; font-weight: bold; color: #2c3e50; margin-bottom: 8px;">NOMBRE</div>
                            <div class="signature-line" style="width: 150px; margin: 0 auto;"></div>
                        </td>
                        <td style="text-align: center; width: 33%; padding: 5px;">
                            <div style="font-size: 9px; font-weight: bold; color: #2c3e50; margin-bottom: 8px;">CEDULA</div>
                            <div class="signature-line" style="width: 150px; margin: 0 auto;"></div>
                        </td>
                        <td style="text-align: center; width: 33%; padding: 5px;">
                            <div style="font-size: 9px; font-weight: bold; color: #2c3e50; margin-bottom: 8px;">FIRMA</div>
                            <div class="signature-line" style="width: 150px; margin: 0 auto;"></div>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="footer">
                <p>Documento generado el {{ $generated_date }}</p>
            </div>
        </div>
    </div>
</body>
</html>