#!/usr/bin/env bash
# Verifica che gli indici dei task dicano il vero: ogni cartella elencata, ogni riga con la sua cartella.
#
# Il difetto che corregge (VKD29, docs/task/vulnerability/previous/vulnerability.md): il 2026-08-06
# è nato un task e l'indice ha continuato a elencarne sei — e quello mancante aveva la priorità più
# alta. Niente legava la nascita di una cartella alla riga corrispondente.
#
# Vale nei due versi, e il secondo è quello che sfugge: un task spostato in `done/` che resta
# elencato fra gli aperti dice a chi legge che c'è ancora lavoro dove non ce n'è.
#
# TRE INDICI, NON UNO — dal 2026-08-07. `docs/task/index.md` era a 99 righe su un tetto di 40
# (ATO21): elencava aperti, chiusi, rimandati e la vista dei difetti. Chiusi e rimandati sono scesi
# nella cartella che descrivono, e ogni stato ha il suo indice. Il controllo li segue: il legame che
# presidia è «una cartella, una riga», non «un file».
#
# COSA NON COPRE (ABA16):
#   - non verifica che la riga **descriva** il task: solo che lo citi. Una riga che dice il falso
#     passa, ed è il caso di TAO09;
#   - non verifica l'**ordine** di priorità, che è una decisione del developer;
#   - `vulnerability/` e `backlog/development-proposals/` sono registri per contesto, non task
#     datati: le loro cartelle non hanno una riga propria e restano fuori.
#
# Uso:   ./scripts/check-task-index.sh          elenco, exit 1 se trova qualcosa
#        ./scripts/check-task-index.sh --json   output per l'hook di Claude Code
#
# PORTING: TASKS e la tabella STATI sono gli unici percorsi da cambiare su un altro repo.

set -uo pipefail

ROOT=${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/.." && pwd)}
TASKS="$ROOT/docs/task"
MODE=${1:-text}

# stato : indice che lo elenca : prefisso con cui l'indice cita le sue cartelle
STATI="todo:$TASKS/index.md:./todo/
done:$TASKS/done/index.md:./
backlog:$TASKS/backlog/index.md:./"

[ -d "$TASKS" ] || { [ "$MODE" = "--json" ] && echo '{}'; exit 0; }

problems=""

while IFS=: read -r state idx prefix; do
    [ -d "$TASKS/$state" ] || continue
    if [ ! -f "$idx" ]; then
        problems="${problems}stato senza indice: docs/task/$state/ non ha ${idx#$ROOT/}"$'\n'
        continue
    fi

    # Verso 1 — ogni cartella datata ha la sua riga.
    for d in "$TASKS/$state"/*/; do
        [ -d "$d" ] || continue
        name=$(basename "$d")
        case "$name" in [0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]-*) ;; *) continue ;; esac
        grep -qF "${prefix}${name}/" "$idx" \
            || problems="${problems}cartella senza riga d'indice: docs/task/$state/$name/ (atteso in ${idx#$ROOT/})"$'\n'
    done

    # Verso 2 — ogni cartella citata esiste. Solo le datate: gli altri link sono nodi e registri.
    esc=$(printf '%s' "$prefix" | sed 's/[.[\*^$]/\\&/g')
    refs=$(grep -oE "${esc}[0-9]{8}-[A-Za-z0-9._-]+/" "$idx" 2>/dev/null | sort -u)
    while IFS= read -r ref; do
        [ -n "$ref" ] || continue
        [ -d "$TASKS/$state/$(basename "$ref")" ] \
            || problems="${problems}riga d'indice senza cartella: $ref in ${idx#$ROOT/}"$'\n'
    done <<< "$refs"
done <<< "$STATI"

problems=$(printf '%s' "$problems" | sed '/^$/d')

if [ -z "$problems" ]; then
    n_todo=$(find "$TASKS/todo" -maxdepth 1 -mindepth 1 -type d 2>/dev/null | wc -l | tr -d ' ')
    n_done=$(find "$TASKS/done" -maxdepth 1 -mindepth 1 -type d 2>/dev/null | wc -l | tr -d ' ')
    if [ "$MODE" = "--json" ]; then echo '{}'; else
        echo "Indici dei task ok: $n_todo aperti e $n_done chiusi, tutti elencati nel proprio indice"
    fi
    exit 0
fi

count=$(echo "$problems" | wc -l | tr -d ' ')

if [ "$MODE" = "--json" ]; then
    msg=$(echo "$problems" | head -4 | tr '\n' ';' | tr -d '"')
    printf '{"systemMessage": "Indici dei task disallineati: %s. %s"}\n' "$count" "$msg"
    exit 0
fi

echo "Indici dei task disallineati — $count problemi:"
echo "  (l'indice è il documento che dice cosa si fa dopo. Se omette, chi legge sceglie fra le opzioni sbagliate.)"
echo
echo "$problems"
exit 1
