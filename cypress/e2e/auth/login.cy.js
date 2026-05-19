/// <reference types="cypress" />

describe("Scenari di Autenticazione (Login/Logout)", () => {
    const adminUser = Cypress.env("adminUser");
    const guestUser = Cypress.env("guestUser");

    beforeEach(() => {
        cy.visit("/loginForm");
    });

    /**
     * Caso 1: Accesso con credenziali sbagliate.
     * Verifichiamo che, inserendo dati non validi, l'applicazione mostri un messaggio di errore
     * e l'utente rimanga sulla pagina di login.
     */
    it("should fail with wrong credentials and display an error message", () => {
        // Inseriamo credenziali volutamente errate
        cy.get('input[name="username"]').type("utente_sbagliato");
        cy.get('input[name="password"]').type("password_errata");

        // Sottomettiamo il form
        cy.get("form").submit();

        // Verifichiamo di essere ancora sulla pagina di login
        cy.url().should("include", "/loginForm");

        // Verifichiamo che il messaggio di errore specifico sia visibile.
        // La chiave 'auth.err-login' viene dal file `lang/it.json`.
        cy.contains("Credenziali non valide").should("be.visible");
    });

    /**
     * Caso 2: Accesso con credenziali admin corrette e successivo logout.
     * Verifichiamo che un utente amministratore possa accedere e venga reindirizzato
     * al pannello di amministrazione. Successivamente, testiamo il corretto funzionamento del logout.
     */
    it("should allow an admin user to log in and then log out", () => {
        // Inseriamo le credenziali dell'amministratore.
        // Utilizziamo Cypress.env() per recuperare le credenziali in modo sincrono.
        cy.get('input[name="username"]').type(adminUser.username);
        cy.get('input[name="password"]').type(adminUser.password);

        // Sottomettiamo il form
        cy.get("form").submit();

        // VERIFICA LOGIN:
        // Attendiamo il reindirizzamento e verifichiamo che l'URL sia quello del pannello admin.
        cy.url().should("include", "/admin/users");

        // Verifichiamo che un elemento chiave della pagina admin sia presente,
        // ad esempio il titolo "Gestione Utenti".
        cy.contains("h1", "Gestione Utenti").should("be.visible");

        // VERIFICA LOGOUT:
        // Cerchiamo e clicchiamo il pulsante di logout.
        cy.get('button[aria-label="Logout"]').click();

        // Verifichiamo di essere stati reindirizzati correttamente alla pagina di login.
        cy.url().should("include", "/loginForm"); // L'URL corretto della pagina di login

        // Verifichiamo che un elemento della pagina di login sia di nuovo visibile.
        cy.contains("button", "Login").should("be.visible");
    });

    /**
     * Caso 3: Accesso con utente valido ma non autorizzato (non admin).
     * Per questo test è necessario un utente specifico nel database.
     * L'utente 'user' con password 'password' esiste ma non ha ruoli sull'IdP.
     */
    it.skip("should redirect a non-admin user to the unauthorized page", () => {
        // Il test è marcato come .skip perché richiede dati specifici nel DB.
        // Rimuovi .skip quando l'utente 'user' è stato creato.
        cy.get('input[name="username"]').type(guestUser.username);
        cy.get('input[name="password"]').type(guestUser.password);
        cy.get("form").submit();

        // Verifichiamo il reindirizzamento alla pagina di "Accesso Negato".
        cy.url().should("include", "/unauthorized");
        cy.contains("h1", "Accesso Negato").should("be.visible");
    });
});
