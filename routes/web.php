<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfController;

Route::get('/', function () {
    return redirect('/admin');
});


Route::get('/generate-pdf/{id}', [PdfController::class, 'generate'])
    ->name('pdf.generate')
    ->middleware('auth');