import { defineConfig } from "cypress";

export default defineConfig({
    component: {
        devServer: {
            framework: "vue",
            bundler: "vite",
        },
    },

    allowCypressEnv: true,

    // Impostazioni per la modalità "watch" (quando usi `cypress open`).
    // Questo è il fix definitivo per l'errore "EMFILE: too many open files".
    watchForFileChanges: true,
    watchOptions: {
        // Ignoriamo tutte le cartelle che contengono un numero enorme di file
        // e che non devono essere monitorate per le modifiche durante i test.
        ignored: [
            "**/node_modules/**",
            "**/.git/**",
            "**/vendor/**",
            "**/storage/**",
            "**/public/build/**",
            "**/bootstrap/cache/**",
        ],
    },

    e2e: {
        // Imposta l'URL di base della tua applicazione Laravel.
        // In questo modo nei test potrai usare `cy.visit('/')` invece dell'URL completo.
        // Assicurati che corrisponda alla porta su cui gira la tua app (es. `php artisan serve`).
        baseUrl: "http://localhost:8001",

        // Specifica dove si trovano i tuoi file di test.
        specPattern: "cypress/e2e/**/*.cy.{js,jsx,ts,tsx}",

        // Specifica dove si trovano i file di supporto (come commands.js).
        supportFile: "cypress/support/e2e.js",

        setupNodeEvents(on, config) {
            // Qui puoi implementare listener per eventi Node, se necessario in futuro.
            // Per ora, lo lasciamo vuoto.
        },
    },
});
