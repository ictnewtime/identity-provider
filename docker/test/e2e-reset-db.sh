#!/usr/bin/env bash
# Riporta il database degli E2E allo stato iniziale: schema da zero + dati di partenza + utenti E2E.
#
# Lo usano l'entrypoint, all'avvio, e i test che hanno bisogno di partire puliti
# (`resetDatabase()` in tests/e2e/support/db.js). Il database e' il file sqlite in /tmp del
# container: qui non esiste un altro database da colpire per sbaglio, ed e' controllato lo stesso.
set -euo pipefail

cd /var/www

case "${DB_CONNECTION:-}:${DB_DATABASE:-}" in
    sqlite:/tmp/*) ;;
    *)
        echo "e2e-reset-db: rifiuto, il database non e' un sqlite in /tmp (${DB_CONNECTION:-}:${DB_DATABASE:-})" >&2
        exit 1
        ;;
esac

mkdir -p "$(dirname "$DB_DATABASE")"
rm -f "$DB_DATABASE"
touch "$DB_DATABASE"

php artisan migrate --force --no-interaction --quiet
php artisan db:seed --force --no-interaction --quiet
php artisan db:seed --class=E2EUserSeeder --force --no-interaction --quiet
