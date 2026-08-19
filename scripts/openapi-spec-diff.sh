#!/usr/bin/env bash
# Confronta lo specifico OpenAPI generato PRIMA e DOPO una modifica alle annotazioni.
#
# Serve al refactoring dei literali duplicati (task static-analysis-findings-v3): quelle modifiche
# non rompono nessun test, perche' nessun test guarda la documentazione generata. L'unica prova che
# non e' cambiata e' confrontare il file prima e dopo.
#
# Perche' non si versiona il file: `storage/api-docs/.gitignore` contiene `*`, e api-docs.json e' un
# **artefatto generato** — versionarlo significherebbe un conflitto a ogni annotazione toccata da due
# persone. L'istantanea fuori dall'albero costa meno e serve allo stesso scopo.
#
# Uso:
#   ./scripts/openapi-spec-diff.sh salva      prima di toccare le annotazioni
#   ./scripts/openapi-spec-diff.sh confronta  dopo: rigenera e mostra le differenze
#
# Provato nei due versi il 2026-08-13: con le annotazioni intatte dice «identico»; cambiando una
# `description` di una riga, la mostra.

set -euo pipefail

ROOT=${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/.." && pwd)}
SPEC="$ROOT/storage/api-docs/api-docs.json"
SNAPSHOT="${OPENAPI_SNAPSHOT:-/tmp/api-docs.snapshot.json}"

# Il progetto non ha PHP locale: si usa un contenitore usa-e-getta, con la config cache deviata
# per non toccare quella dell'applicazione di sviluppo.
generate() {
    docker run --rm -v "$ROOT":/app -w /app \
        -e APP_CONFIG_CACHE=/tmp/config-openapi.php \
        php:8.2-cli php artisan l5-swagger:generate >/dev/null
}

case "${1:-}" in
salva)
    generate
    cp "$SPEC" "$SNAPSHOT"
    echo "Istantanea salvata in $SNAPSHOT ($(wc -c <"$SNAPSHOT") byte)"
    ;;

confronta)
    if [ ! -f "$SNAPSHOT" ]; then
        echo "Nessuna istantanea in $SNAPSHOT: eseguire prima '$0 salva'." >&2
        exit 1
    fi

    generate

    if diff -q "$SNAPSHOT" "$SPEC" >/dev/null; then
        echo "Specifico OpenAPI IDENTICO: il refactoring non ha cambiato la documentazione"
        exit 0
    fi

    echo "Lo specifico OpenAPI e' CAMBIATO:"
    echo
    diff "$SNAPSHOT" "$SPEC" || true
    exit 1
    ;;

*)
    echo "Uso: $0 {salva|confronta}" >&2
    exit 2
    ;;
esac
