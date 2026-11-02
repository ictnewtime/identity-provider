#!/usr/bin/env bash
# Avvio dei test E2E dentro il loro container. Gli argomenti passano a `playwright test`.
#
# Tutto quello che crea vive nel container e muore con lui: le credenziali, le chiavi, il
# database sqlite in /tmp. Niente viene letto dall'host, niente viene scritto sull'host tranne
# il rapporto dei test, se la cartella /e2e-report e' montata.
set -euo pipefail

cd /var/www

# Le variabili del `.env` dell'immagine servono anche a questo script (DB_DATABASE, E2E_BASE_URL)
# e a Playwright: si esportano. Quelle gia' nell'ambiente del container vincono.
while IFS='=' read -r NOME VALORE; do
    [[ "$NOME" =~ ^[A-Z_][A-Z0-9_]*$ ]] || continue
    [ -n "${!NOME:-}" ] || export "$NOME=$VALORE"
done < .env

# --- 1. Credenziali e chiavi, generate qui ------------------------------------------------
# Generate a ogni avvio: valgono per questo container e basta, non c'e' niente da custodire.
# Il suffisso soddisfa le regole di complessita' delle password al primo colpo.
gen_password() {
    printf '%s' "$({ LC_ALL=C tr -dc 'A-Za-z0-9' < /dev/urandom || true; } | head -c 24)aA1!"
}

APP_KEY="base64:$(openssl rand -base64 32)"
JWT_SECRET=$(openssl rand -hex 32)
SEED_ADMIN_PASSWORD=$(gen_password)
E2E_ADMIN_USERNAME=e2e.admin
E2E_ADMIN_PASSWORD=$(gen_password)
E2E_USER_USERNAME=e2e.user
E2E_USER_PASSWORD=$(gen_password)
export APP_KEY JWT_SECRET SEED_ADMIN_PASSWORD E2E_ADMIN_USERNAME E2E_ADMIN_PASSWORD E2E_USER_USERNAME E2E_USER_PASSWORD

# La coppia di chiavi che firma il master token (storage/app/keys, vedi docs/post-deploy.md).
openssl genrsa -out storage/app/keys/private.key 2048 2>/dev/null
openssl rsa -in storage/app/keys/private.key -pubout -out storage/app/keys/public.key 2>/dev/null
php artisan passport:keys --force --no-interaction --quiet

# --- 2. Database ----------------------------------------------------------------------------
echo "==> Database sqlite: $DB_DATABASE"
e2e-reset-db.sh

# --- 3. Applicazione ------------------------------------------------------------------------
echo "==> php artisan serve su $E2E_BASE_URL"
PHP_CLI_SERVER_WORKERS=4 php artisan serve --host=127.0.0.1 --port=8000 --no-reload > /tmp/serve.log 2>&1 &

for _ in $(seq 1 30); do
    if (true < /dev/tcp/127.0.0.1/8000) 2>/dev/null; then
        break
    fi
    sleep 1
done

# --- 4. Test --------------------------------------------------------------------------------
echo "==> playwright test $*"
set +e
npx playwright test "$@"
ESITO=$?
set -e

# Su un rosso, il log dell'applicazione e' spesso la spiegazione: si mostra la coda.
if [ "$ESITO" -ne 0 ]; then
    echo "==> Errori dell'applicazione (prime righe di ciascuno)"
    grep -E '[a-z]+\.(ERROR|CRITICAL):' /tmp/serve.log | cut -c1-400 | sort | uniq -c | head -n 20 || true
    echo "==> Ultime righe del log dell'applicazione"
    tail -n 30 /tmp/serve.log
fi

exit "$ESITO"
