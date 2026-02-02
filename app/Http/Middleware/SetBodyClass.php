<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetBodyClass {
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle( Request $request, Closure $next, string $type = null ): Response {
        $classes = [];

        $routeName = $request->route()?->getName();
//
        // Potrebno poraditi na nazivima ruta da bi ovo radilo kako treba
//        if ( str_starts_with( $routeName, 'admin.' ) ) {
//            $classes[] = 'admin';
//        } else {
//            $classes[] = 'front';
//        }

        $classes[] = str_replace( '.', '-', $routeName );

        if ( $type ) {
            $classes[] = $type;
        }

        if ( \Auth::check() ) {
            $classes[] = 'logged-in';
        } else {
            $classes[] = 'logged-out';
        }

        view()->share( 'body_class', implode( ' ', $classes ) );

        return $next( $request );
    }
}
