<?php

use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\RegisterUserController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::get( '/', static function () {
    return Auth::user() ? view( 'dashboard' ) : view( 'welcome' );
} )->name( 'home' );

Route::middleware( 'auth' )->group( static function () {
    // Manage account settings
    Route::get( '/profile', [ ProfileController::class, 'edit' ] )->name( 'profile.edit' );
    Route::patch( '/profile', [ ProfileController::class, 'update' ] )->name( 'profile.update' );
    Route::get( '/password', [ PasswordController::class, 'edit' ] )->name( 'password.edit' );
    Route::patch( '/password', [ PasswordController::class, 'update' ] )->name( 'password.update' );

    Route::resource( 'company', CompanyController::class );
    Route::get( 'company/{company}/settings', [ CompanySettingsController::class, 'edit' ] )->name( 'company.settings.edit' );
    Route::patch( 'company/{company}/settings', [ CompanySettingsController::class, 'update' ] )->name( 'company.settings.update' );

    Route::resource( 'clients', ClientController::class );
//    Route::resource( 'invoices', InvoiceController::class );
//    Route::get( '/invoice/{invoice}/pdf', [ InvoiceController::class, 'generatePDF' ] )->name( 'invoice.pdf' );
//
//    Route::get( '/settings/invoice', [ InvoiceSettingController::class, 'edit' ] )->name( 'settings.invoice.edit' );
//    Route::put( '/settings/invoice', [ InvoiceSettingController::class, 'update' ] )->name( 'settings.invoice.update' );
} );

Route::middleware( 'guest' )->group( static function () {
    Route::get( '/register', [ RegisterUserController::class, 'create' ] )->name( 'register' );
    Route::post( '/register', [ RegisterUserController::class, 'store' ] )->name( 'register.store' );

    Route::get( '/login', [ SessionController::class, 'create' ] )->name( 'login' );
    Route::post( '/login', [ SessionController::class, 'store' ] )->name( 'login.store' );
} );

Route::delete( '/logout', [ SessionController::class, 'destroy' ] )->middleware( 'auth' )->name( 'logout' );
