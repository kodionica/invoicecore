<?php

use App\Http\Controllers\RegisterUserController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::get( '/', static function () {
    return view( 'welcome' );
} )->name( 'home' );

Route::middleware( 'guest' )->group( static function () {
    Route::get( '/register', [ RegisterUserController::class, 'create' ] )->name( 'register' );
    Route::post( '/register', [ RegisterUserController::class, 'store' ] )->name( 'register.store' );

    Route::get( '/login', [ SessionController::class, 'create' ] )->name( 'login' );
    Route::post( '/login', [ SessionController::class, 'store' ] )->name( 'login.store' );
} );

Route::delete( '/logout', [ SessionController::class, 'destroy' ] )->middleware( 'auth' )->name( 'logout' );
