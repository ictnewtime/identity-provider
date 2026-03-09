import "./bootstrap";
import { createApp, h, warn } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";

// Import PrimeVue e i suoi stili
import PrimeVue from "primevue/config";
import { definePreset } from "@primeuix/themes";
import "primeicons/primeicons.css";
import Aura from "@primeuix/themes/aura";
import { i18nVue } from "laravel-vue-i18n";
import ToastService from "primevue/toastservice";
import Tooltip from "primevue/tooltip";
import "../css/app.css";

const BrandPreset = definePreset(Aura, {
    semantic: {
        info: {
            50: "#fffbeb",
            100: "#fef3c7",
            200: "#fde68a",
            300: "#fcd34d",
            400: "#fbbf24",
            500: "#f59e0b", // Il colore principale dell'icona
            600: "#d97706", // Il colore hover dell'icona
            700: "#b45309",
            800: "#92400e",
            900: "#78350f",
            950: "#451a03",
        },
        primary: {
            50: "#f0f9fd",
            100: "#e0f3fa",
            200: "#bae5f5",
            300: "#87ceeb",
            400: "#4eb4dd",
            500: "#3298c1", // Brand identity color
            600: "#267b9d", // Colore per l'hover del mouse
            700: "#20637f",
            800: "#1b526b",
            900: "#19455a",
            950: "#112d3d",
        },
    },
});

createInertiaApp({
    // Questa funzione dice a Inertia dove andare a pescare le pagine richieste dal backend
    resolve: (name) => {
        const pages = import.meta.glob("./Pages/**/*.vue", { eager: true });
        return pages[`./Pages/${name}.vue`];
    },

    // Il setup costruisce l'istanza Vue
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        // 1. Usa il plugin di Inertia
        app.use(plugin);

        // 2. Configura PrimeVue
        app.use(PrimeVue, {
            theme: {
                preset: BrandPreset,
                options: {
                    cssLayer: false,
                    darkModeSelector: "light",
                    prefix: "p",
                },
            },
        });

        app.use(i18nVue, {
            resolve: async (lang) => {
                const langs = import.meta.glob("../../lang/*.json");
                return await langs[`../../lang/${lang}.json`]();
            },
        });

        // 3. Configura il ToastService
        app.use(ToastService);

        app.directive("tooltip", Tooltip);

        // (Opzionale) Se hai un componente Notification che vuoi avere
        // DAVVERO ovunque senza importarlo ogni volta, puoi registrarlo qui:
        // import Notification from './components/Notification.vue';
        // app.component('notification', Notification);

        // 4. Monta l'app sull'elemento radice fornito da Inertia
        app.mount(el);
    },
});
