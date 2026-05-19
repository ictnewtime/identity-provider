/// <reference types="cypress" />

describe("Scenari di CRUD per Provider User Roles", () => {
    // Definizione dei dati di test.
    // Essendo relazioni, diamo per scontato che nel DB esistano già questi record base.
    const adminUser = Cypress.env("adminUser");
    const testData = {
        username: "admin",
        providerName: "localhost",
        roleName: "Admin",
        updatedRoleName: "User", // Un altro ruolo da usare per l'update
    };

    // Funzioni Helper

    // Cerca un'associazione usando l'input di ricerca della DataTable
    const searchProviderUserRole = (searchTerm) => {
        cy.get('[data-cy="input-provider-user-roles-search"]').clear().type(searchTerm);
        cy.wait(500); // Wait per il debounce della ricerca
    };

    // Helper per selezionare un'opzione da un Select PrimeVue con filtro
    const selectPrimeVueOption = (dataCy, optionText) => {
        cy.get(`[data-cy="${dataCy}"]`).click();
        cy.get(".p-select-overlay .p-select-option").contains(optionText).should("be.visible").click();
    };

    // SETUP
    beforeEach(() => {
        // Login come admin
        cy.login(adminUser.username, adminUser.password);
        cy.visit("/admin/provider-user-roles"); // Adegua l'URL alla tua route effettiva

        // Assicuriamoci che la tabella sia visibile prima di iniziare il test
        cy.get('[data-cy="provider-user-roles-table"]').should("be.visible");
    });

    afterEach(() => {
        cy.logout();
    });

    // TEST
    it("CREATE: dovrebbe aprire la modale, selezionare le entità e creare una nuova associazione", () => {
        cy.get('[data-cy="btn-new-provider-user-role"]').click();

        // Verifica che il form sia effettivamente renderizzato
        cy.get('[data-cy="provider-user-role-form"]').should("be.visible");

        // Selezioniamo l'Utente, il Provider e il Ruolo
        selectPrimeVueOption("select-provider-user-role-user", testData.username);
        selectPrimeVueOption("select-provider-user-role-provider", testData.providerName);

        // Aspettiamo che il dropdown dei ruoli si sblocchi in base al provider
        cy.get('[data-cy="select-provider-user-role-role"]').should("not.have.class", "p-disabled");
        selectPrimeVueOption("select-provider-user-role-role", testData.roleName);

        // Submit form
        cy.get('[data-cy="btn-submit-provider-user-role-form"]').click();

        // Verifica chiusura della modale
        cy.get('[data-cy="dialog-provider-user-role-form"]').should("not.exist");

        // Verifica che il nuovo record sia nella tabella (cerchiamo per username)
        searchProviderUserRole(testData.username);
        cy.get('[data-cy="provider-user-roles-table"] tbody tr')
            .should("contain", testData.username)
            .and("contain", testData.providerName)
            .and("contain", testData.roleName);
    });

    it("UPDATE: dovrebbe aprire la modale, modificare il ruolo e salvare", () => {
        searchProviderUserRole(testData.username);

        // Trova la riga corretta e clicca sul bottone di modifica
        cy.contains('[data-cy="provider-user-roles-table"] tbody tr', testData.username)
            .find('[data-cy="btn-edit-provider-user-role"]')
            .first()
            .click();

        cy.get('[data-cy="provider-user-role-form"]').should("be.visible");

        // Modifica il ruolo (l'utente e il provider potrebbero essere bloccati in update, testiamo solo il ruolo)
        selectPrimeVueOption("select-provider-user-role-role", testData.updatedRoleName);

        // Salva form
        cy.get('[data-cy="btn-submit-provider-user-role-form"]').click();

        // Verifica chiusura della modale
        cy.get('[data-cy="dialog-provider-user-role-form"]').should("not.exist");

        // Verifica l'aggiornamento
        searchProviderUserRole(testData.username);
        cy.get('[data-cy="provider-user-roles-table"] tbody tr').first().should("contain", testData.updatedRoleName);
    });

    it("READ: dovrebbe cercare e filtrare i record correttamente", () => {
        searchProviderUserRole(testData.username);

        // La tabella deve contenere almeno una riga e il testo cercato
        cy.get('[data-cy="provider-user-roles-table"] tbody tr').should("have.length.at.least", 1);
        cy.get('[data-cy="provider-user-roles-table"] tbody tr').first().should("contain", testData.username);

        // Pulisce il filtro
        cy.get('[data-cy="input-provider-user-roles-search"]').clear();
        cy.wait(500);
    });

    it("DELETE & RESTORE: dovrebbe eliminare un'associazione (soft delete) e ripristinarla", () => {
        searchProviderUserRole(testData.username);

        // 1. DELETE
        cy.contains('[data-cy="provider-user-roles-table"] tbody tr', testData.username)
            .find('[data-cy="btn-delete-provider-user-role"]')
            .first()
            .click();

        // Conferma nella Dialog
        cy.get('[data-cy="dialog-delete-provider-user-role"]').should("be.visible");
        cy.get('[data-cy="btn-confirm-delete-provider-user-role"]').click();

        cy.wait(500);

        // Verifica che non sia più visibile nei record attivi
        searchProviderUserRole(testData.username);
        cy.get('[data-cy="provider-user-roles-table"] tbody').should("not.contain", testData.roleName); // O verifica l'empty state

        // 2. CHECK SOFT DELETE
        cy.get('[data-cy="btn-toggle-deleted-provider-user-roles"]').click();
        cy.wait(500);

        searchProviderUserRole(testData.username);
        cy.get('[data-cy="provider-user-roles-table"] tbody tr').first().should("contain", testData.username);

        // 3. RESTORE
        cy.contains('[data-cy="provider-user-roles-table"] tbody tr', testData.username)
            .find('[data-cy="btn-restore-provider-user-role"]')
            .first()
            .click();

        // Conferma nella Dialog
        cy.get('[data-cy="dialog-restore-provider-user-role"]').should("be.visible");
        cy.get('[data-cy="btn-confirm-restore-provider-user-role"]').click();

        // 4. VERIFICA FINALE (Torna ai record attivi)
        cy.get('[data-cy="btn-toggle-deleted-provider-user-roles"]').click();
        cy.wait(500);

        searchProviderUserRole(testData.username);
        cy.get('[data-cy="provider-user-roles-table"] tbody tr').first().should("contain", testData.username);
    });
});
