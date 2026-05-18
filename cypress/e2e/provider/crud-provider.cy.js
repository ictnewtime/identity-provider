/// <reference types="cypress" />

describe("Scenari di CRUD per i Providers", () => {
    // Generiamo un timestamp per avere dati di test sempre univoci e non sporcare il DB
    const timestamp = new Date().getTime();
    const secretKey = Cypress.env("nonAdminPassword") || "secretKey";
    const newProvider = {
        name: `localhost-${timestamp}`,
        domain: `localhost`,
        updatedName: `localhost-${timestamp}-updated`,
        url: `http://localhost:8002`,
        protocol: "http",
        logoutUrl: "http://localhost:8002/logout",
        secret_key: secretKey,
    };

    // Funzioni Helper

    // Cerca un provider usando l'input di ricerca della DataTable
    const searchProvider = (searchTerm) => {
        cy.get('[data-cy="input-search-providers"]').clear().type(searchTerm);
        cy.wait(500);
    };

    // SETUP
    beforeEach(() => {
        // Usiamo i Custom Command creati in precedenza
        cy.login(Cypress.env("adminUsername") || "admin", Cypress.env("adminPassword") || "password");
        cy.visit("/admin/providers");

        // Assicuriamoci che la tabella sia visibile prima di iniziare il test
        cy.get(".p-datatable").should("be.visible");
    });

    afterEach(() => {
        cy.logout();
    });

    // TEST
    it("CREATE: dovrebbe aprire la modale, parsare l'URL e creare un nuovo provider", () => {
        cy.get('[data-cy="btn-new-provider"]').click();

        // Verifica che il form sia effettivamente renderizzato
        cy.get('[data-cy="provider-form"]').should("be.visible");

        // 1. Inseriamo il Nome e l'URL
        cy.get('[data-cy="input-provider-name"]').type(newProvider.name);
        cy.get('[data-cy="input-provider-url"]').type(newProvider.url);

        // 2. Clicchiamo il bottone "Sparkles" per parsare l'URL
        cy.get('[data-cy="btn-parse-url"]').click();

        // 3. VERIFICA: Controlliamo che il form abbia estratto e compilato i campi correttamente
        cy.get('[data-cy="input-provider-protocol"]').should("have.value", newProvider.protocol);
        cy.get('[data-cy="input-provider-domain"]').should("have.value", newProvider.domain);
        cy.get('[data-cy="input-provider-logout-url"]').should("have.value", newProvider.logoutUrl);

        // 4. Inseriamo la secret_key (visto che l'hai definita nei mock data)
        // Nota: se il Password component di PrimeVue nasconde l'input, aggiungiamo ' input' al selettore
        // Clicchiamo sul bottone del "dado" per generare una password automatica
        cy.get('[data-cy="btn-generate-secret"]').click();

        // Estraiamo il valore dell'input e verifichiamo che la lunghezza sia esattamente 32
        cy.get('[data-cy="input-provider-secret-key"] input').invoke("val").should("have.length", 32);

        // Puliamo il campo generato automaticamente e inseriamo la nostra chiave mockata
        cy.get('[data-cy="input-provider-secret-key"] input').clear().type(newProvider.secret_key);

        // (Opzionale ma consigliato) Verifichiamo che il valore finale sia quello che abbiamo digitato
        cy.get('[data-cy="input-provider-secret-key"] input').should("have.value", newProvider.secret_key);

        // Submit form
        cy.get('[data-cy="btn-submit-provider-form"]').click();

        // Verifica chiusura della modale
        cy.get('[data-cy="provider-form"]').should("not.exist");

        // Verifica che il nuovo record sia nella tabella
        searchProvider(newProvider.domain);
        cy.get('[data-cy="providers-table"] tbody tr').should("contain", newProvider.name);
    });

    it("UPDATE: dovrebbe aprire la modale, modificare il nome e salvare", () => {
        searchProvider(newProvider.domain);

        cy.contains('[data-cy="providers-table"] tbody tr', newProvider.domain)
            .find('[data-cy^="btn-edit-provider-"]')
            .click();

        cy.get('[data-cy="provider-form"]').should("be.visible");

        // Modifica campo
        cy.get('[data-cy="input-provider-name"]').clear().type(newProvider.updatedName);

        // Salva form
        cy.get('[data-cy="btn-submit-provider-form"]').click();

        cy.get('[data-cy="provider-form"]').should("not.exist");

        searchProvider(newProvider.domain);
        cy.get('[data-cy="providers-table"] tbody tr').first().should("contain", newProvider.updatedName);
    });

    it("READ: dovrebbe cercare e filtrare i provider correttamente", () => {
        searchProvider(newProvider.updatedName);

        cy.get('[data-cy="providers-table"] tbody tr').should("have.length", 1);
        cy.get('[data-cy="providers-table"] tbody tr').first().should("contain", newProvider.updatedName);

        cy.get('[data-cy="input-search-providers"]').clear();
        cy.wait(500);
    });

    it("DELETE & RESTORE: dovrebbe eliminare un provider (soft delete) e ripristinarlo", () => {
        searchProvider(newProvider.domain);

        // 1. DELETE (Usa il selettore che inizia per "btn-delete-provider-")
        cy.contains('[data-cy="providers-table"] tbody tr', newProvider.domain)
            .find('[data-cy^="btn-delete-provider-"]')
            .click();

        cy.get(".p-dialog").should("contain", newProvider.domain);
        // L'ideale sarebbe aggiungere data-cy="btn-confirm-delete" alla Dialog di conferma
        cy.get(".p-dialog")
            .contains("button", /Elimina|Delete/i)
            .click();

        cy.wait(500);
        searchProvider(newProvider.domain);
        cy.get('[data-cy="providers-table"] tbody').should("contain", "Nessun provider trovato");

        // 2. CHECK SOFT DELETE (Usa il nuovo data-cy per il toggle)
        cy.get('[data-cy="btn-toggle-deleted"]').click();
        cy.wait(500);

        searchProvider(newProvider.domain);
        cy.get('[data-cy="providers-table"] tbody tr').first().should("contain", newProvider.domain);

        // 3. RESTORE (Usa il selettore che inizia per "btn-restore-provider-")
        cy.contains('[data-cy="providers-table"] tbody tr', newProvider.domain)
            .find('[data-cy^="btn-restore-provider-"]')
            .click();

        cy.get(".p-dialog").should("be.visible");
        // L'ideale sarebbe aggiungere data-cy="btn-confirm-restore" alla Dialog di ripristino
        cy.get(".p-dialog")
            .contains("button", /Ripristina|Restore/i)
            .click();

        // 4. VERIFICA FINALE (Toggle back)
        cy.get('[data-cy="btn-toggle-deleted"]').click();
        cy.wait(500);

        searchProvider(newProvider.domain);
        cy.get('[data-cy="providers-table"] tbody tr').first().should("contain", newProvider.domain);
    });
});
