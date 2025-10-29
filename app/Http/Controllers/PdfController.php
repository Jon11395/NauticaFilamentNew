<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Project;
use App\Models\Payment;
use Carbon\Carbon;
use ZipArchive;
use Illuminate\Support\Facades\Storage;

class PdfController extends Controller
{
    public function generate($id)
    {
        ini_set("memory_limit", "512M");
        set_time_limit(300);

        // Eager load all necessary relationships in a single query
        $record = Project::with([
            'incomes' => fn($q) => $q->select('id', 'project_id', 'bill_number', 'date', 'bill_amount', 'IVA', 'retentions', 'description', 'total_deposited')->orderBy('date', 'desc'),
            'expenses' => fn($q) => $q->select('id', 'project_id', 'provider_id', 'expense_type_id', 'voucher', 'date', 'concept', 'amount', 'type')->orderBy('date', 'desc'),
            'expenses.provider:id,name',
            'expenses.expenseType:id,name',
            'contracts.contractexpenses' => fn($q) => $q->select('id', 'contract_id', 'voucher', 'date', 'concept', 'retentions', 'CCSS', 'total_deposited')->orderBy('date', 'desc'),
            'spreadsheets.payment' => fn($q) => $q->select('id', 'spreadsheet_id', 'employee_id', 'salary', 'description'),
            'spreadsheets.payment.employee:id,name'
        ])->findOrFail($id);

        // Process Incomes
        $totalIncomeDeposited = $record->incomes->sum('total_deposited');
        $incomesData = [
            'totalIncomeDeposited' => $totalIncomeDeposited,
            'incomes' => $record->incomes->toArray(),
        ];

        // Process Expenses
        $totalPaidExpenses = $record->expenses->where('type', 'paid')->sum('amount');
        $totalUnpaidExpenses = $record->expenses->where('type', 'unpaid')->sum('amount');
        $expensesData = [
            'totalPaidExpenses' => $totalPaidExpenses,
            'totalUnpaidExpenses' => $totalUnpaidExpenses,
            'totalExpenses' => $totalPaidExpenses + $totalUnpaidExpenses,
            'expenses' => $record->expenses->map(function ($expense) {
                return [
                    'voucher' => $expense->voucher,
                    'date' => $expense->date,
                    'concept' => $expense->concept,
                    'amount' => $expense->amount,
                    'type' => $expense->type,
                    'provider_name' => $expense->provider->name ?? null,
                    'expense_type_name' => $expense->expenseType->name ?? null,
                ];
            })->toArray(),
        ];

        // Process Contracts
        $totalContractExpenses = $record->contracts->flatMap->contractexpenses->sum('total_deposited');
        $contractsData = [
            'totalContractExpenses' => $totalContractExpenses,
            'contract_expenses' => $record->contracts->map(function ($contract) {
                return [
                    'contract_name' => $contract->name,
                    'expenses' => $contract->contractexpenses->toArray()
                ];
            })->toArray(),
        ];

        // Process Spreadsheets
        $totalSpreadsheetsPayments = $record->spreadsheets->flatMap->payment->sum('salary');
        $spreadsheetsData = [
            'totalSpreadsheetsPayments' => $totalSpreadsheetsPayments,
            'spreadsheet_payments' => $record->spreadsheets->map(function ($spreadsheet) {
                return [
                    'spreadsheet_date' => $spreadsheet->date,
                    'payments' => $spreadsheet->payment->map(function ($payment) {
                        return [
                            'salary' => $payment->salary,
                            'description' => $payment->description,
                            'employee_name' => $payment->employee->name ?? '—',
                        ];
                    })->toArray(),
                ];
            })->toArray(),
        ];

        // Load a Blade view and pass the processed data
        $pdf = Pdf::loadView('pdf.template', [
            'record' => $record,
            'recordImage' => $this->getLogo(),
            'incomes' => $incomesData,
            'expenses' => $expensesData,
            'contracts' => $contractsData,
            'spreadsheets' => $spreadsheetsData
        ]);

        // Download the generated PDF
        return $pdf->stream("record_{$id}.pdf");
    }

