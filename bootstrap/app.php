<?php

use App\Http\Middleware\SetBodyClass;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure( basePath : dirname( __DIR__ ) )
    ->withRouting(
        web :      __DIR__ . '/../routes/web.php',
        api :      __DIR__ . '/../routes/api.php',
        commands : __DIR__ . '/../routes/console.php',
        health :   '/up',
    )
    ->withMiddleware( function ( Middleware $middleware ): void {
        // Define global middleware here
        // Obična definicija middleware-a
//        $middleware->append( SetBodyClass::class );

        // Definicija middleware-a za web jer imamo Auth, a Auth radi samo nakon što se inicijalizuje sesija što se dešava nakon web
        $middleware->web( append : SetBodyClass::class );

        // Enable Sanctum's SPA (cookie-based) authentication for API routes.
        $middleware->statefulApi();
    } )
    ->withExceptions( function ( Exceptions $exceptions ): void {
        //
    } )->create();
