import { test, expect } from "@playwright/test";
import { admin, newPassword, uniqueSuffix } from "../support/env.js";
import { login } from "../support/auth.js";
import { createUser, searchUser, userRow } from "../support/users.js";
import { it as t } from "../support/labels.js";

/**
 * Ricerca di un utente con una lettera accentata nel nome.
 *
 * ATTENZIONE AL DATABASE. Gli E2E girano su sqlite (decisione del developer: un container solo),
 * e sqlite ignora maiuscole e minuscole nel `LIKE` solo per le lettere ASCII:
 *
 *     LIKE '%MARIÒ%'  su  'mariò'
 *       sqlite                      -> 0 righe
 *       MariaDB utf8mb4_unicode_ci  -> 1 riga   (misurato il 2026-08-12)
 *
 * Per questo il caso in MAIUSCOLO e' `fixme`: su sqlite fallirebbe per il motore, non per il
 * codice. Torna un test vero il giorno che gli E2E girano su MariaDB, che e' quello di produzione.
 */
test.describe("Ricerca utenti con caratteri accentati", () => {
    const suffix = uniqueSuffix();
    const user = {
        username: `mariò.rossi_${suffix}`,
        email: `mario_${suffix}@example.com`,
        name: "Mariò",
        surname: "Rossi",
        password: newPassword(),
    };

    test.beforeEach(async ({ page }) => {
        await login(page, admin.username, admin.password);
    });

    test("crea un utente accentato e lo trova cercandolo come e' scritto", async ({ page }) => {
        await createUser(page, user, t);

        await searchUser(page, user.username);
        await expect(userRow(page, user.username)).toBeVisible();
    });

    test.fixme("lo trova anche cercandolo in MAIUSCOLO (serve MariaDB, non sqlite)", async ({ page }) => {
        await searchUser(page, user.username.toUpperCase());
        await expect(userRow(page, user.username)).toBeVisible();
    });
});
