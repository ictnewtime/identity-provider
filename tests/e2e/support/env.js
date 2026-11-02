import { randomBytes } from "node:crypto";

/**
 * Le credenziali degli utenti E2E. Le genera `docker/test/e2e-entrypoint.sh` a ogni avvio del
 * container e le semina `E2EUserSeeder`: qui si leggono e basta. Nessun valore di ripiego, perche'
 * una password scritta nel test sarebbe una password nel repository.
 */
function required(name) {
    const value = process.env[name];
    if (!value) {
        throw new Error(`Manca ${name}: i test E2E si eseguono con ./scripts/run-test-e2e.sh`);
    }
    return value;
}

export const admin = {
    get username() {
        return required("E2E_ADMIN_USERNAME");
    },
    get password() {
        return required("E2E_ADMIN_PASSWORD");
    },
};

export const nonAdmin = {
    get username() {
        return required("E2E_USER_USERNAME");
    },
    get password() {
        return required("E2E_USER_PASSWORD");
    },
};

/** Una password valida per un utente creato DAL test: lunga almeno 12, con ogni classe di caratteri. */
export function newPassword() {
    return `${randomBytes(12).toString("hex")}aA1!`;
}

/** Un suffisso per dati unici fra un'esecuzione e l'altra. */
export function uniqueSuffix() {
    return `${Date.now()}${randomBytes(2).toString("hex")}`;
}
