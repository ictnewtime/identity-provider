/// <reference types="cypress" />

describe("Scenari per le Sessioni", () => {
    const adminUser = Cypress.env("adminUser");
    const auditToFind = Cypress.env("auditToFind");

    beforeEach(() => {
        cy.intercept("POST", "**/v2/login").as("loginRequest");

        // 2. Lanciamo il comando di login (che preme il tasto submit)
        cy.login(adminUser.username, adminUser.password);

        // 3. Aspettiamo la chiamata, ma questa volta ci aspettiamo il 302 (Redirect)
        cy.wait("@loginRequest").its("response.statusCode").should("eq", 302);

        cy.url().should("include", "/admin/users");
        // 4. Solo a questo punto siamo sicuri al 100% di avere il cookie di sessione.
        // Adesso possiamo visitare la pagina in sicurezza!
        cy.visit("/admin/audits");

        // Verifica finale
        cy.get('[data-cy="audits-table"]').should("be.visible");
    });

    it("READ: dovrebbe leggere la prima riga e verificare username, action ed entity corretti", () => {
        // Carica i dati dell'utente admin

        // Assicuriamoci che la tabella sia visibile
        cy.get('[data-cy="audits-table"]').should("be.visible");

        // Aspettiamo che ci sia almeno una riga popolata nella tabella
        cy.get('[data-cy="audits-table"] tbody tr').should("have.length.at.least", 1);

        // Selezioniamo la prima riga e circoscriviamo i controlli
        cy.get('[data-cy="audits-table"] tbody tr')
            .first()
            .within(() => {
                // 1. Verifica lo Username
                cy.contains(adminUser.username).scrollIntoView().should("be.visible");
                cy.contains(auditToFind.action).scrollIntoView().should("be.visible");
                cy.contains(auditToFind.entity).scrollIntoView().should("be.visible");
            });
    });
});
