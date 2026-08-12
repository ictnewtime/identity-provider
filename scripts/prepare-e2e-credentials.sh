#!/usr/bin/env bash
# Prepara le credenziali dei test E2E: le GENERA, non le custodisce.
#
# Un segreto che non esiste prima dell'esecuzione non si puo' esporre. Non c'e' niente da
# ruotare, da revocare o da ritrovare in una storia git: le password valgono per un ambiente
# e per una preparazione sola.
#
# Le due meta' sono INSEPARABILI e questo e' il motivo per cui e' uno script e non due comandi:
#   1. gli utenti nel database, con quelle password;
#   2. il file che Cypress legge per accedere.
# La seconda da sola produce un login che fallisce sempre, perche' cypress.env.json non crea
# utenti: contiene le credenziali di utenti che devono gia' esistere.
#
# Uso:   ./scripts/prepare-e2e-credentials.sh
#        PHP_BIN="docker exec -i idp_app_2 php" ./scripts/prepare-e2e-credentials.sh
#
# Punto TSA10 di docs/task/todo/20260812-static-analysis-findings-v1/action-plan.md.
#
# COSA NON FA, perche' uno script che non lo dichiara sembra coprire tutto:
#   - non tocca l'indice git. Sganciare cypress.env.json e' TSA14 e lo esegue il developer (R2);
#   - non prepara il database: DatabaseSeeder deve essere gia' passato (provider e ruolo admin);
#   - non porta le credenziali nella pipeline: quello e' BPT03, in docs/task/backlog/.

set -euo pipefail

# I file creati qui sotto non sono leggibili dagli altri utenti della macchina.
umask 077

ROOT=$(cd "$(dirname "$0")/.." && pwd)
ENV_FILE="$ROOT/cypress.env.json"
PHP_BIN=${PHP_BIN:-php}

# Il suffisso garantisce maiuscola, minuscola, cifra e simbolo: la policy di complessita' e'
# soddisfatta al primo colpo, senza un ciclo che rigenera finche' non va bene.
gen_pwd() {
    printf '%s' "$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9' | cut -c1-20)Aa1!"
}

E2E_ADMIN_USERNAME=${E2E_ADMIN_USERNAME:-e2e.admin}
E2E_USER_USERNAME=${E2E_USER_USERNAME:-e2e.user}
E2E_ADMIN_PASSWORD=$(gen_pwd)
E2E_USER_PASSWORD=$(gen_pwd)
# Password dell'utente creato DAL test di CRUD: e' un dato di prova, non una credenziale di
# accesso, ma segue la stessa strada per non lasciare letterali nei test (TSA03).
E2E_NEW_USER_PASSWORD=$(gen_pwd)

export E2E_ADMIN_USERNAME E2E_ADMIN_PASSWORD E2E_USER_USERNAME E2E_USER_PASSWORD

# --- 1. Gli utenti, PRIMA del file -------------------------------------------------------
# Se il seeder fallisce lo script si ferma qui e cypress.env.json resta com'era: meglio uno
# stato vecchio e coerente di un file nuovo con credenziali che non aprono niente.
echo "==> Utenti E2E nel database"
$PHP_BIN "$ROOT/artisan" db:seed --class=E2EUserSeeder --no-interaction

# --- 2. Il file che legge Cypress --------------------------------------------------------
echo "==> $ENV_FILE"
cat > "$ENV_FILE" <<EOF
{
    "adminUsername": "${E2E_ADMIN_USERNAME}",
    "adminPassword": "${E2E_ADMIN_PASSWORD}",
    "nonAdminUsername": "${E2E_USER_USERNAME}",
    "nonAdminPassword": "${E2E_USER_PASSWORD}",
    "newUserPassword": "${E2E_NEW_USER_PASSWORD}",
    "newName": "Francesco2",
    "oldName": "Francesco"
}
EOF

# --- 3. Il controllo che non deve fallire a vuoto ----------------------------------------
# Avvisa e basta: e' una diagnosi, non un passo. Se git non e' utilizzabile qui, non e' una
# ragione per far fallire una preparazione che per il resto e' riuscita.
if git -C "$ROOT" ls-files --error-unmatch cypress.env.json >/dev/null 2>&1; then
    echo "!!! cypress.env.json e' ancora TRACCIATO da git: 'git rm --cached cypress.env.json' (TSA14)"
fi

echo "==> Fatto. Utenti: ${E2E_ADMIN_USERNAME}, ${E2E_USER_USERNAME}. Le password non si stampano (R6)."
