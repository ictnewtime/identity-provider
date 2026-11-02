import { expect } from "@playwright/test";

/**
 * Cerca nella tabella utenti e aspetta la RISPOSTA della ricerca, non un tempo fisso.
 *
 * La ricerca parte dopo il debounce di UserTable.vue; l'attesa si aggancia alla richiesta con
 * quel `q`, non alle altre ricariche della lista (dopo un salvataggio, o con `q` vuoto).
 */
export async function searchUser(page, text) {
    const response = page.waitForResponse(
        (res) =>
            res.url().includes("/admin/v1/users?") &&
            new URL(res.url()).searchParams.get("q") === text &&
            res.request().method() === "GET",
    );
    await page.locator("#user-search").fill(text);
    expect((await response).status()).toBe(200);
}

/**
 * Mostra o nasconde gli utenti eliminati. Il filtro di ricerca resta quello di prima: si aspetta
 * la ricarica della lista con il nuovo `show_deleted`.
 */
export async function toggleDeletedUsers(page, showDeleted) {
    const response = page.waitForResponse(
        (res) =>
            res.url().includes("/admin/v1/users?") &&
            new URL(res.url()).searchParams.get("show_deleted") === String(showDeleted),
    );
    await page.getByTestId("toggle-deleted-users").click();
    expect((await response).status()).toBe(200);
}

/** La riga della tabella utenti che contiene lo username indicato. */
export function userRow(page, username) {
    return page.getByTestId("users-table").locator("tbody tr").filter({ hasText: username });
}

/**
 * Crea un utente dal form "Nuovo utente". Il form e' quello di UserForm.vue; la data di
 * scadenza della password e' facoltativa e si lascia vuota.
 */
export async function createUser(page, user, labels) {
    await page.getByRole("button", { name: labels.newUser }).click();
    await page.locator("#username").fill(user.username);
    await page.locator("#email").fill(user.email);
    await page.locator("#name").fill(user.name);
    await page.locator("#surname").fill(user.surname);
    await page.locator("#password").fill(user.password);
    await page.locator("#password_confirmation").fill(user.password);
    await page.getByRole("button", { name: labels.create }).click();
    await expect(page.getByText(labels.created)).toBeVisible();
}
