#!/usr/bin/env bash
# Verifica che un'iterazione che tocca la meta-doc lasci il suo report (R15, punto TMD15).
#
# R15 chiede il report quando l'interruttore è acceso E l'iterazione modifica o pianifica la
# meta-doc. Finora dipendeva dal fatto che l'agente se ne ricordasse — cioè dal difetto che il report
# stesso è nato per denunciare.
#
# Hook: Stop. AVVISA, non blocca: un report è una riflessione, e obbligarla a comando produrrebbe
# testo scritto per soddisfare un controllo. Il blocco è per le cose che rompono qualcosa.
#
# COSA NON COPRE (ABA16): verifica che un report ESISTA, non che ragioni. E la granularità è il
# GIORNO, non l'iterazione: un report scritto stamattina soddisfa il controllo per un'iterazione di
# stasera — un controllo che pretendesse "uno per iterazione" non saprebbe contare le iterazioni.
#
# Uso:   ./scripts/check-report.sh          leggibile
#        ./scripts/check-report.sh --json   per l'hook

set -uo pipefail

ROOT=${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/.." && pwd)}
INDEX="$ROOT/docs/ai/index.md"
REPORTS="$ROOT/docs/ai/reports"
MODE=${1:-text}

ok() { if [ "$MODE" = "--json" ]; then echo '{}'; else echo "$1"; fi; exit 0; }

[ -f "$INDEX" ] || ok "Indice assente: niente da verificare"

# 1. L'interruttore. Se è spento, la regola si ignora — è metà del suo senso.
grep -qE '^\| R15 \|.*interruttore.*\*\*`acceso`\*\*' "$INDEX" || ok "R15 spenta: nessun report atteso"

cd "$ROOT" || ok ""

# 2. L'iterazione ha toccato la meta-doc? Modificandola, oppure PIANIFICANDOLA — la seconda è la
#    correzione del 2026-08-06: il ragionamento più denso sta nell'iterazione che progetta, e quella
#    scrive in docs/task/, non in docs/ai/.
#
#    Il "pianificandola" era rilevato cercando `meta-doc` nel NOME della cartella del task: un piano
#    che riscrive `docs/ai/` da una cartella chiamata diversamente passava sotto, e il silenzio del
#    cancello si legge come conformità. È ABA16, e la prima istanza misurata è il piano che ha
#    prodotto questa correzione. Ora si guarda il BERSAGLIO DICHIARATO: la riga `**Ambito**` del
#    piano, che è dove un piano dice su cosa lavora.
touched=$(git status --short 2>/dev/null | awk '{print $NF}')
meta=$(printf '%s\n' "$touched" | grep -cE '^(docs/ai/|\.claude/(rules|skills|agents)/)' || true)

# Il nome della cartella resta come primo indizio: costa un grep e copre i task già nati così.
plan=$(printf '%s\n' "$touched" | grep -cE '^docs/task/.*(meta-doc|meta-docs)' || true)

# L'Ambito dichiarato. Si legge SOLO quella riga e non tutto il file: ogni documento di task cita
# `docs/ai/` per rimandare a una regola, e contare quelle citazioni farebbe scattare il cancello su
# qualunque modifica — un controllo che grida sul corretto si smette di leggere.
if [ "${plan:-0}" -eq 0 ]; then
    for f in $(printf '%s\n' "$touched" | grep -E '^docs/task/.*\.md$'); do
        [ -f "$f" ] || continue
        if sed -n 's/^|[[:space:]]*\*\*Ambito\*\*[[:space:]]*|//p' "$f" \
           | grep -qE '(docs/ai/|\.claude/)'; then
            plan=$((plan + 1))
        fi
    done
fi

[ "${meta:-0}" -eq 0 ] && [ "${plan:-0}" -eq 0 ] && ok "Meta-doc non toccata: nessun report atteso"

# 3. Un report scritto oggi? I report sono per iterazione, ma la granularità verificabile è il
#    giorno: un controllo che pretendesse "uno per iterazione" non saprebbe contare le iterazioni.
today=$(date +%F)
recent=$(ls "$REPORTS"/${today}-*.md 2>/dev/null | wc -l | tr -d ' ')

if [ "${recent:-0}" -gt 0 ]; then
    ok "Report ok: $recent scritti oggi, meta-doc toccata"
fi

msg="R15: la meta-doc e' stata toccata (${meta} file, ${plan} di pianificazione) e oggi non risulta nessun report in docs/ai/reports/. Nome: $(date +%Y-%m-%d-%H-%M-%S).md, formato nel README della cartella."

if [ "$MODE" = "--json" ]; then
    printf '{"systemMessage": "%s"}\n' "$msg"
else
    echo "$msg"
    exit 1
fi
