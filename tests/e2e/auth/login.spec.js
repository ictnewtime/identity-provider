import { test, expect } from "@playwright/test";
import { admin, nonAdmin } from "../support/env.js";
import { login, submitLogin } from "../support/auth.js";
import { it as t } from "../support/labels.js";

test.describe("Autenticazione (login/logout)", () => {
    test("credenziali sbagliate: resta sul login e mostra l'errore", async ({ page }) => {
        await submitLogin(page, "utente_inesistente", "password_errata");

        await expect(page.getByText(t.loginError)).toBeVisible();
        await expect(page.locator("#username")).toBeVisible();
    });

    test("un amministratore entra nella lista utenti e poi esce", async ({ page }) => {
        await login(page, admin.username, admin.password);
        await expect(page.getByRole("heading", { level: 1, name: t.usersTitle })).toBeVisible();

        await page.getByRole("button", { name: "Logout" }).click();

        // Il logout riporta al form di login, che sta su "/".
        await expect(page.locator("#username")).toBeVisible();
        await expect(page.getByRole("button", { name: "Login" })).toBeVisible();
    });

    test("un utente senza ruoli sull'IdP finisce su Accesso Negato", async ({ page }) => {
        await submitLogin(page, nonAdmin.username, nonAdmin.password);

        await expect(page).toHaveURL(/\/client\/v1\/unauthorized/);
        await expect(page.getByRole("heading", { level: 1, name: t.unauthorized })).toBeVisible();
    });
});
