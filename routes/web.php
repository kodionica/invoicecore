<?php

use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app')->name('home');
Route::view('/{any}', 'app')->where('any', '.*');

//Route::view( '/', 'dashboard')->name( 'home');
//Route::apiResource('invoices', InvoiceController::class);
//Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'generatePDF'])->name( 'invoices.pdf');
