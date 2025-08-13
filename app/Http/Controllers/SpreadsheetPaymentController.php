<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Project;
use App\Models\Contract;
use Illuminate\Http\Request;
use App\Models\ContractExpense;
use App\Models\Spreadsheet;

class SpreadsheetPaymentController extends Controller
{
    public function index(Spreadsheet $spreadsheet){



        return view("filament.resources.projects.pages.SpreadsheetEmployee", $spreadsheet);
  

        //$pdf = Pdf::loadView('pdf.example', ['timesheet' => $timesheets]);
        //return $pdf->download();
    }
}
