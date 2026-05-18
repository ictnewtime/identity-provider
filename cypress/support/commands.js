// cypress/support/commands.js

/**
 * Comando custom per effettuare il Login.
 */
Cypress.Commands.add("login", (username, password) => {
    cy.visit("/loginForm");

    cy.get('input[name="username"]').clear().type(username);
    cy.get('input[name="password"]').clear().type(password, { log: false });

    // Sottomettiamo il form come fai nel tuo test
    cy.get("form").submit();
});

/**
 * Comando custom per effettuare il Logout.
 */
Cypress.Commands.add("logout", () => {
    // Clicchiamo sul pulsante di logout usando il tuo selettore
    cy.get('button[aria-label="Logout"]').click();

    // Verifichiamo che il logout sia effettivo controllando il redirect e il bottone
    cy.url().should("include", "/loginForm");
    cy.contains("button", "Login").should("be.visible");
});
