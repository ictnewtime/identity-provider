/// <reference types="cypress" />

describe("Scenari per le Sessioni", () => {
    const adminUser = Cypress.env("adminUser");
    const sessionToFind = Cypress.env("sessionToFind");

    beforeEach(() => {
        cy.intercept("POST", "**/v2/login").as("loginRequest");

        // 2. Lanciamo il comando di login (che preme il tasto submit)
        cy.login(adminUser.username, adminUser.password);

        // 3. Aspettiamo la chiamata, ma questa volta ci aspettiamo il 302 (Redirect)
        cy.wait("@loginRequest").its("response.statusCode").should("eq", 302);

        cy.url().should("include", "/admin/users");
        // 4. Solo a questo punto siamo sicuri al 100% di avere il cookie di sessione.
        // Adesso possiamo visitare la pagina in sicurezza!
        cy.visit("/admin/sessions");

        // Verifica finale
        cy.get('[data-cy="sessions-table"]').should("be.visible");
    });

    it("READ: dovrebbe leggere la prima riga e verificare la presenza dello username corretto", () => {
        // Recuperiamo l'oggetto adminUser dalle variabili di ambiente

        // Assicuriamoci che ci sia almeno una riga popolata nella tabella
        cy.get('[data-cy="sessions-table"] tbody tr').should("have.length.at.least", 1);

        // Selezioniamo la prima riga e circoscriviamo i controlli al suo interno
        cy.get('[data-cy="sessions-table"] tbody tr')
            .first()
            .within(() => {
                // 1. Verifica lo Username
                cy.contains(adminUser.username).scrollIntoView().should("be.visible");

                // 2. Verifica altri campi presenti nella DataTable (es. Provider)
                cy.contains(sessionToFind.providerName).scrollIntoView().should("be.visible");

                // Verifica la presenza del bottone di terminazione
                cy.get('[data-cy="btn-delete-session"]').scrollIntoView().should("be.visible");
            });
    });
});
