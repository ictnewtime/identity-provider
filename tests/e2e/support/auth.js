import { expect } from "@playwright/test";

/** Compila e invia il form di login, senza fare ipotesi su dove si arriva. */
export async function submitLogin(page, username, password) {
    await page.goto("/");
    await page.locator("#username").fill(username);
    await page.locator("#password").fill(password);
    await page.locator('form button[type="submit"]').click();
}

/** Login di un amministratore: si attende la lista utenti, che e' la sua pagina di arrivo. */
export async function login(page, username, password) {
    await submitLogin(page, username, password);
    await expect(page).toHaveURL(/\/admin\/users/);
}
