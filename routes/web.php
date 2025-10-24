<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfController;
use Livewire\Livewire;

Route::get('/', function () {
    return redirect('/admin');
});

// Fix for missing POST authentication routes - Use Livewire's proper update route
Route::post('/admin/login', function () {
    return Livewire::update();
})->middleware(['web', 'throttle:60,1']);

Route::get('/generate-pdf/{id}', [PdfController::class, 'generate'])
    ->name('pdf.generate')
    ->middleware('auth');

Route::get('/salary-receipt/{id}', [PdfController::class, 'salaryReceipt'])
    ->name('salary-receipt.download')
    ->middleware('auth');

Route::get('/salary-receipt-bulk/{ids}', [PdfController::class, 'bulkSalaryReceipt'])
    ->name('salary-receipt.bulk-download')
    ->middleware('auth');