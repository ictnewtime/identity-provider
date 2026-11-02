import { test, expect } from "@playwright/test";
import { admin, newPassword, nonAdmin, uniqueSuffix } from "../support/env.js";
import { login, submitLogin } from "../support/auth.js";
import { createUser, searchUser, userRow } from "../support/users.js";
import { en as t } from "../support/labels.js";

/**
 * Gli stessi percorsi principali con il browser in inglese.
 *
 * La lingua la sceglie `SetLocale` dall'intestazione `Accept-Language` quando la sessione non ne
 * ha una: qui il browser chiede `en`, e messaggi del server e testi dell'interfaccia devono
 * arrivare in inglese.
 */
test.use({ locale: "en-US", extraHTTPHeaders: { "Accept-Language": "en" } });

test.describe("Interfaccia in inglese", () => {
    test("wrong credentials show the English error", async ({ page }) => {
        await submitLogin(page, "missing_user", "wrong_password");

        await expect(page.getByText(t.loginError)).toBeVisible();
    });

    test("an administrator lands on the English user list", async ({ page }) => {
        await login(page, admin.username, admin.password);

        await expect(page.getByRole("heading", { level: 1, name: t.usersTitle })).toBeVisible();
    });

    test("a user without roles sees the English unauthorized page", async ({ page }) => {
        await submitLogin(page, nonAdmin.username, nonAdmin.password);

        await expect(page.getByRole("heading", { level: 1, name: t.unauthorized })).toBeVisible();
    });

    test("an administrator creates a user with the English form", async ({ page }) => {
        const suffix = uniqueSuffix();
        const user = {
            username: `en_user_${suffix}`,
            email: `en_${suffix}@example.com`,
            name: "English",
            surname: "User",
            password: newPassword(),
        };

        await login(page, admin.username, admin.password);
        await createUser(page, user, t);

        await searchUser(page, user.username);
        await expect(userRow(page, user.username)).toBeVisible();
    });
});
