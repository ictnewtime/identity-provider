import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import tailwindcss from "@tailwindcss/vite";
import i18n from "laravel-vue-i18n/vite";

export default defineConfig({
    plugins: [
        tailwindcss(),
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        i18n(),
    ],
    resolve: {
        alias: {
            vue: "vue/dist/vue.esm-bundler.js",
        },
    },
    server: {
        host: "0.0.0.0",
        port: 5173,
        strictPort: true, // Se la porta è occupata, Vite va in errore invece di usarne un'altra
        cors: true, // FONDAMENTALE: Permette al browser di scaricare i file
        hmr: {
            host: "localhost",
        },
        watch: {
            usePolling: true, // FONDAMENTALE in Docker: forza Vite a controllare i file salvati
            interval: 500, // Controlla ogni mezzo secondo
        },
    },
});
