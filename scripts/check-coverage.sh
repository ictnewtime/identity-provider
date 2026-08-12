#!/usr/bin/env bash
# TCT17 — la copertura di consumer-lead contro la soglia decisa.
#
# Perché esiste: PHPUnit sa produrre un rapporto di copertura ma **non sa fallire** sotto una
# soglia — non c'è nessun `--coverage-threshold`. Senza questo controllo «coperto al 100%» resta
# un'intenzione che nessun comando conferma o smentisce, ed è ciò che R16 non ammette.
#
# La soglia è **100** per decisione del developer dell'11-08-2026: «va coperto al 100%, non
# vanno lasciati spazi ai dubbi». Finché la copertura è sotto, questo controllo è rosso — ed è il
# comportamento voluto: è un bersaglio, non una descrizione dello stato.
#
# Uso:
#   ./scripts/check-coverage.sh <clover.xml> [soglia]
#
# Il file clover lo produce la suite nell'immagine di prova:
#   docker build -f consumer-lead/Dockerfile.test -t consumer-lead-test:local consumer-lead/.
#   docker run --rm --network host -v "$PWD/tmp:/out" consumer-lead-test:local \
#       vendor/bin/phpunit --coverage-clover /out/clover.xml

set -euo pipefail

# Separatore decimale col punto, indipendente dalla lingua della macchina: con la virgola `awk`
# tronca "82,07" a 82 nel confronto, e su una soglia frazionaria darebbe l'esito sbagliato.
export LC_NUMERIC=C

CLOVER="${1:-}"
SOGLIA="${2:-100}"

if [[ -z "$CLOVER" ]]; then
    echo "Uso: $0 <clover.xml> [soglia]" >&2
    exit 2
fi

if [[ ! -f "$CLOVER" ]]; then
    echo "Rapporto di copertura non trovato: $CLOVER" >&2
    echo "  La suite non è stata eseguita, oppure è stata eseguita senza --coverage-clover." >&2
    echo "  Una soglia senza misura non è una soglia: qui si fallisce invece di passare." >&2
    exit 1
fi

# L'ultimo <metrics> di un clover è il totale del progetto: i precedenti sono per file e per classe.
LETTURA=$(grep -o '<metrics[^>]*statements="[0-9]*"[^>]*>' "$CLOVER" | tail -1)

TOTALI=$(sed -E 's/.* statements="([0-9]+)".*/\1/' <<<"$LETTURA")
COPERTE=$(sed -E 's/.* coveredstatements="([0-9]+)".*/\1/' <<<"$LETTURA")

if [[ -z "$TOTALI" || "$TOTALI" == "0" ]]; then
    echo "Il rapporto non dichiara nessuna riga da coprire: misura non attendibile." >&2
    exit 1
fi

PERCENTUALE=$(awk -v c="$COPERTE" -v t="$TOTALI" 'BEGIN { printf "%.2f", (c / t) * 100 }')
SCOPERTE=$((TOTALI - COPERTE))

if awk -v p="$PERCENTUALE" -v s="$SOGLIA" 'BEGIN { exit !(p + 0 >= s + 0) }'; then
    echo "Copertura ok: ${PERCENTUALE}% (${COPERTE}/${TOTALI} righe), soglia ${SOGLIA}%"
    exit 0
fi

echo "Copertura sotto soglia: ${PERCENTUALE}% (${COPERTE}/${TOTALI} righe), soglia ${SOGLIA}%." >&2
echo "  Restano ${SCOPERTE} righe senza un test che le esegua." >&2
echo "  Il dettaglio per classe sta nel rapporto testuale: aggiungere --coverage-text." >&2
exit 1
