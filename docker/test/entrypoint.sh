#!/usr/bin/env bash
# Avvio dell'ambiente dei test di backend. Una cosa sola, e poi esegue quello che gli si chiede.
#
# Il database e' sqlite in memoria (`.env.test.backend.example`): nasce e muore dentro il processo
# di PHP, quindi qui non c'e' niente da creare ne' da aspettare.
set -euo pipefail

# La suite ha bisogno di PHPUnit, che l'immagine dell'applicazione non ha (`--no-dev`).
if [ ! -f vendor/bin/phpunit ]; then
    echo "==> composer install (con le dipendenze di sviluppo)"
    composer install --no-interaction --prefer-dist
fi

exec "$@"
