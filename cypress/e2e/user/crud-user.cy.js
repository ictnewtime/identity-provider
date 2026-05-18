/// <reference types="cypress" />

describe("Scenari di CRUD per gli Utenti", () => {
    // Dati di test. Usiamo un timestamp per rendere username e email unici ad ogni esecuzione.
    const timestamp = new Date().getTime();
    const newUser = {
        username: `testuser.${timestamp}`,
        email: `test.${timestamp}@example.com`,
        name: "Test",
        surname: "User",
        password: "Password123!",
    };

    // --- Funzioni Helper per azioni ripetute ---

    /**
     * Funzione helper per cercare un utente nella tabella.
     * @param {string} username - Lo username da cercare.
     */
    const searchUser = (username) => {
        cy.get('[data-cy="input-search-users"]').clear().type(username);
        // Attendiamo un istante per permettere alla tabella di aggiornarsi (debounce)
        cy.wait(500);
    };

    /**
     * Funzione helper per mostrare/nascondere gli utenti eliminati (Soft Delete).
     */
    const toggleShowDeletedUsers = () => {
        cy.get('[data-cy="btn-toggle-deleted-users"]').click();
        cy.wait(500);
    };

    beforeEach(() => {
        // Eseguiamo il login con le credenziali Admin tramite l'utility
        cy.login(Cypress.env("adminUsername") || "admin", Cypress.env("adminPassword") || "password");

        // Verifichiamo di essere atterrati al posto giusto
        cy.url().should("include", "/admin/users");
        cy.get('[data-cy="page-title-users"]').should("be.visible");
        cy.get('[data-cy="users-table-component"]').should("be.visible");
    });

    afterEach(() => {
        cy.logout();
    });

    it("CREATE: dovrebbe creare un nuovo utente", () => {
        cy.get('[data-cy="btn-new-user"]').click();

        // Verifica apertura modale
        cy.get('[data-cy="dialog-user-form"]').should("be.visible");

        // Riempimento del form
        cy.get('[data-cy="input-user-username"]').type(newUser.username);
        cy.get('[data-cy="input-user-email"]').type(newUser.email);
        cy.get('[data-cy="input-user-name"]').type(newUser.name);
        cy.get('[data-cy="input-user-surname"]').type(newUser.surname);

        // I campi password in PrimeVue sono wrappati, quindi cerchiamo l'input interno
        cy.get('[data-cy="input-user-password"] input').type(newUser.password);
        cy.get('[data-cy="input-user-password-confirmation"] input').type(newUser.password);

        // Gestione del campo data (potrebbe variare leggermente a seconda dell'implementazione di LocalizedDatePicker)
        cy.get('[data-cy="input-user-password-expires"] input').click();
        cy.get(".p-datepicker-year").select("2050");
        cy.get(".p-datepicker-month").select("Gennaio");
        cy.contains(".p-datepicker-calendar a", "1").click();

        cy.get('[data-cy="btn-submit-user-form"]').click();

        // Verifica del successo e chiusura modale
        cy.get('[data-cy="dialog-user-form"]').should("not.exist");
        cy.contains("Il nuovo utente è stato creato con successo").should("be.visible");

        // Verifichiamo che il nuovo utente sia presente nella tabella
        searchUser(newUser.username);
        cy.get('[data-cy="users-table"] tbody tr').should("contain", newUser.username);
    });

    it("READ: dovrebbe cercare e trovare un utente esistente", () => {
        const adminUsername = Cypress.env("adminUsername") || "admin";
        searchUser(adminUsername);

        // La tabella dovrebbe contenere una sola riga con l'utente cercato
        cy.get('[data-cy="users-table"] tbody tr').should("have.length", 1);
        cy.get('[data-cy="users-table"] tbody tr').first().should("contain", adminUsername);
    });

    it("UPDATE: dovrebbe modificare il nome di un utente e ripristinarlo", () => {
        const adminUsername = Cypress.env("adminUsername") || "admin";
        const originalName = Cypress.env("oldName") || "Admin";
        const newName = Cypress.env("newName") || "AdminUpdated";

        searchUser(adminUsername);

        // --- UPDATE ---
        cy.get(`[data-cy="btn-edit-user-${adminUsername}"]`).click();
        cy.get('[data-cy="dialog-user-form"]').should("be.visible");

        cy.get('[data-cy="input-user-name"]').clear().type(newName);
        cy.get('[data-cy="btn-submit-user-form"]').click();

        cy.get('[data-cy="dialog-user-form"]').should("not.exist");
        cy.contains("I dati dell'utente sono stati aggiornati").should("be.visible");

        // Verifica aggiornamento tabella
        cy.get('[data-cy="users-table"] tbody tr').should("contain", newName);

        // --- RESTORE NOME ORIGINALE ---
        cy.get(`[data-cy="btn-edit-user-${adminUsername}"]`).click();
        cy.get('[data-cy="dialog-user-form"]').should("be.visible");

        cy.get('[data-cy="input-user-name"]').clear().type(originalName);
        cy.get('[data-cy="btn-submit-user-form"]').click();

        cy.get('[data-cy="dialog-user-form"]').should("not.exist");
        cy.contains("I dati dell'utente sono stati aggiornati").should("be.visible");

        // Verifica ripristino tabella
        cy.get('[data-cy="users-table"] tbody tr').should("contain", originalName);
    });

    it("DELETE & RESTORE: dovrebbe eliminare un utente (soft delete) e poi ripristinarlo", () => {
        searchUser(newUser.username);

        // --- 1. DELETE (Soft Delete) ---
        cy.get(`[data-cy="btn-delete-user-${newUser.username}"]`).click();
        cy.get('[data-cy="dialog-delete-user"]').should("be.visible");

        // Clicca il pulsante di conferma dentro il dialog
        cy.get('[data-cy="dialog-delete-user"]')
            .contains("button", /Elimina|Delete/i)
            .click();

        cy.contains("Utente eliminato correttamente").should("be.visible");

        // Verifica che sia sparito dalla tabella principale
        cy.wait(500);
        cy.get('[data-cy="users-table"] tbody').should("not.contain", newUser.username);

        // --- 2. CHECK SOFT DELETE ---
        toggleShowDeletedUsers();
        searchUser(newUser.username);
        cy.get('[data-cy="users-table"] tbody tr').first().should("contain", newUser.username);

        // --- 3. RESTORE USER ---
        cy.get(`[data-cy="btn-restore-user-${newUser.username}"]`).click();
        cy.get('[data-cy="dialog-restore-user"]').should("be.visible");

        // Clicca il pulsante di conferma dentro il dialog
        cy.get('[data-cy="dialog-restore-user"]')
            .contains("button", /Ripristina|Restore/i)
            .click();

        cy.contains("Utente ripristinato con successo").should("be.visible");

        // --- 4. CHECK RESTORED ---
        toggleShowDeletedUsers(); // Ritorno alla vista tabella normale
        searchUser(newUser.username);
        cy.get('[data-cy="users-table"] tbody tr').first().should("contain", newUser.username);
    });
});
