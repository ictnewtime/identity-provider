// ***********************************************************
// This example support/e2e.js is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
//
// You can change the location of this file or turn off
// automatically serving support files with the
// 'supportFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/configuration
// ***********************************************************

// Import commands.js using ES2015 syntax:
import "./commands";

// Aggiungiamo un hook `beforeEach` globale che si applica a tutti i test.
// Questo intercetta ogni richiesta in uscita e aggiunge l'header 'Accept-Language'.
// In questo modo, il backend Laravel saprà di dover rispondere sempre in italiano,
// risolvendo il problema dei messaggi di validazione in inglese.
beforeEach(() => {
    cy.intercept({ url: Cypress.config("baseUrl") + "/**", middleware: true }, (req) => {
        req.headers["Accept-Language"] = "it";
    });
});
