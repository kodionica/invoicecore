<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\RegisterUserController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::get( '/', static function () {
    return Auth::user() ? view( 'dashboard' ) : view( 'welcome' );
} )->name( 'home' );

Route::resource( 'clients', ClientController::class )->middleware( 'auth' );
Route::resource( 'invoices', InvoiceController::class )->middleware( 'auth' );

Route::middleware( 'guest' )->group( static function () {
    Route::get( '/register', [ RegisterUserController::class, 'create' ] )->name( 'register' );
    Route::post( '/register', [ RegisterUserController::class, 'store' ] )->name( 'register.store' );

    Route::get( '/login', [ SessionController::class, 'create' ] )->name( 'login' );
    Route::post( '/login', [ SessionController::class, 'store' ] )->name( 'login.store' );
} );

Route::delete( '/logout', [ SessionController::class, 'destroy' ] )->middleware( 'auth' )->name( 'logout' );
