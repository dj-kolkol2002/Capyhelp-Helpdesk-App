import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        vue(),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    build: {
        rolldownOptions: {
            output: {
                manualChunks(id) {
                    if (!id.includes('node_modules')) {
                        return undefined;
                    }

                    if (id.includes('/vue/') || id.includes('/@inertiajs/') || id.includes('/vue-i18n/')) {
                        return 'vendor-vue';
                    }

                    if (id.includes('/@fortawesome/')) {
                        return 'vendor-icons';
                    }

                    if (id.includes('/laravel-echo/') || id.includes('/pusher-js/')) {
                        return 'vendor-realtime';
                    }

                    return 'vendor';
                },
            },
        },
    },
});
