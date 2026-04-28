import { defineConfig } from 'vite';
import react            from '@vitejs/plugin-react';
import tailwindcss      from '@tailwindcss/vite';
import laravel          from 'laravel-vite-plugin';

export default defineConfig( {
    plugins: [
        laravel( {
            input  : [
                'resources/js/main.tsx',
                // 'resources/css/invoice.scss',
            ],
            ssr    : 'resources/js/ssr/renderInvoiceDocument.tsx',
            refresh: true,
        } ),
        react(),
        tailwindcss(),
    ],
    server : {
        host      : '0.0.0.0',
        port      : 5173,
        strictPort: true,
        origin    : 'https://invoicecore.ddev.site:5173',
        hmr       : {
            host    : 'invoicecore.ddev.site',
            protocol: 'wss',
        },
        cors      : {
            origin     : 'https://invoicecore.ddev.site',
            credentials: true,
        },
    },
} );
