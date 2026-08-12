#!/usr/bin/env bash
# Avvio dell'ambiente di test. Due cose sole, e poi esegue quello che gli si chiede.
set -euo pipefail

# --- 1. Il database dei test, ricreato se manca ------------------------------------------
# `CREATE DATABASE IF NOT EXISTS`: alla prima accensione lo crea, alle successive non fa niente.
# `idp_test` e' del container; `idp_develop` e' del developer e qui non si nomina nemmeno.
# Si usa PDO invece di un client mysql per non installare un pacchetto in piu'.
if [ "${DB_CONNECTION:-}" = "mysql" ]; then
    echo "==> Attendo ${DB_HOST:-mariadb}:${DB_PORT:-3306}"
    for _ in $(seq 1 30); do
        if timeout 1 bash -c "true < /dev/tcp/${DB_HOST:-mariadb}/${DB_PORT:-3306}" 2>/dev/null; then
            break
        fi
        sleep 1
    done

    echo "==> CREATE DATABASE IF NOT EXISTS ${DB_DATABASE:-idp_test}"
    # Heredoc quotato e non `php -r '...'`: un apostrofo in un commento italiano chiuderebbe la
    # stringa della shell, e il resto finirebbe eseguito da bash. Succede una volta sola.
    php <<'PHP'
<?php
$dsn = sprintf("mysql:host=%s;port=%s", getenv("DB_HOST") ?: "mariadb", getenv("DB_PORT") ?: "3306");
$pdo = new PDO($dsn, getenv("DB_USERNAME") ?: "root", getenv("DB_PASSWORD") ?: "");
$nome = getenv("DB_DATABASE") ?: "idp_test";
// Il nome arriva dalla configurazione, non da una richiesta. Si valida comunque: un
// identificatore non puo' essere passato come parametro legato, quindi finisce nella stringa.
if (!preg_match("/^[A-Za-z0-9_]+$/", $nome)) {
    fwrite(STDERR, "Nome di database non ammesso: {$nome}\n");
    exit(1);
}
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$nome}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
PHP
fi

# --- 2. Le dipendenze di sviluppo ---------------------------------------------------------
# La suite ha bisogno di PHPUnit, che l'immagine dell'applicazione non ha (`--no-dev`).
if [ ! -f vendor/bin/phpunit ]; then
    echo "==> composer install (con le dipendenze di sviluppo)"
    composer install --no-interaction --prefer-dist
fi

exec "$@"
