import { defineConfig } from 'vite';
import laravel          from 'laravel-vite-plugin';
import { globSync }     from 'glob';

export default defineConfig( {
    plugins: [
        laravel( {
            input  : globSync( 'resources/{css,js}/*.{scss,css,js}', { ignore: 'resources/{css,js}/_*.{css,scss,js}' } ),
            refresh: true,
        } ),
    ],
} );
