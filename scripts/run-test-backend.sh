#!/usr/bin/env bash
# Esegue la suite di BACKEND: prepara l'ambiente, costruisce l'immagine, lancia i test.
#
#     ./scripts/run-test-backend.sh                    tutta la suite
#     ./scripts/run-test-backend.sh --filter=Audit     una parte
#
# La preparazione dell'ambiente NON e' qui dentro: e' `setup-env-for-test-backend.sh`, che questo
# script chiama. Sono due perche' servono a due momenti diversi — chi lancia i test a mano prepara
# l'ambiente e basta, e non vuole che gli si costruisca un'immagine per conto suo.
#
# COSA NON FA: non passa `--env-file`. Passa **una per una** le sole variabili che il modello
# dichiara senza valore. Un file locale sbagliato non deve poter sovrascrivere
# TEST_ALLOWED_DATABASES, che e' la guardia contro i test che cancellano il database di sviluppo
# (difetto VDF11).
set -euo pipefail

RADICE=${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/.." && pwd)}
cd "$RADICE"

MODELLO=".env.test.backend.example"
LOCALE=".env.test.backend"
IMMAGINE="idp-test-backend"

# --- 1. L'ambiente ----------------------------------------------------------------------------
"$RADICE/scripts/setup-env-for-test-backend.sh"

# --- 2. Le credenziali da passare al container ------------------------------------------------
# Si rilegge il file appena preparato. L'ambiente del processo vince, come nello script di sopra.
mapfile -t DA_PASSARE < <(grep -E '^[A-Z_]+=$' "$MODELLO" | sed 's/=$//')

ENV_ARGS=()
for NOME in "${DA_PASSARE[@]}"; do
    VALORE=${!NOME:-}
    # `|| true` per la stessa ragione dello script di preparazione: `set -e` + `pipefail`.
    [ -n "$VALORE" ] || VALORE=$(grep -E "^$NOME=" "$LOCALE" | tail -1 | cut -d= -f2- || true)
    [ -n "$VALORE" ] || { echo "Manca ancora $NOME: eseguire ./scripts/setup-env-for-test-backend.sh" >&2; exit 1; }
    ENV_ARGS+=(-e "$NOME=$VALORE")
done

# --- 3. Immagine e suite -----------------------------------------------------------------------
echo "==> docker build -f Dockerfile.test.backend -t $IMMAGINE ."
docker build -q -f Dockerfile.test.backend -t "$IMMAGINE" . >/dev/null

if [ "$#" -eq 0 ]; then
    set -- php artisan test
else
    set -- php artisan test "$@"
fi

echo "==> $*"
# `--user`: i file che il container scrive nell'albero montato appartengono a CHI HA LANCIATO lo
# script, non a root. Senza questo, ogni esecuzione lasciava file di root nell'albero del developer —
# 3996 al conto del 2026-08-20 — e uno di loro, `lang/php_en.json`, bloccava `npm run build` con
# `EACCES` (difetto BDB32). Non e' una questione di sicurezza dell'immagine: e' l'albero dell'host.
# `HOME`: l'uid dell'host dentro il container non ha una casa, e composer scrive nella sua. `/tmp`
# non e' nell'albero montato, quindi non lascia niente in giro.
exec docker run --rm \
    --user "$(id -u):$(id -g)" \
    -e HOME=/tmp \
    -v "$RADICE":/var/www \
    "${ENV_ARGS[@]}" \
    "$IMMAGINE" "$@"
