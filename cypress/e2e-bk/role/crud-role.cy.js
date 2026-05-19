/// <reference types="cypress" />

/// <reference types="cypress" />

describe("Scenari di CRUD per i Ruoli (Roles)", () => {
    // Generiamo un timestamp per avere dati di test sempre univoci e non sporcare il DB
    const timestamp = new Date().getTime();
    const adminUser = Cypress.env("adminUser");

    const newRole = {
        roleName: `admin-${timestamp}`,
        updatedRoleName: `superadmin-${timestamp}`,
        // NOTA: Affinché il test funzioni, questo provider deve esistere nel DB al momento del test.
        // Se non esiste, potresti dover creare prima il provider in un hook before() o usare un nome fisso noto.
        providerName: `localhost`,
    };

    // --- Funzioni Helper ---

    // Cerca un ruolo usando l'input di ricerca della DataTable
    const searchRole = (searchTerm) => {
        cy.get('[data-cy="input-search-roles"]').clear().type(searchTerm);
        cy.wait(500); // Attendiamo il debounce della ricerca
    };

    // --- SETUP ---
    beforeEach(() => {
        // Usiamo i Custom Command creati in precedenza
        cy.login(adminUser.username, adminUser.password);
        cy.visit("/admin/roles");

        // Assicuriamoci che la tabella sia visibile prima di iniziare il test
        cy.get('[data-cy="roles-table"]').should("be.visible");
    });

    afterEach(() => {
        cy.logout();
    });

    // --- TEST ---

    it("CREATE: dovrebbe aprire la modale, compilare il form con la dropdown e creare un nuovo ruolo", () => {
        cy.get('[data-cy="btn-new-role"]').click();

        // Verifica apertura modale
        cy.get('[data-cy="dialog-role-form"]').should("be.visible");

        // 1. Inserimento Nome Ruolo
        cy.get('[data-cy="input-role-name"]').type(newRole.roleName);

        // 2. Gestione Dropdown PrimeVue per il Provider
        // Apriamo il menu a tendina
        cy.get('[data-cy="select-role-provider"]').click();

        // Cerchiamo l'opzione usando l'attributo ARIA standard di PrimeVue (role="option")
        cy.get('[role="option"]').contains(newRole.providerName).click();

        // 3. Submit
        cy.get('[data-cy="btn-submit-role-form"]').click();

        // Verifica chiusura modale
        cy.get('[data-cy="dialog-role-form"]').should("not.exist");

        // Verifica presenza nella tabella
        searchRole(newRole.roleName);
        cy.get('[data-cy="roles-table"] tbody tr').should("contain", newRole.roleName);
        cy.get('[data-cy="roles-table"] tbody tr').should("contain", newRole.providerName);
    });

    it("READ: dovrebbe cercare e trovare il ruolo appena creato", () => {
        searchRole(newRole.roleName);

        // Ci aspettiamo esattamente 1 risultato
        cy.get('[data-cy="roles-table"] tbody tr').should("have.length", 1);
        cy.get('[data-cy="roles-table"] tbody tr').first().should("contain", newRole.roleName);

        // Pulizia campo di ricerca
        cy.get('[data-cy="input-search-roles"]').clear();
        cy.wait(500);
    });

    it("UPDATE: dovrebbe aprire la modale, modificare il nome del ruolo e salvare", () => {
        searchRole(newRole.roleName);

        // Troviamo il bottone di modifica dinamico nella riga corretta
        cy.contains('[data-cy="roles-table"] tbody tr', newRole.roleName).find('[data-cy^="btn-edit-role-"]').click();

        cy.get('[data-cy="dialog-role-form"]').should("be.visible");

        // Aggiorniamo il nome
        cy.get('[data-cy="input-role-name"]').clear().type(newRole.updatedRoleName);

        cy.get('[data-cy="btn-submit-role-form"]').click();

        // Verifica salvataggio
        cy.get('[data-cy="dialog-role-form"]').should("not.exist");

        // Verifichiamo il nuovo nome in tabella
        searchRole(newRole.updatedRoleName);
        cy.get('[data-cy="roles-table"] tbody tr').first().should("contain", newRole.updatedRoleName);
    });

    it("DELETE & RESTORE: dovrebbe eliminare un ruolo (soft delete) e poi ripristinarlo", () => {
        // Usiamo il nome aggiornato per la ricerca
        searchRole(newRole.updatedRoleName);

        // --- 1. DELETE ---
        cy.contains('[data-cy="roles-table"] tbody tr', newRole.updatedRoleName)
            .find('[data-cy^="btn-delete-role-"]')
            .click();

        cy.get('[data-cy="dialog-delete-role"]').should("be.visible");
        cy.get('[data-cy="btn-confirm-delete"]').click();

        // Verifica che sia sparito dalla vista normale
        cy.wait(500);
        searchRole(newRole.updatedRoleName);
        // Testo di fallback, adatta se diverso
        cy.get('[data-cy="roles-table"] tbody').should("contain", "Nessun ruolo trovato");

        // --- 2. CHECK SOFT DELETE ---
        // Mostriamo gli eliminati
        cy.get('[data-cy="btn-toggle-deleted-roles"]').click();
        cy.wait(500);

        searchRole(newRole.updatedRoleName);
        cy.get('[data-cy="roles-table"] tbody tr').first().should("contain", newRole.updatedRoleName);

        // --- 3. RESTORE ---
        cy.contains('[data-cy="roles-table"] tbody tr', newRole.updatedRoleName)
            .find('[data-cy^="btn-restore-role-"]')
            .click();

        cy.get('[data-cy="dialog-restore-role"]').should("be.visible");
        cy.get('[data-cy="btn-confirm-restore"]').click();

        // --- 4. VERIFICA FINALE ---
        // Nascondiamo gli eliminati (ritorno alla vista standard)
        cy.get('[data-cy="btn-toggle-deleted-roles"]').click();
        cy.wait(500);

        searchRole(newRole.updatedRoleName);
        cy.get('[data-cy="roles-table"] tbody tr').first().should("contain", newRole.updatedRoleName);
    });
});
