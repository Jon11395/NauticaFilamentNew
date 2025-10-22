<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfController;

Route::get('/', function () {
    return redirect('/admin');
});


Route::get('/generate-pdf/{id}', [PdfController::class, 'generate'])
    ->name('pdf.generate')
    ->middleware('auth');

Route::get('/salary-receipt/{id}', [PdfController::class, 'salaryReceipt'])
    ->name('salary-receipt.download')
    ->middleware('auth');

Route::get('/salary-receipt-bulk/{ids}', [PdfController::class, 'bulkSalaryReceipt'])
    ->name('salary-receipt.bulk-download')
    ->middleware('auth');