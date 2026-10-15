import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

/*
 * Não usamos o download automático de fontes do plugin (fonts.bunny.net):
 * o build funciona offline e a interface usa a fonte do sistema operacional
 * (Inter quando instalada; caso contrário Segoe UI, Roboto, etc.).
 */
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
