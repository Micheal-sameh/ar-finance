import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        react(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
            'ziggy-js': path.resolve(__dirname, 'vendor/tightenco/ziggy'),
        },
    },
    optimizeDeps: {
        // FullCalendar's plugins (daygrid/timegrid/interaction/react) all
        // register view/plugin classes against a single @fullcalendar/core
        // instance. If Vite's dep optimizer bundles core separately per
        // entry, each plugin gets a different class identity and FullCalendar
        // throws "Class constructor ... cannot be invoked without 'new'" at
        // runtime. Forcing them into one pre-bundled chunk keeps a single
        // shared core instance across dev and build.
        include: ['@fullcalendar/core', '@fullcalendar/react', '@fullcalendar/daygrid', '@fullcalendar/timegrid', '@fullcalendar/interaction'],
    },
    server: {
        host: '127.0.0.1',
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
