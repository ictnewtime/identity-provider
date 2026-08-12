#!/usr/bin/env bash
# Aggiunge una riga alla lista dei rilievi sulla meta-doc, in append (R7, punto TSV17).
#
# COSA FA, e cosa NON può fare. Questo script possiede la FORMA: assegna l'ID libero, valida la
# natura, scrive nel posto giusto dentro la sezione «Aperte» e aggiorna il conteggio. Il CONTENUTO è
# dell'agente: un hook che scattasse da solo a fine turno non saprebbe cosa scrivere, perché «cosa è
# emerso in questa iterazione» non si deduce dai file toccati. Per questo il modo `--check` **ricorda**
# e non inventa: è la stessa scelta di check-report.sh, che avvisa quando manca un report e non lo
# genera.
#
# PERCHÉ L'APPEND E NON UNA RISCRITTURA. Un `>>` non distrugge mai: nel confine fra hook che
# controllano e hook che agiscono (TAO02) è il caso di scrittura meno rischioso che esista. Uno
# script che riscrivesse la lista potrebbe perderla; questo, al peggio, aggiunge una riga di troppo.
#
# LA FORMA DI UNA RIGA — la stessa dichiarata in docs/ai/full/backlog.md, dove sta in prosa:
#     [ ] <ID> | <natura> | <descrizione in una riga>
# La sigla la decide la natura: `todo` -> ATO · `vulnerabilità` -> AVU · il resto -> ABA. Un ID non si
# riusa: il progressivo riparte dal massimo trovato nel file.
#
# SI SCRIVE IN FONDO, SEMPRE APERTA — dal 2026-08-07, su decisione del developer. Prima si inseriva
# dentro il fence di una sezione «Aperte», con una riga di conteggio da tenere aggiornata e una
# sezione «Chiuse» sotto. Tre cose che potevano rompersi, e due si sono rotte lo stesso giorno: una
# voce vera finita fra i fac-simile, e l'intestazione col conteggio che usciva dal registro di
# migrazione a ogni append. Adesso la lista arriva a fine file e l'append e' letteralmente un `>>`.
#
# COSA NON COPRE (ABA16):
#   - non giudica se il rilievo **valga**: scrive quello che gli viene passato;
#   - non riconosce un duplicato semantico — due righe che dicono la stessa cosa con parole diverse
#     entrano entrambe. Confronta solo il testo esatto;
#   - `--check` sa che la meta-doc è stata toccata, **non** se era emerso qualcosa da annotare: una
#     iterazione che non ha prodotto rilievi lo fa avvisare a vuoto, ed è il prezzo di non inventare;
#   - non chiude niente. Una voce esce dalla lista quando **diventa un task**, e quella è una
#     decisione del developer: nessuno stato `[x]` vive più in questo file.
#
# Uso:  ./scripts/append-finding.sh <natura> "<descrizione in una riga>"
#       ./scripts/append-finding.sh --dry-run <natura> "<descrizione>"
#       ./scripts/append-finding.sh --check            promemoria per l'hook Stop
#
# PORTING: LIST è l'unico percorso da cambiare su un altro repo.

set -uo pipefail

ROOT=${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/.." && pwd)}
LIST="$ROOT/docs/ai/full/backlog.md"

# --- modo promemoria, per l'hook di fine turno.
if [ "${1:-}" = "--check" ]; then
    cd "$ROOT" || exit 0
    [ -f "$LIST" ] || { echo '{}'; exit 0; }
    touched=$(git status --short 2>/dev/null | awk '{print $NF}' \
              | grep -cE '^(docs/ai/|\.claude/(rules|skills|agents)/)' || true)
    [ "${touched:-0}" -eq 0 ] && { echo '{}'; exit 0; }
    # la lista è cambiata in questa sessione di lavoro?
    if git status --short "$LIST" 2>/dev/null | grep -q .; then echo '{}'; exit 0; fi
    printf '{"systemMessage": "R7: la meta-doc e stata toccata (%s file) e la lista dei rilievi non e cambiata. Se e emerso qualcosa — un consiglio, un conflitto, una vulnerabilita della procedura — si aggiunge con ./scripts/append-finding.sh. Se non e emerso niente, va bene cosi."}\n' "$touched"
    exit 0
fi

DRY=0
[ "${1:-}" = "--dry-run" ] && { DRY=1; shift; }

natura=${1:-}
descr=${2:-}

usage() {
    echo "Uso: $0 [--dry-run] <natura> \"<descrizione in una riga>\""
    echo "  natura: consiglio · conflitto · ragionamento · todo · vulnerabilità [A|M|B]"
    exit 2
}

[ -n "$natura" ] && [ -n "$descr" ] || usage
[ -f "$LIST" ] || { echo "Lista non trovata: $LIST"; exit 1; }

# Una riga è una riga: un a capo romperebbe il formato, e il file è fatto per essere letto a colpo.
case "$descr" in *$'\n'*) echo "RIFIUTO: la descrizione deve stare su una riga sola."; exit 1 ;; esac

case "$natura" in
    todo)                          sigla=ATO ;;
    "vulnerabilità"|"vulnerabilità A"|"vulnerabilità M"|"vulnerabilità B") sigla=AVU ;;
    consiglio|conflitto|ragionamento) sigla=ABA ;;
    *) echo "RIFIUTO: natura '$natura' non riconosciuta."; usage ;;
esac

# Il testo esatto c'è già? Un doppione letterale non aggiunge niente.
if grep -qF -- "| $natura | $descr" "$LIST"; then
    echo "RIFIUTO: una riga con questa natura e questa descrizione esiste già."
    exit 1
fi

# Il progressivo libero. Nel file non c'è più nessuna riga d'esempio: ogni riga che comincia con
# `[ ] ` è una voce vera, e il massimo è il massimo.
last=$(grep -oE "^\[ \] ${sigla}[0-9]+" "$LIST" | grep -oE '[0-9]+' | sort -n | tail -1)
next=$(printf '%02d' $(( ${last:-0} + 1 )))
riga="[ ] ${sigla}${next} | ${natura} | ${descr}"

if [ "$DRY" -eq 1 ]; then
    echo "Aggiungerebbe in fondo a docs/ai/full/backlog.md:"
    echo "  $riga"
    exit 0
fi

# In fondo al file, e basta. Niente sezioni, niente conteggio da aggiornare, niente python: la forma
# di scrittura meno rischiosa che esista, che è il motivo per cui l'hook può permettersela (TAO02).
printf '%s\n' "$riga" >> "$LIST"
echo "  aggiunta: $riga"
echo "  voci aperte: $(grep -c '^\[ \] ' "$LIST")"
