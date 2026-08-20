#!/usr/bin/env bash
# Prepara `.env.test.backend` a partire dal modello versionato.
#
#     ./scripts/setup-env-for-test-backend.sh
#
# Fa una cosa sola, ed e' il motivo per cui e' separato da `run-test-backend.sh`: la preparazione
# dell'ambiente serve anche a chi i test li lancia **a mano**, con `docker build` e `docker run` —
# e in quel caso non vuole che qualcuno gli costruisca l'immagine per conto suo.
#
# Cosa fa, in ordine:
#   1. se `.env.test.backend` non c'e', lo crea copiando il modello;
#   2. per ogni variabile che il modello dichiara **senza valore** — sono le credenziali — controlla
#      se un valore c'e' gia': nell'ambiente del processo, oppure nel file locale;
#   3. se manca, lo **genera** e lo scrive nel file locale, che git ignora. Mai nel modello.
#
# NON lo chiede a nessuno. Sono credenziali di prova: servono a esistere, non a essere ricordate —
# il seeder pretende una password e non ne inventa una (difetto VDF08), ma quale sia non interessa a
# nessuno. Generandola cadono due problemi in una volta: non c'e' niente da digitare, e lo script
# funziona identico in CI, dove un terminale non c'e'.
#
# Se il file e' gia' completo non genera niente: il valore resta quello di prima, cosi' due
# esecuzioni di fila lavorano sullo stesso ambiente.
set -euo pipefail

RADICE=${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/.." && pwd)}
cd "$RADICE"

MODELLO=".env.test.backend.example"
LOCALE=".env.test.backend"

[ -f "$MODELLO" ] || { echo "Manca $MODELLO: non c'e' da cosa partire." >&2; exit 1; }

# Le variabili dichiarate vuote nel modello: nel file versionato non entra mai un valore.
mapfile -t DA_COMPILARE < <(grep -E '^[A-Z_]+=$' "$MODELLO" | sed 's/=$//')

if [ ! -f "$LOCALE" ]; then
    cp "$MODELLO" "$LOCALE"
    echo "==> Creato $LOCALE dal modello"
fi

for NOME in "${DA_COMPILARE[@]}"; do
    # L'ambiente del processo vince: in CI si esporta e qui non si chiede niente.
    if [ -n "${!NOME:-}" ]; then
        echo "==> $NOME: gia' nell'ambiente, non la scrivo nel file"
        continue
    fi

    # `|| true`: senza, `grep` che non trova niente farebbe uscire lo script per via di
    # `set -e` + `pipefail` — in silenzio, con codice 1 e nessun messaggio. Provato.
    VALORE=$(grep -E "^$NOME=" "$LOCALE" | tail -1 | cut -d= -f2- || true)

    if [ -n "$VALORE" ]; then
        echo "==> $NOME: gia' in $LOCALE"
        continue
    fi

    # Generata: 24 caratteri alfanumerici piu' un suffisso che soddisfa qualunque regola di
    # complessita' incontri per strada. Niente caratteri che possano rompere una riga di `.env`
    # o una riga di comando.
    # Le graffe con `|| true` non sono decorazione: `head` chiude la pipe dopo 24 byte, `tr` prende
    # SIGPIPE, e con `pipefail` l'intera pipeline vale 141 — `set -e` chiuderebbe lo script **in
    # silenzio**. E' la seconda volta che questa combinazione morde in questo file.
    VALORE="$({ LC_ALL=C tr -dc 'A-Za-z0-9' < /dev/urandom || true; } | head -c 24)aA1!"
    [ ${#VALORE} -ge 20 ] || { echo "Generazione di $NOME fallita." >&2; exit 1; }

    TMP=$(mktemp)
    if grep -qE "^$NOME=" "$LOCALE"; then
        awk -v n="$NOME" -v v="$VALORE" '{ if ($0 ~ "^"n"=") print n"="v; else print }' "$LOCALE" > "$TMP"
        mv "$TMP" "$LOCALE"
    else
        rm -f "$TMP"
        printf '%s=%s\n' "$NOME" "$VALORE" >> "$LOCALE"
    fi
    echo "==> $NOME generata e scritta in $LOCALE" >&2
done

echo "==> $LOCALE pronto"
