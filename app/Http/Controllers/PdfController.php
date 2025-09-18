<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Project;
use App\Models\Income;
use App\Models\Expense;
use App\Models\Contract;
use App\Models\Spreadsheet;


class PdfController extends Controller
{
    public function generate($id)
    {

        set_time_limit(300);

        $record = Project::findOrFail($id);

        // Load a Blade view and pass data + base64 image
        $pdf = Pdf::loadView('pdf.template', [
            'record' => $record,
            'recordImage' => $this->getLogo(),
            'incomes' => $this->getIncomes($id),
            'expenses' => $this->getExpenses($id),
            'contracts' => $this->getContracts($id),
            'spreadsheets' => $this->getSpreadsheets($id)
        ]);

        // Download the generated PDF
        return $pdf->stream("record_{$id}.pdf");
    }

    private function getLogo(){

        $imagePath = public_path('images/Logotipo_Editable .png'); // assuming $record->image is like 'images/logo.png'

        $base64Image = null;
    
        if (file_exists($imagePath)) {
            $type = pathinfo($imagePath, PATHINFO_EXTENSION);
            $data = file_get_contents($imagePath);
            $base64Image = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }

        return $base64Image;
    }

    private function getIncomes($id){

        $totalIncomeDeposited = Income::where('project_id', $id)->sum('total_deposited');
        $incomes = Income::where('project_id', $id)
            ->orderBy('date', 'desc')
            ->get(['bill_number', 'date', 'bill_amount', 'IVA', 'retentions', 'description', 'total_deposited'])
            ->toArray();
        //dump($totalIncomeDeposited);

        $incomes = [
            'totalIncomeDeposited' => $totalIncomeDeposited,
            'incomes' => $incomes,
        ];

        return $incomes;
    }

    private function getExpenses($id){

        $totalPaidExpenses = Expense::where('project_id', $id)
            ->where('type', 'paid')
            ->sum('amount');
        $totalUnpaidExpenses = Expense::where('project_id', $id)
            ->where('type', 'unpaid')
            ->sum('amount');
        $totalExpenses = Expense::where('project_id', $id)->sum('amount');
        $expenses = Expense::where('expenses.project_id', $id)
            ->join('providers', 'expenses.provider_id', '=', 'providers.id')
            ->join('expense_types', 'expenses.expense_type_id', '=', 'expense_types.id')
            ->orderBy('expenses.date', 'desc')
            ->get([
                'expenses.voucher',
                'expenses.date',
                'expenses.concept',
                'expenses.amount',
                'expenses.type',
                'providers.name as provider_name',
                'expense_types.name as expense_type_name',
            ])
            ->toArray();
        //dump($totalExpenses);

        $expenses = [
            'totalPaidExpenses' => $totalPaidExpenses,
            'totalUnpaidExpenses' => $totalUnpaidExpenses,
            'totalExpenses' => $totalExpenses,
            'expenses' => $expenses,
        ];

        return $expenses;
    }

    private function getContracts($id){

        $totalContractExpenses = Contract::join('contract_expenses as ce', 'ce.contract_id', '=', 'contracts.id')
            ->where('contracts.project_id', $id)
            ->sum('ce.total_deposited');


        $contract_expenses = Contract::with(['contractexpenses' => function($query) {
                $query->orderBy('date', 'desc'); // sort expenses by date
                $query->select('id', 'contract_id', 'voucher', 'date', 'concept', 'retentions', 'CCSS', 'total_deposited');
            }])
            ->where('project_id', $id)
            ->get()
            ->map(function($contract) {
                return [
                    'contract_name' => $contract->name ?? null, // optional
                    'expenses' => $contract->contractexpenses->toArray()
                ];
            })
            ->toArray();

        //dump($contract_expenses);

        $contracts = [
            'totalContractExpenses' => $totalContractExpenses,
            'contract_expenses' => $contract_expenses,
        ];

        return $contracts;
    }

    private function getSpreadsheets($projectId)
    {
        // Total of all payments for the project
        $totalSpreadsheetsPayments = Spreadsheet::join('payments as p', 'p.spreadsheet_id', '=', 'spreadsheets.id')
            ->where('spreadsheets.project_id', $projectId)
            ->sum('p.salary');

           
        $spreadsheet_payments = Spreadsheet::with(['payment.employee' => function($q) {
                $q->select('id', 'name'); // only select the employee columns we need
            }])
            ->where('project_id', $projectId)
            ->get()
            ->map(function($spreadsheet) {
                return [
                    'spreadsheet_date' => $spreadsheet->date ?? null,
                    'payments' => $spreadsheet->payment->map(function($payment) {
                        return [
                            'salary' => $payment->salary,
                            'description' => $payment->description,
                            'employee_name' => $payment->employee->name ?? '—'
                        ];
                    })->toArray()
                ];
            })
            ->toArray();


        //dump($spreadsheet_payments);
            

        $contracts = [
            'totalSpreadsheetsPayments' => $totalSpreadsheetsPayments,
            'spreadsheet_payments' => $spreadsheet_payments,
        ];

        return $contracts;

    }
}