    public function salaryReceipt($id)
    {
        try {
            // Get payment with related data
            $payment = Payment::with(['employee', 'spreadsheet.project'])->findOrFail($id);
            $employee = $payment->employee;
            $spreadsheet = $payment->spreadsheet;
            
            // Calculate hourly rates and hours
            $hourlyRate = $employee->hourly_salary;
            $extraHourRate = $hourlyRate * 1.5;
            
            // Parse period to get actual date range
            $periodText = $spreadsheet->period ?? '';
            $startDate = Carbon::parse($spreadsheet->date);
            $endDate = Carbon::parse($spreadsheet->date)->addDays(13); // default fallback
            
            // Try to parse the period string if available
            if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})\s*-\s*(\d{1,2})\/(\d{1,2})\/(\d{4})/', $periodText, $matches)) {
                try {
                    $startDate = Carbon::createFromFormat('d/m/Y', trim($matches[1] . '/' . $matches[2] . '/' . $matches[3]));
                    $endDate = Carbon::createFromFormat('d/m/Y', trim($matches[4] . '/' . $matches[5] . '/' . $matches[6]));
                } catch (\Exception $e) {
                    // If parsing fails, use default
                    $startDate = Carbon::parse($spreadsheet->date);
                    $endDate = Carbon::parse($spreadsheet->date)->addDays(13);
                }
            }
            
            // Calculate hours from timesheets if available
            $timesheets = $employee->timesheets()
                ->where('project_id', $spreadsheet->project_id)
                ->whereBetween('date', [$startDate->startOfDay(), $endDate->endOfDay()])
                ->get();
            
            $normalHours = $timesheets->sum('hours');
            $extraHours = $timesheets->sum('extra_hours');
            $nightDays = $timesheets->where('night_work', true)->count();
            
            // Get base salary from payment record
            $baseSalary = $payment->salary ?? 0;
            
            // Determine employee type: hourly vs fixed based on payment description
            // Check if description contains "horas" (hourly) or "fija" (fixed)
            $description = $payment->description ?? '';
            $isHourlyDescription = str_contains(strtolower($description), 'horas');
            $isFixedDescription = str_contains(strtolower($description), 'fija');
            
            // Determine employee type
            if ($isHourlyDescription) {
                $employeeType = 'hourly';
            } elseif ($isFixedDescription) {
                $employeeType = 'fixed';
            } else {
                // Fallback: check timesheets
                $hasTimesheetData = $timesheets->count() > 0 && ($normalHours > 0 || $extraHours > 0);
                $employeeType = $hasTimesheetData ? 'hourly' : 'fixed';
            }
            
            // Calculate amounts
            $normalHoursAmount = $normalHours * $hourlyRate;
            $extraHoursAmount = $extraHours * $extraHourRate;
            $nightWorkBonus = \App\Models\GlobalConfig::getValue('night_work_bonus', 0);
            $guardAmount = $nightDays * $nightWorkBonus;
            
            // For fixed employees, use the base salary as the main amount
            if ($employeeType === 'fixed') {
                $grossSalary = $baseSalary + ($payment->additionals ?? 0);
            } else {
                $grossSalary = $normalHoursAmount + $extraHoursAmount + $guardAmount + ($payment->additionals ?? 0);
            }
            
            // Get CCSS from database
            $ccssAmount = $payment->ccss ?? 0;
            
            // Calculate total deductions
            $totalDeductions = $ccssAmount + ($payment->rebates ?? 0);
            
            // Calculate net pay
            $netPay = $grossSalary - $totalDeductions;
            
            // Format period text
            $periodText = $spreadsheet->period;
            
            // Parse date format like "07/10/2025 - 21/10/2025"
            if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})\s*-\s*(\d{1,2})\/(\d{1,2})\/(\d{4})/', $periodText, $matches)) {
                $startDay = $matches[1];
                $startMonth = $matches[2];
                $startYear = $matches[3];
                $endDay = $matches[4];
                $endMonth = $matches[5];
                $endYear = $matches[6];
                
                // Convert month numbers to Spanish month names
                $months = [
                    '01' => 'enero', '02' => 'febrero', '03' => 'marzo', '04' => 'abril',
                    '05' => 'mayo', '06' => 'junio', '07' => 'julio', '08' => 'agosto',
                    '09' => 'setiembre', '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre'
                ];
                
                $startMonthName = strtoupper($months[$startMonth] ?? 'mes');
                $endMonthName = strtoupper($months[$endMonth] ?? 'mes');
                
                $formattedPeriod = "Planilla del {$startDay} de {$startMonthName} al {$endDay} de {$endMonthName} del {$endYear}";
            }
            
            // Prepare data for PDF
            $data = [
                'company_name' => 'NAUTICA JJ S.A.',
                'employee_name' => $employee->name,
                'period' => $formattedPeriod,
                'hourly_rate' => number_format($hourlyRate, 2, ',', ' '),
                'extra_hour_rate' => number_format($extraHourRate, 2, ',', ' '),
                'normal_hours' => $normalHours,
                'normal_hours_amount' => number_format($normalHoursAmount, 2, ',', ' '),
                'extra_hours' => $extraHours,
                'extra_hours_amount' => number_format($extraHoursAmount, 2, ',', ' '),
                'guard_days' => $nightDays,
                'guard_amount' => number_format($guardAmount, 2, ',', ' '),
                'holiday_amount' => number_format($payment->additionals ?? 0, 2, ',', ' '),
                'base_salary' => number_format($baseSalary, 2, ',', ' '),
                'employee_type' => $employeeType,
                'gross_salary' => number_format($grossSalary, 2, ',', ' '),
                'ccss_rate' => $ccssAmount > 0 ? number_format(($ccssAmount / $grossSalary) * 100, 2, ',', ' ') . '%' : '0,00%',
                'ccss_amount' => number_format($ccssAmount, 2, ',', ' '),
                'rebates_amount' => number_format($payment->rebates ?? 0, 2, ',', ' '),
                'total_deductions' => number_format($totalDeductions, 2, ',', ' '),
                'net_pay' => number_format($netPay, 2, ',', ' '),
                'generated_date' => now()->format('d/m/Y H:i:s')
            ];
            
            // Generate PDF
            $pdf = Pdf::loadView('pdf.salary-receipt', $data)
                ->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);
            
            // Generate filename with generation date
            $timestamp = \Carbon\Carbon::now()->format('Y_m_d');
            $filename = str_replace(' ', '_', $employee->name) . '_' . $timestamp . '.pdf';
            
            // Return PDF download
            return $pdf->stream($filename);
            
        } catch (\Exception $e) {
            abort(500, 'Error al generar colilla: ' . $e->getMessage());
        }
    }

    public function bulkSalaryReceipt($ids)
    {
        try {
            $paymentIds = explode(',', $ids);
            $payments = Payment::with(['employee', 'spreadsheet.project'])->whereIn('id', $paymentIds)->get();
            
            if ($payments->isEmpty()) {
                abort(404, 'No se encontraron pagos para generar colillas');
            }
            
            // Create a temporary directory for PDFs
            $tempDir = storage_path('app/temp/colillas_' . now()->format('Y_m_d_H_i_s'));
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            $pdfFiles = [];
            
            foreach ($payments as $payment) {
                try {
                    $employee = $payment->employee;
                    $spreadsheet = $payment->spreadsheet;
                    
                    // Calculate hourly rates and hours
                    $hourlyRate = $employee->hourly_salary;
                    $extraHourRate = $hourlyRate * 1.5;
                    
                    // Parse period to get actual date range
                    $periodText = $spreadsheet->period ?? '';
                    $startDate = Carbon::parse($spreadsheet->date);
                    $endDate = Carbon::parse($spreadsheet->date)->addDays(13); // default fallback
                    
                    // Try to parse the period string if available
                    if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})\s*-\s*(\d{1,2})\/(\d{1,2})\/(\d{4})/', $periodText, $matches)) {
                        try {
                            $startDate = Carbon::createFromFormat('d/m/Y', trim($matches[1] . '/' . $matches[2] . '/' . $matches[3]));
                            $endDate = Carbon::createFromFormat('d/m/Y', trim($matches[4] . '/' . $matches[5] . '/' . $matches[6]));
                        } catch (\Exception $e) {
                            // If parsing fails, use default
                            $startDate = Carbon::parse($spreadsheet->date);
                            $endDate = Carbon::parse($spreadsheet->date)->addDays(13);
                        }
                    }
                    
                    // Calculate hours from timesheets if available
                    $timesheets = $employee->timesheets()
                        ->where('project_id', $spreadsheet->project_id)
                        ->whereBetween('date', [$startDate->startOfDay(), $endDate->endOfDay()])
                        ->get();
                    
                    $normalHours = $timesheets->sum('hours');
                    $extraHours = $timesheets->sum('extra_hours');
                    $nightDays = $timesheets->where('night_work', true)->count();
                    
                    // Get base salary from payment record
                    $baseSalary = $payment->salary ?? 0;
                    
                    // Determine employee type: hourly vs fixed based on payment description
                    // Check if description contains "horas" (hourly) or "fija" (fixed)
                    $description = $payment->description ?? '';
                    $isHourlyDescription = str_contains(strtolower($description), 'horas');
                    $isFixedDescription = str_contains(strtolower($description), 'fija');
                    
                    // Determine employee type
                    if ($isHourlyDescription) {
                        $employeeType = 'hourly';
                    } elseif ($isFixedDescription) {
                        $employeeType = 'fixed';
                    } else {
                        // Fallback: check timesheets
                        $hasTimesheetData = $timesheets->count() > 0 && ($normalHours > 0 || $extraHours > 0);
                        $employeeType = $hasTimesheetData ? 'hourly' : 'fixed';
                    }
                    
                    // Calculate amounts
                    $normalHoursAmount = $normalHours * $hourlyRate;
                    $extraHoursAmount = $extraHours * $extraHourRate;
                    $nightWorkBonus = \App\Models\GlobalConfig::getValue('night_work_bonus', 0);
                    $guardAmount = $nightDays * $nightWorkBonus;
                    
                    // For fixed employees, use the base salary as the main amount
                    if ($employeeType === 'fixed') {
                        $grossSalary = $baseSalary + ($payment->additionals ?? 0);
                    } else {
                        $grossSalary = $normalHoursAmount + $extraHoursAmount + $guardAmount + ($payment->additionals ?? 0);
                    }
                    
                    // Get CCSS from database
                    $ccssAmount = $payment->ccss ?? 0;
                    
                    // Calculate total deductions
                    $totalDeductions = $ccssAmount + ($payment->rebates ?? 0);
                    
                    // Calculate net pay
                    $netPay = $grossSalary - $totalDeductions;
                    
                    // Format period text
                    $periodText = $spreadsheet->period;// Default format
                    
                    // Parse date format like "07/10/2025 - 21/10/2025"
                    if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})\s*-\s*(\d{1,2})\/(\d{1,2})\/(\d{4})/', $periodText, $matches)) {
                        $startDay = $matches[1];
                        $startMonth = $matches[2];
                        $startYear = $matches[3];
                        $endDay = $matches[4];
                        $endMonth = $matches[5];
                        $endYear = $matches[6];
                        
                        // Convert month numbers to Spanish month names
                        $months = [
                            '01' => 'enero', '02' => 'febrero', '03' => 'marzo', '04' => 'abril',
                            '05' => 'mayo', '06' => 'junio', '07' => 'julio', '08' => 'agosto',
                            '09' => 'setiembre', '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre'
                        ];
                        
                        $startMonthName = strtoupper($months[$startMonth] ?? 'mes');
                        $endMonthName = strtoupper($months[$endMonth] ?? 'mes');
                        
                        $formattedPeriod = "Planilla del {$startDay} de {$startMonthName} al {$endDay} de {$endMonthName} del {$endYear}";
                    }
                    
                    // Prepare data for PDF
                    $data = [
                        'company_name' => 'NAUTICA JJ S.A.',
                        'employee_name' => $employee->name,
                        'period' => $formattedPeriod,
                        'hourly_rate' => number_format($hourlyRate, 2, ',', ' '),
                        'extra_hour_rate' => number_format($extraHourRate, 2, ',', ' '),
                        'normal_hours' => $normalHours,
                        'normal_hours_amount' => number_format($normalHoursAmount, 2, ',', ' '),
                        'extra_hours' => $extraHours,
                        'extra_hours_amount' => number_format($extraHoursAmount, 2, ',', ' '),
                        'guard_days' => $nightDays,
                        'guard_amount' => number_format($guardAmount, 2, ',', ' '),
                        'holiday_days' => $payment->additionals ?? 0,
                        'holiday_amount' => number_format($payment->additionals ?? 0, 2, ',', ' '),
                        'base_salary' => number_format($baseSalary, 2, ',', ' '),
                        'employee_type' => $employeeType,
                        'gross_salary' => number_format($grossSalary, 2, ',', ' '),
                        'ccss_rate' => $ccssAmount > 0 ? number_format(($ccssAmount / $grossSalary) * 100, 2, ',', ' ') . '%' : '0,00%',
                        'ccss_amount' => number_format($ccssAmount, 2, ',', ' '),
                        'rebates_amount' => number_format($payment->rebates ?? 0, 2, ',', ' '),
                        'total_deductions' => number_format($totalDeductions, 2, ',', ' '),
                        'net_pay' => number_format($netPay, 2, ',', ' '),
                        'generated_date' => now()->format('d/m/Y H:i:s')
                    ];
                    
                    // Generate PDF
                    $pdf = Pdf::loadView('pdf.salary-receipt', $data)
                        ->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);
                    
                    // Generate filename with generation date
                    $timestamp = \Carbon\Carbon::now()->format('Y_m_d');
                    $filename = str_replace(' ', '_', $employee->name) . '_' . $timestamp . '.pdf';
                    $filePath = $tempDir . '/' . $filename;
                    
                    // Save PDF to temporary directory
                    $pdf->save($filePath);
                    $pdfFiles[] = $filePath;
                    
                } catch (\Exception $e) {
                    // Log error but continue with other PDFs
                    \Log::error("Error generating colilla for payment {$payment->id}: " . $e->getMessage());
                }
            }
            
            if (empty($pdfFiles)) {
                abort(500, 'No se pudieron generar las colillas');
            }
            
            // Create ZIP file with generation date
            $generationDate = \Carbon\Carbon::now()->format('Y_m_d');
            $zipFilename = 'colillas_' . $generationDate . '.zip';
            $zipPath = storage_path('app/temp/' . $zipFilename);
            
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
                abort(500, 'No se pudo crear el archivo ZIP');
            }
            
            foreach ($pdfFiles as $pdfFile) {
                $zip->addFile($pdfFile, basename($pdfFile));
            }
            
            $zip->close();
            
            // Clean up temporary PDF files
            foreach ($pdfFiles as $pdfFile) {
                if (file_exists($pdfFile)) {
                    unlink($pdfFile);
                }
            }
            
            // Remove temporary directory
            if (is_dir($tempDir)) {
                rmdir($tempDir);
            }
            
            // Return ZIP file download
            return response()->download($zipPath, $zipFilename)->deleteFileAfterSend(true);
            
        } catch (\Exception $e) {
            abort(500, 'Error al generar colillas en lote: ' . $e->getMessage());
        }
    }

    private function getLogo()
    {
        $imagePath = public_path('images/Logotipo_Editable .png');
        if (!file_exists($imagePath)) {
            return null;
        }

        $type = pathinfo($imagePath, PATHINFO_EXTENSION);
        $data = file_get_contents($imagePath);
        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
}