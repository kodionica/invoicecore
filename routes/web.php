<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceSettingController;
use App\Http\Controllers\RegisterUserController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::get( '/', static function () {
    return Auth::user() ? view( 'dashboard' ) : view( 'welcome' );
} )->name( 'home' );

Route::middleware( 'auth' )->group( static function () {
    Route::resource( 'clients', ClientController::class );
    Route::resource( 'invoices', InvoiceController::class );
    Route::get( '/invoice/{invoice}/pdf', [ InvoiceController::class, 'generatePDF' ] )->name( 'invoice.pdf' );

    Route::get( '/settings/invoice', [ InvoiceSettingController::class, 'edit' ] )->name( 'settings.invoice.edit' );
    Route::put( '/settings/invoice', [ InvoiceSettingController::class, 'update' ] )->name( 'settings.invoice.update' );
} );

Route::middleware( 'guest' )->group( static function () {
    Route::get( '/register', [ RegisterUserController::class, 'create' ] )->name( 'register' );
    Route::post( '/register', [ RegisterUserController::class, 'store' ] )->name( 'register.store' );

    Route::get( '/login', [ SessionController::class, 'create' ] )->name( 'login' );
    Route::post( '/login', [ SessionController::class, 'store' ] )->name( 'login.store' );
} );

Route::delete( '/logout', [ SessionController::class, 'destroy' ] )->middleware( 'auth' )->name( 'logout' );
