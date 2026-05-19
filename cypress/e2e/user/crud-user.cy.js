/// <reference types="cypress" />

describe("Scenari di CRUD per gli Utenti", () => {
    // Dati di test. Usiamo un timestamp per rendere username e email unici ad ogni esecuzione.
    const timestamp = new Date().getTime();
    const adminUser = Cypress.env("adminUser");
    const guestUser = Cypress.env("guestUser");
    const newUser = {
        username: `testuser.${timestamp}`,
        ...guestUser,
        username: `testuser.${timestamp}`,
        email: `test.${timestamp}@example.com`,
    };

    const searchUser = (username) => {
        cy.get('[data-cy="input-search-users"]').clear().type(username);
        cy.wait(500);
    };

    // Mostrare o nascondere gli utenti cancellati con il soft-delete
    const toggleShowDeletedUsers = () => {
        cy.get('[data-cy="btn-toggle-deleted-users"]').click();
        cy.wait(500);
    };

    beforeEach(() => {
        cy.intercept("POST", "**/v2/login").as("loginRequest");
        cy.login(adminUser.username, adminUser.password);
        cy.wait("@loginRequest").its("response.statusCode").should("eq", 302);
        cy.url().should("include", "/admin/users");
    });

    afterEach(() => {
        cy.logout();
        cy.wait(500);
    });

    // it("TRY login and logout", () => {
    //     cy.wait(500);
    // });

    it("CREATE: dovrebbe creare un nuovo utente", () => {
        cy.get('[data-cy="btn-new-user"]').click();

        // Verifica apertura modale
        cy.get('[data-cy="dialog-user-form"]').should("be.visible");

        // Riempimento del form
        cy.get('[data-cy="input-user-username"]').clear().type(newUser.username);
        cy.get('[data-cy="input-user-username"]').clear().type(newUser.username);

        cy.get('[data-cy="input-user-email"]').clear().type(newUser.email);
        cy.get('[data-cy="input-user-name"]').clear().type(newUser.name);
        cy.get('[data-cy="input-user-surname"]').clear().type(newUser.surname);

        // I campi password in PrimeVue sono wrappati, quindi cerchiamo l'input interno
        cy.get('[data-cy="input-user-password"] input').type(newUser.password);
        cy.get('[data-cy="input-user-password-confirmation"] input').type(newUser.password);

        // Gestione del campo data
        // Seleziona l'input, puliscilo, scrivi la data e premi Invio per confermare
        cy.get('[data-cy="input-expiration-date"] input').clear().type("01/01/2050{enter}");

        cy.get('[data-cy="input-user-surname"]').click();

        // Verifica del successo e chiusura modale
        cy.get('[data-cy="dialog-user-form"]').should("not.exist");

        // Verifichiamo che il nuovo utente sia presente nella tabella
        searchUser(newUser.username);
        cy.get('[data-cy="users-table"] tbody tr').should("contain", newUser.username);
    });

    it("READ: dovrebbe cercare e trovare un utente esistente", () => {
        searchUser(adminUser.username);

        // La tabella dovrebbe contenere almeno una riga con l'utente cercato
        cy.get('[data-cy="users-table"] tbody tr').should("have.length.gt", 0);
        cy.get('[data-cy="users-table"] tbody tr').first().should("contain", adminUser.username);
    });

    it("UPDATE: dovrebbe modificare il nome di un utente e ripristinarlo", () => {
        searchUser(adminUser.username);

        cy.get(`[data-cy="btn-edit-user-${adminUser.username}"]`).click();
        cy.get('[data-cy="dialog-user-form"]').should("be.visible");

        cy.get('[data-cy="input-user-name"]').clear().type(adminUser.newName);
        cy.get('[data-cy="input-user-name"]').clear().type(adminUser.newName);
        cy.get('[data-cy="btn-submit-user-form"]').click();

        cy.get('[data-cy="dialog-user-form"]').should("not.exist");
        cy.contains("I dati dell'utente sono stati aggiornati").should("have.length.gt", 0);

        // Restore del nome originale
        cy.get(`[data-cy="btn-edit-user-${adminUser.username}"]`).click();
        cy.get('[data-cy="dialog-user-form"]').should("be.visible");

        // Verifica aggiornamento tabella
        cy.get('[data-cy="input-user-name"] input').should("contain", adminUser.newName);

        cy.get('[data-cy="input-user-name"]').clear().type(adminUser.oldName);
        cy.get('[data-cy="input-user-name"]').clear().type(adminUser.oldName);
        cy.get('[data-cy="btn-submit-user-form"]').click();

        cy.get('[data-cy="dialog-user-form"]').should("not.exist");
        cy.contains("I dati dell'utente sono stati aggiornati").should("be.visible");

        // Verifica ripristino tabella
        cy.get('[data-cy="users-table"] tbody tr').should("have.length.gt", 0);
    });

    it("DELETE & RESTORE: dovrebbe eliminare un utente (soft delete) e poi ripristinarlo", () => {
        searchUser(newUser.username);

        // Effettuare la soft delete
        cy.get(`[data-cy="btn-delete-user-${newUser.username}"]`).click();
        cy.get('[data-cy="dialog-delete-user"]').should("be.visible");

        // Clicca il pulsante di conferma dentro il dialog
        cy.get('[data-cy="btn-confirm-delete-user"]').click();

        cy.contains("Utente eliminato correttamente").should("be.visible");

        // Verifica che sia sparito dalla tabella principale
        cy.wait(500);
        cy.get('[data-cy="users-table"] tbody').should("not.contain", newUser.username);

        // Check della soft delete
        toggleShowDeletedUsers();
        searchUser(newUser.username);
        cy.get('[data-cy="users-table"] tbody tr').first().should("contain", newUser.username);

        // Restore user
        cy.get(`[data-cy="btn-restore-user-${newUser.username}"]`).click();
        cy.get('[data-cy="dialog-restore-user"]').should("be.visible");

        // Clicca il pulsante di conferma dentro il dialog
        cy.get('[data-cy="btn-confirm-restore-user"]').click();

        // Check dell utente su cui è stato datto il restore
        // Ritorno alla vista tabella uitenti non cancellati
        toggleShowDeletedUsers();
        searchUser(newUser.username);
        cy.get('[data-cy="users-table"] tbody tr').first().should("contain", newUser.username);
    });
});
