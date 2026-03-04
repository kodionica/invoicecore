<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\InvoiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('companies', CompanyController::class);
    Route::post('companies/switch', [CompanySwitchController::class, 'switch']);

    Route::apiResource('clients', ClientController::class);

    Route::apiResource('invoices', InvoiceController::class);
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'generatePDF']);

    Route::put('profile', [ProfileController::class, 'update']);
    Route::put('password', [PasswordController::class, 'update']);
});
