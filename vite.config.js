import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js'
            ],
            refresh: true,
        }),
    ],
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        headers: {
            'X-Content-Type-Options': 'nosniff',
        },
        hmr: {
            host: '127.0.0.1',
        },
    },
    resolve: {
        alias: {
            '~admin-lte': 'admin-lte',
            '~jquery': 'jquery',
        }
    }
});
