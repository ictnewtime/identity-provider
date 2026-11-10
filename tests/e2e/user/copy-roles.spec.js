import { test, expect } from "@playwright/test";
import { admin } from "../support/env.js";
import { login } from "../support/auth.js";
import { searchUser, userRow } from "../support/users.js";
import { it as t } from "../support/labels.js";

/**
 * "Copia ruoli": gli utenti scelti in tabella sono la SORGENTE. All'apertura della modale i loro
 * ruoli sono già selezionati e il campo "Utenti destinatari" è vuoto: l'invio parte solo dopo
 * aver scelto almeno un destinatario, e porta i destinatari, non la sorgente.
 *
 * L'invio (`bulk-add`) è intercettato e NON raggiunge il backend: qui interessa cosa spedisce
 * l'interfaccia, non il salvataggio, che ha i suoi test di backend.
 */
test.describe("Copia ruoli", () => {
    test.beforeEach(async ({ page }) => {
        await login(page, admin.username, admin.password);
    });

    test("il bottone è disabilitato senza utenti selezionati", async ({ page }) => {
        const button = page.getByTestId("btn-add-roles");

        await expect(button).toBeDisabled();
        await expect(button).toContainText(t.copyRoles);

        // il tooltip spiega cosa fare anche a bottone disabilitato
        await page.getByTestId("btn-add-roles-wrapper").hover();
        await expect(page.getByText(t.copyRolesTooltip)).toBeVisible();
    });

    test("precompila i ruoli degli utenti selezionati e li copia sui destinatari scelti", async ({ page }) => {
        await searchUser(page, admin.username);
        await expect(page.getByTestId("users-table").locator("tbody tr")).toHaveCount(1);

        let bulkAddBody = null;
        await page.route("**/admin/v1/provider-user-roles/bulk-add", async (route) => {
            bulkAddBody = route.request().postDataJSON();
            await route.fulfill({ status: 200, json: {} });
        });

        const sourceRoles = page.waitForResponse(
            (res) => res.url().includes("/admin/v1/users/roles") && res.request().method() === "GET",
        );
        // La checkbox di PrimeVue tiene lo stato in Vue, non nell'input: `check()` non lo vede
        // cambiare. Si clicca e si verifica l'effetto, cioè il bottone che si abilita.
        await userRow(page, admin.username).locator(".p-checkbox").first().click();
        await expect(page.getByTestId("btn-add-roles")).toBeEnabled();
        await page.getByTestId("btn-add-roles").click();
        await expect(page.getByTestId("add-roles-roles")).toBeVisible();

        // i ruoli della sorgente sono già selezionati
        const response = await sourceRoles;
        expect(response.url()).toContain("user_ids");
        expect(response.status()).toBe(200);
        const roles = await response.json();
        expect(roles.length, "ruoli dell'admin").toBeGreaterThan(0);
        await expect(page.getByTestId("add-roles-roles")).toContainText(roles[0].name);

        // i destinatari partono vuoti: senza destinatari non si invia
        await expect(page.getByTestId("add-roles-users").locator(".p-multiselect-chip")).toHaveCount(0);
        await expect(page.getByTestId("add-roles-submit")).toBeDisabled();
        await expect(page.getByTestId("add-roles-submit")).toContainText(t.applyRoles);

        await page.getByTestId("add-roles-users").click();
        await page.locator(".p-multiselect-option").filter({ hasText: admin.username }).click();
        await page.keyboard.press("Escape");

        const bulkAdd = page.waitForRequest("**/admin/v1/provider-user-roles/bulk-add");
        await page.getByTestId("add-roles-submit").click();
        await bulkAdd;

        expect(bulkAddBody.user_ids).toHaveLength(1);
        expect(bulkAddBody.roles.length).toBeGreaterThan(0);
        expect(bulkAddBody.is_all_selected).toBe(false);
        await expect(page.getByText(t.rolesCopied)).toBeVisible();
    });
});
