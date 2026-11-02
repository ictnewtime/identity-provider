import { test, expect } from "@playwright/test";
import { admin, newPassword, uniqueSuffix } from "../support/env.js";
import { login } from "../support/auth.js";
import { resetDatabase } from "../support/db.js";
import { createUser, searchUser, toggleDeletedUsers, userRow } from "../support/users.js";
import { it as t } from "../support/labels.js";

/**
 * Crea, cerca, modifica, elimina e ripristina un utente.
 *
 * I passi dipendono l'uno dall'altro (si modifica l'utente creato prima), quindi girano in serie
 * e su un database appena azzerato. L'utente modificato e' quello creato qui, mai l'amministratore.
 */
test.describe.configure({ mode: "serial" });

test.describe("CRUD utenti", () => {
    const suffix = uniqueSuffix();
    const user = {
        username: `testuser_${suffix}`,
        email: `test_${suffix}@example.com`,
        name: "Test",
        surname: "User",
        password: newPassword(),
    };

    test.beforeAll(() => {
        resetDatabase();
    });

    test.beforeEach(async ({ page }) => {
        await login(page, admin.username, admin.password);
    });

    test("CREATE: crea un nuovo utente", async ({ page }) => {
        await createUser(page, user, t);

        await searchUser(page, user.username);
        await expect(userRow(page, user.username)).toBeVisible();
    });

    test("READ: trova l'amministratore cercandolo", async ({ page }) => {
        await searchUser(page, admin.username);

        await expect(page.getByTestId("users-table").locator("tbody tr")).toHaveCount(1);
        await expect(userRow(page, admin.username)).toBeVisible();
    });

    test("UPDATE: modifica il nome dell'utente creato", async ({ page }) => {
        await searchUser(page, user.username);
        await page.getByTestId(`edit-user-${user.username}`).click();

        await expect(page.locator("#name")).toHaveValue(user.name);
        await page.locator("#name").fill("Modificato");
        await page.getByRole("button", { name: t.save }).click();

        await expect(page.getByText(t.updated)).toBeVisible();
        await expect(userRow(page, user.username)).toBeVisible();

        // il nome non e' una colonna della tabella: si verifica riaprendo il form
        await page.getByTestId(`edit-user-${user.username}`).click();
        await expect(page.locator("#name")).toHaveValue("Modificato");
    });

    test("DELETE e RESTORE: elimina l'utente (soft delete) e lo ripristina", async ({ page }) => {
        await searchUser(page, user.username);
        await page.getByTestId(`delete-user-${user.username}`).click();
        await page.getByRole("button", { name: t.delete, exact: true }).click();
        await expect(page.getByText(t.deleted)).toBeVisible();
        await expect(userRow(page, user.username)).toHaveCount(0);

        // fra gli eliminati c'e' (il filtro di ricerca resta lo stesso)
        await toggleDeletedUsers(page, true);
        await expect(userRow(page, user.username)).toBeVisible();

        await page.getByTestId(`restore-user-${user.username}`).click();
        await page.getByRole("button", { name: t.restore, exact: true }).click();
        await expect(page.getByText(t.restored)).toBeVisible();

        // e torna fra gli attivi
        await toggleDeletedUsers(page, false);
        await expect(userRow(page, user.username)).toBeVisible();
    });
});
