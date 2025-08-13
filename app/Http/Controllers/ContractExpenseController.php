<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Project;
use App\Models\Contract;
use Illuminate\Http\Request;
use App\Models\ContractExpense;

class ContractExpenseController extends Controller
{
    public function index(Contract $contract){



        return view("filament.resources.projects.pages.ContractExpense", $contract);
  

        //$pdf = Pdf::loadView('pdf.example', ['timesheet' => $timesheets]);
        //return $pdf->download();
    }
}
