<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Project;

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