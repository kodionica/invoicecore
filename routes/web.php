<?php

use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\InvoiceController;
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

    Route::resource( 'companies', CompanyController::class );
    Route::post( 'companies/switch', [ CompanySwitchController::class, 'switch' ] )->name( 'companies.switch' );

    Route::resource( 'clients', ClientController::class );
    Route::resource( 'invoices', InvoiceController::class );
    Route::get( 'invoices/{invoice}/pdf', [ InvoiceController::class, 'generatePDF' ] )->name( 'invoices.pdf' );
} );

Route::middleware( 'guest' )->group( static function () {
    Route::get( '/register', [ RegisterUserController::class, 'create' ] )->name( 'register' );
    Route::post( '/register', [ RegisterUserController::class, 'store' ] )->name( 'register.store' );

    Route::get( '/login', [ SessionController::class, 'create' ] )->name( 'login' );
    Route::post( '/login', [ SessionController::class, 'store' ] )->name( 'login.store' );
} );

Route::delete( '/logout', [ SessionController::class, 'destroy' ] )->middleware( 'auth' )->name( 'logout' );
