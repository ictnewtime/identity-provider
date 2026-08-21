#!/usr/bin/env bash
# Un comando dentro il container dell'applicazione, con l'utente di chi lancia lo script.
#
# Perche' esiste (punto TSR12): dentro `idp_app_2` si e' root, e il container monta l'albero del
# progetto su /var/www. Quindi `docker exec idp_app_2 npm run build` scrive i suoi file **come root
# nell'albero di chi sviluppa**, e da quel momento quei file non si riscrivono e non si cancellano:
# e' l'`EACCES` di `npm run build`, il difetto BDB32, che e' costato una sessione a trovare.
#
# Il container **deve** restare root — nginx apre la porta 80 e l'entrypoint fa `chown` — quindi la
# leva non e' il container: e' il comando. Qui sta scritto una volta, e vale per npm, composer e
# artisan insieme.
#
# Uso:   ./scripts/in-app.sh npm run build
#        ./scripts/in-app.sh php artisan migrate --force
#        ./scripts/in-app.sh composer install
#
# COSA NON COPRE: chi scrive `docker exec idp_app_2 ...` a mano continua a lasciare file di root.
# Non c'e' modo di impedirlo; c'e' solo modo di rendere il comando giusto piu' corto di quello
# sbagliato.

set -euo pipefail

CONTENITORE=${CONTENITORE_APP:-idp_app_2}

if [ "$#" -eq 0 ]; then
    echo "Uso: $0 <comando> [argomenti]   — per esempio: $0 npm run build" >&2
    exit 2
fi

if ! docker inspect -f '{{.State.Running}}' "$CONTENITORE" 2>/dev/null | grep -q true; then
    echo "Il container $CONTENITORE non e' in esecuzione: lancia prima 'docker compose up -d'." >&2
    exit 1
fi

# `HOME=/tmp`: l'uid dell'host dentro il container non ha una casa, e npm e composer scrivono la
# loro cache in `$HOME`. `/tmp` sta fuori dall'albero montato, quindi non lascia niente in giro.
exec docker exec \
    --user "$(id -u):$(id -g)" \
    --env HOME=/tmp \
    --workdir /var/www \
    "$CONTENITORE" "$@"
