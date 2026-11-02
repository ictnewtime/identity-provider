#!/usr/bin/env bash
# Test E2E (Playwright) in un container usa-e-getta: costruisce l'immagine, esegue i test, e il
# container sparisce alla fine (`--rm`) insieme al suo database sqlite. Riesce o fallisce come i test.
#
#     ./scripts/run-test-e2e.sh                          # tutti
#     ./scripts/run-test-e2e.sh tests/e2e/auth           # una cartella o un file
#     ./scripts/run-test-e2e.sh --grep "logout"          # qualunque opzione di `playwright test`
#
# Non tocca il database di sviluppo, ne' l'applicazione su :8001: dentro il container c'e' tutto.
# L'unica cosa che scrive sull'host e' `e2e-report/` (tracce e rapporto dei test falliti), che git ignora.
set -euo pipefail

RADICE=${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/.." && pwd)}
cd "$RADICE"

IMMAGINE="idp-test-e2e"
RAPPORTO="$RADICE/e2e-report"

echo "==> docker build -f Dockerfile.test.e2e -t $IMMAGINE ."
docker build -q -f Dockerfile.test.e2e -t "$IMMAGINE" . >/dev/null

rm -rf "$RAPPORTO"
mkdir -p "$RAPPORTO"

# `--user`: il rapporto che finisce in e2e-report/ appartiene a chi lancia lo script, non a root.
# `--shm-size`: Chromium usa /dev/shm, e i 64 MB predefiniti di Docker lo fanno cadere.
exec docker run --rm \
    --user "$(id -u):$(id -g)" \
    --shm-size=1g \
    -e HOME=/tmp \
    -e E2E_OUTPUT_DIR=/e2e-report \
    -v "$RAPPORTO":/e2e-report \
    "$IMMAGINE" "$@"
