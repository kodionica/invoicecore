<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CurrencyRateController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\SettingsController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\KpoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::get('/meta', MetaController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', static function ( Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('companies', CompanyController::class);
    Route::post('companies/switch', [CompanySwitchController::class, 'switch']);

    Route::apiResource('clients', ClientController::class);
    Route::get('currency-rates/{currency}/today', CurrencyRateController::class);

    Route::get('invoices/next-number', [InvoiceController::class, 'nextNumber']);
    Route::apiResource('invoices', InvoiceController::class);
    Route::get('invoices/{invoice}/history', [InvoiceController::class, 'history']);
    Route::patch('invoices/{invoice}/items/classifications', [InvoiceController::class, 'updateItemClassifications']);
    Route::get('invoices/{invoice}/pdf-versions', [InvoiceController::class, 'pdfVersions']);
    Route::get('invoices/{invoice}/pdf-versions/{version}/download', [InvoiceController::class, 'downloadPdfVersion']);
    Route::get('invoices/{invoice}/email-history', [InvoiceController::class, 'emailHistory']);
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'generatePDF']);
    Route::post('invoices/{invoice}/email', [InvoiceController::class, 'sendEmail']);

    Route::get('kpo', [KpoController::class, 'index']);
    Route::post('kpo', [KpoController::class, 'store']);
    Route::get('kpo/generation-preview', [KpoController::class, 'generationPreview']);
    Route::get('kpo/regeneration-preview', [KpoController::class, 'regenerationPreview']);
    Route::post('kpo/regenerate', [KpoController::class, 'regenerate']);
    Route::post('kpo/generate', [KpoController::class, 'generate']);
    Route::get('kpo/pdf', [KpoController::class, 'generatePDF']);
    Route::get('kpo/{kpo}/history', [KpoController::class, 'history']);
    Route::get('kpo/{kpo}', [KpoController::class, 'show']);
    Route::put('kpo/{kpo}', [KpoController::class, 'update']);
    Route::delete('kpo/{kpo}', [KpoController::class, 'destroy']);
    Route::post('kpo/{kpo}/remove-generated', [KpoController::class, 'removeGenerated']);

    Route::put('profile', [ProfileController::class, 'update']);
    Route::put('password', [PasswordController::class, 'update']);
    Route::get('settings', [SettingsController::class, 'show']);
    Route::put('settings', [SettingsController::class, 'update']);
});
