import { test, expect } from "@playwright/test";
import { admin, uniqueSuffix } from "../support/env.js";
import { login } from "../support/auth.js";
import { insertBulkData } from "../support/db.js";
import { searchUser, userRow } from "../support/users.js";

/**
 * Le ricerche nei form trovano anche cio' che sta OLTRE il centesimo elemento.
 *
 * Il backend restituisce al massimo 100 righe per pagina. Due difetti visti su staging:
 * - i menu caricavano l'elenco una volta sola, con `per_page: 1000`: il server si fermava a 100 e
 *   chi veniva dopo non si trovava mai;
 * - scrivendo nel menu, ogni lettera sostituiva la precedente invece di comporre la parola.
 *
 * Qui si creano 105 utenti, 105 provider e 105 ruoli (sull'ultimo provider) e si cerca sempre
 * l'ULTIMO, scrivendone il nome lettera per lettera.
 */
const prefix = `bulk${uniqueSuffix()}`;
const COUNT = 105;
const lastUser = `${prefix}_${COUNT}`;
const lastProvider = `${prefix}_p${COUNT}`;
const lastRole = `${prefix}_r${COUNT}`;

test.beforeAll(() => {
    insertBulkData(prefix, COUNT);
});

test.beforeEach(async ({ page }) => {
    await login(page, admin.username, admin.password);
});

/**
 * Apre una modale e aspetta che la sua animazione sia finita: alla fine dell'animazione la modale
 * sposta il focus sul suo primo elemento, e le lettere battute prima andrebbero perse.
 */
async function openDialog(page, buttonName, dialogName) {
    await page.getByRole("button", { name: buttonName }).click();
    const dialog = page.getByRole("dialog", { name: dialogName });
    await expect(dialog).toBeVisible();
    await dialog.evaluate((el) => Promise.allSettled(el.getAnimations({ subtree: true }).map((a) => a.finished)));
    return dialog;
}

/**
 * Il caso segnalato: cursore sul menu CHIUSO, poi il testo battuto lettera per lettera.
 * Le lettere devono comporsi nel filtro del menu.
 */
async function typeInClosedSelect(page, label, text) {
    const select = page.getByRole("combobox", { name: label });
    await select.focus();
    await page.keyboard.type(text, { delay: 20 });
    await expect(page.locator(".p-select-filter")).toHaveValue(text);
    return select;
}

async function pickOption(page, name) {
    const option = page.getByRole("option", { name, exact: true });
    await expect(option).toBeVisible();
    await option.click();
}

/** Nelle MultiSelect: si apre il menu, si scrive nel filtro, si sceglie l'opzione. */
async function searchInMultiSelect(page, testId, text, apiPath) {
    const response = page.waitForResponse(
        (res) => res.url().includes(`${apiPath}?`) && new URL(res.url()).searchParams.get("q") === text,
    );
    await page.getByTestId(testId).click();
    await page.keyboard.type(text, { delay: 20 });
    await expect(page.locator(".p-multiselect-filter")).toHaveValue(text);
    expect((await response).status()).toBe(200);
    await page.locator(".p-multiselect-option").filter({ hasText: text }).click();
    await page.keyboard.press("Escape");
}

test.describe("Nuova Associazione", () => {
    test.beforeEach(async ({ page }) => {
        await page.goto("/admin/provider-user-roles");
        await openDialog(page, "Nuova Associazione", "Nuova Associazione");
    });

    test("trova un utente oltre il centesimo (ricerca sul server)", async ({ page }) => {
        const response = page.waitForResponse(
            (res) => res.url().includes("/admin/v1/users?") && new URL(res.url()).searchParams.get("q") === lastUser,
        );
        const select = await typeInClosedSelect(page, "Utente", lastUser);
        expect((await response).status()).toBe(200);

        await pickOption(page, lastUser);
        await expect(select).toHaveText(lastUser);
    });

    test("trova un provider e un ruolo oltre il centesimo", async ({ page }) => {
        const provider = await typeInClosedSelect(page, "Provider", lastProvider);
        await pickOption(page, lastProvider);
        await expect(provider).toHaveText(lastProvider);

        // i ruoli si caricano dopo la scelta del provider: si aspetta che il menu si abiliti
        const role = page.getByRole("combobox", { name: "Ruolo" });
        await expect(role).not.toHaveAttribute("aria-disabled", "true");
        await typeInClosedSelect(page, "Ruolo", lastRole);
        await pickOption(page, lastRole);
        await expect(role).toHaveText(lastRole);
    });
});

test.describe("Nuovo Ruolo", () => {
    test("trova un provider oltre il centesimo", async ({ page }) => {
        await page.goto("/admin/roles");
        await openDialog(page, "Nuovo Ruolo", "Nuovo Ruolo");

        const provider = await typeInClosedSelect(page, "Provider", lastProvider);
        await pickOption(page, lastProvider);
        await expect(provider).toHaveText(lastProvider);
    });
});

test.describe("Copia ruoli", () => {
    test("trova utenti destinatari e ruoli oltre il centesimo", async ({ page }) => {
        await searchUser(page, admin.username);
        await userRow(page, admin.username).locator(".p-checkbox").first().click();
        await page.getByTestId("btn-add-roles").click();
        await expect(page.getByTestId("add-roles-users")).toBeVisible();

        await searchInMultiSelect(page, "add-roles-users", lastUser, "/admin/v1/users");
        await expect(page.getByTestId("add-roles-users")).toContainText(lastUser);

        await searchInMultiSelect(page, "add-roles-roles", lastRole, "/admin/v1/roles");
    });
});
