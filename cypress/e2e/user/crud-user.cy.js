/// <reference types="cypress" />

describe("Scenari di CRUD per gli Utenti", () => {
    // Dati di test. Usiamo un timestamp per rendere username e email unici ad ogni esecuzione.
    const timestamp = new Date().getTime();
    const newUser = {
        username: `testuser_${timestamp}`,
        email: `test_${timestamp}@example.com`,
        name: "Test",
        surname: "User",
        // Generata da scripts/prepare-e2e-credentials.sh: nessuna password letterale nei test.
        password: Cypress.env("newUserPassword"),
    };

    // --- Funzioni Helper per azioni ripetute ---

    // Contatore per dare a ogni ricerca un alias suo. Sei chiamate condividono questo helper, e sei
    // intercettazioni con lo STESSO alias sarebbero sei rotte indistinguibili: `cy.wait` non saprebbe
    // quale delle sei aspetta, e il test tornerebbe a dipendere dall'ordine con cui arrivano.
    let ricercheFatte = 0;

    /**
     * Funzione helper per cercare un utente nella tabella.
     *
     * Aspetta la RISPOSTA della ricerca, non un numero di millisecondi. Prima qui c'era un'attesa
     * fissa di 500 ms, che e' esattamente il debounce di `UserTable.vue:90-95`: il tempo che serve a
     * far PARTIRE la richiesta, non a riceverla. Su una macchina carica il test proseguiva con la
     * tabella non ancora aggiornata.
     *
     * Il filtro su `q` serve: il `clear()` qui sotto fa partire una richiesta con `q` vuoto, e senza
     * filtro l'attesa si sarebbe chiusa su quella.
     *
     * @param {string} username - Lo username da cercare.
     */
    const searchUser = (username) => {
        const alias = `ricercaUtenti${++ricercheFatte}`;

        cy.intercept({ method: "GET", url: "**/admin/v1/users*", query: { q: username } }).as(alias);
        cy.get("#user-search").clear().type(username);
        cy.wait(`@${alias}`).its("response.statusCode").should("eq", 200);
    };

    const showDeleteSelectedUsers = () => {
        cy.get("#btn-delete-selected").click();
    };

    const showRestoreSelectedUsers = () => {
        cy.get("#btn-restore-selected").click();
    };

    // Eseguiamo il login prima di ogni test
    beforeEach(() => {
        // Si assume l'esistenza di un comando custom 'login' per pulizia del codice.
        // Questo comando visita la pagina di login, inserisce le credenziali e fa il submit.
        // Le credenziali sono prese dalle variabili d'ambiente di Cypress.
        cy.login(Cypress.env("adminUsername"), Cypress.env("adminPassword"));
        cy.url().should("include", "/admin/users");
    });

    it("CREATE: dovrebbe creare un nuovo utente", () => {
        cy.contains("button", "Nuovo Utente").click();

        // Riempimento del form
        cy.get("#username").type(newUser.username);
        cy.get("#email").type(newUser.email);
        cy.get("#name").type(newUser.name);
        cy.get("#surname").type(newUser.surname);
        cy.get("#password").type(newUser.password);
        cy.get("#password_confirmation").type(newUser.password);

        // Gestione del campo data
        cy.get("#password_expires_at").click();
        cy.get(".p-datepicker-year").select("2050");
        cy.get(".p-datepicker-month").select("Gennaio");
        cy.contains(".p-datepicker-calendar a", "1").click();

        cy.contains("button", "Crea Utente").click();

        // Verifica del successo
        cy.contains("Il nuovo utente è stato creato con successo").should("be.visible");

        // Verifichiamo che il nuovo utente sia presente nella tabella
        searchUser(newUser.username);
        cy.contains("td", newUser.username).should("be.visible");
    });

    it("READ: dovrebbe cercare e trovare un utente esistente", () => {
        const adminUsername = Cypress.env("adminUsername") || "admin";
        searchUser(adminUsername);

        // La tabella dovrebbe contenere una sola riga con l'utente cercato
        cy.get('[data-cy="users-table"] tbody tr').should("have.length", 1);
        cy.contains("td", adminUsername).should("be.visible");
    });

    it("UPDATE: dovrebbe modificare il nome di un utente e ripristinarlo", () => {
        const adminUsername = Cypress.env("adminUsername") || "Admin";
        const originalName = Cypress.env("oldName") || "Admin";
        const newName = Cypress.env("newName") || "Admin";

        searchUser(adminUsername);

        // update
        cy.get(`[data-cy="edit-user-${adminUsername}"]`).click();
        cy.get('input[data-cy="form-name"]').clear().type(newName);
        cy.contains("button", "Salva Modifiche").click();
        cy.contains("I dati dell'utente sono stati aggiornati").should("be.visible");
        cy.get('[data-cy="users-table"]').contains("td", newName).should("be.visible");

        // restore
        cy.get(`[data-cy="edit-user-${adminUsername}"]`).click();
        cy.get('input[data-cy="form-name"]').clear().type(originalName);
        cy.contains("button", "Salva Modifiche").click();
        cy.contains("I dati dell'utente sono stati aggiornati").should("be.visible");
        cy.get('[data-cy="users-table"]').contains("td", originalName).should("be.visible");
    });

    it("DELETE & RESTORE: dovrebbe eliminare un utente (soft delete) e poi ripristinarlo", () => {
        // delete (soft delete)
        searchUser(newUser.username);
        cy.get(`[data-cy="delete-user-${newUser.username}"]`).click();
        cy.contains("button", "Elimina").click();
        cy.contains("Utente eliminato correttamente").should("be.visible");
        cy.contains("td", newUser.username).should("not.exist");

        // check soft delete
        showDeleteSelectedUsers();
        searchUser(newUser.username);
        cy.contains("td", newUser.username).should("be.visible");

        // restore user
        cy.get(`[data-cy="restore-user-${newUser.username}"]`).click();
        cy.contains("button", "Ripristina").click();
        cy.contains("Utente ripristinato con successo").should("be.visible");

        // check restored
        showRestoredSelectedUsers();
        searchUser(newUser.username);
        cy.contains("td", newUser.username).should("be.visible");
    });
});
