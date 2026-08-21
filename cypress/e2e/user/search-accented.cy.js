/// <reference types="cypress" />

/**
 * La ricerca deve trovare un nome accentato scritto in maiuscolo (punto TLE04).
 *
 * Perche' e' un test E2E e non di backend: i test di backend girano su sqlite, e sqlite e MariaDB
 * NON cercano allo stesso modo. Misurato il 2026-08-12 sulle due basi:
 *
 *     LIKE '%MARIO'  su  'Mario'  (con la O accentata)
 *       sqlite                 -> 0 righe
 *       MariaDB utf8mb4_unicode_ci -> 1 riga
 *
 * Su sqlite questo test fallirebbe per il MOTORE, non per il codice: sarebbe un rosso che non
 * indica niente. Qui gira contro MariaDB, che e' quello che c'e' in produzione.
 *
 * E' anche il test che mancava: la ricerca era coperta solo su sqlite, quindi la divergenza era
 * invisibile — un test che passa senza provare niente.
 */
describe("Ricerca utenti con caratteri accentati", () => {
    const timestamp = new Date().getTime();
    const utente = {
        username: `mariò.rossi_${timestamp}`,
        email: `mario_${timestamp}@example.com`,
        name: "Mariò",
        surname: "Rossi",
        password: Cypress.env("newUserPassword"),
    };

    before(() => {
        cy.visit("/loginForm");
        cy.get('input[name="username"]').type(Cypress.env("adminUsername"));
        cy.get('input[name="password"]').type(Cypress.env("adminPassword"), { log: false });
        cy.get("form").submit();
        cy.url().should("include", "/admin");
    });

    it("trova un nome accentato cercandolo in MAIUSCOLO", () => {
        cy.visit("/admin/users");

        // Prima si crea l'utente con l'accento, poi lo si cerca in maiuscolo: e' la coppia che
        // su sqlite si spezzerebbe.
        cy.get("#btn-create-user").click();
        cy.get('input[name="username"]').type(utente.username);
        cy.get('input[name="email"]').type(utente.email);
        cy.get('input[name="name"]').type(utente.name);
        cy.get('input[name="surname"]').type(utente.surname);
        cy.get("form").submit();

        // L'intercettazione filtra sul parametro `q`, e non e' pignoleria: la lista si ricarica anche
        // dopo il salvataggio del form qui sopra, con lo stesso indirizzo e `q` vuoto. Senza il
        // filtro, l'attesa si sarebbe chiusa su QUELLA richiesta — cioe' su una condizione
        // osservabile sbagliata, che e' il modo elegante di avere lo stesso difetto di prima.
        const cercato = utente.username.toUpperCase();
        cy.intercept({ method: "GET", url: "**/admin/v1/users*", query: { q: cercato } }).as("ricercaUtenti");

        cy.get("#user-search").clear().type(cercato);

        // Il debounce resta 500 ms, ed e' giusto: e' comportamento dell'applicazione, non del test.
        // Cypress aspetta la richiesta fino al suo timeout, quindi un ritardo non fa piu' fallire niente.
        cy.wait("@ricercaUtenti").its("response.statusCode").should("eq", 200);

        cy.contains(utente.username).should("be.visible");
    });
});
