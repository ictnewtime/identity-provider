import { execFileSync } from "node:child_process";

/**
 * Riporta il database allo stato iniziale: schema da zero, dati di partenza e utenti E2E.
 *
 * Funziona perche' Playwright e l'applicazione stanno nello STESSO container: lo script tocca il
 * file sqlite in /tmp che l'applicazione sta usando, e rifiuta qualunque altro database.
 * Le sessioni aperte restano valide solo se il loro utente esiste ancora: dopo un reset si rifa'
 * il login.
 */
export function resetDatabase() {
    execFileSync("e2e-reset-db.sh", { stdio: "inherit" });
}
