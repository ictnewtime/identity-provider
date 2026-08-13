#!/usr/bin/env bash
# Verifica che ogni punto di un piano d'azione stia DENTRO la sua tabella.
#
# Il difetto che corregge: una riga di punto separata dalla tabella da una riga vuota resta una
# riga di punto per chi la scrive — stessa forma, stesse pipe — ma Markdown la rende come testo
# normale. Il punto c'e' e non si legge, che e' il modo peggiore in cui un punto puo' sparire:
# nessuno lo cerca, perche' nel sorgente sembra a posto.
#
# E' successo il 2026-08-12 su cinque punti di 20260812-local-environments, inseriti in coda a una
# tabella con una riga vuota in mezzo.
#
# LA REGOLA: una riga che comincia con `| SIGLA00` deve essere preceduta da un'altra riga che
# comincia con `|`. Se sopra c'e' una riga vuota o del testo, quel punto e' fuori dalla tabella.
#
# Uso:   ./scripts/check-plan-tables.sh          elenco, exit 1 se trova qualcosa
#        ./scripts/check-plan-tables.sh --json   output per un hook
#
# COSA NON COPRE: non verifica che le colonne siano quelle giuste ne' che il numero di celle
# corrisponda all'intestazione. Verifica una cosa sola, quella che sparisce in silenzio.

set -uo pipefail

ROOT=${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/.." && pwd)}
MODE=${1:-text}

problemi=""

while IFS= read -r piano; do
    fuori=$(
        awk '
            # riga di punto: | SIGLA00 | oppure | ~~SIGLA00~~ |
            # Niente intervalli {n,m}: mawk non li supporta e la regex non troverebbe mai niente
            # — un controllo che tace sempre. Scoperto provandolo su un caso noto.
            /^\| *~?~?[A-Z][A-Z][A-Z]*[0-9][0-9][a-z]?~?~? *\|/ {
                if (precedente !~ /^\|/) {
                    printf "  %s:%d  %s\n", FILENAME, NR, substr($0, 1, 60)
                }
            }
            { precedente = $0 }
        ' "$piano"
    )

    [ -n "$fuori" ] && problemi="${problemi}${fuori}"
done < <(find "$ROOT/docs/task" -name "action-plan.md" | sort)

if [ "$MODE" = "--json" ]; then
    if [ -n "$problemi" ]; then
        printf '{"decision":"block","reason":"punti fuori dalla tabella in un piano d%s azione"}\n' "'"
    else
        echo '{}'
    fi
    exit 0
fi

if [ -n "$problemi" ]; then
    echo "Punti FUORI dalla tabella — Markdown li rende come testo e non si leggono:"
    echo
    printf '%s' "$problemi"
    echo
    echo "Rimedio: togliere la riga vuota che separa il punto dalla tabella."
    exit 1
fi

echo "Piani ok: ogni punto sta dentro la sua tabella"
