#!/usr/bin/env bash
# Salva e rilegge lo STATO DEL LAVORO attraverso una compattazione del contesto (TMD14).
#
# Cosa NON serve salvare, perché si ricarica da solo: il CLAUDE.md di radice e l'indice che importa.
# Era il presupposto sbagliato di ATO13 — dava per perso tutto, e metà si recupera da sola.
#
# Cosa resta davvero scoperto, ed è quello che questo script tiene:
#   - a che punto è un piano: quali punti approvati non sono ancora chiusi
#   - quali registri il lavoro stava toccando
#   - se un cancello è aperto (perf/leak) o un controllo è rosso
#
#   --save   PreCompact:  scrive lo stato prima che il contesto venga riassunto
#   --load   PostCompact: lo restituisce come contesto aggiuntivo
#
# PORTING: TASKS è l'unico percorso da cambiare.

set -uo pipefail

ROOT=${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/.." && pwd)}
TASKS="$ROOT/docs/task/todo"
STATE="$ROOT/.claude/.compact-state"

case "${1:---load}" in
--save)
    {
        echo "## Stato del lavoro, salvato prima della compattazione"
        echo

        # I piani con punti approvati e non chiusi: è l'unica cosa che il contesto perde davvero.
        found=0
        for plan in "$TASKS"/*/action-plan.md; do
            [ -f "$plan" ] || continue
            appr=$(grep -cE '^\| [A-Z]{3}[0-9]{2} \| (approvato|da approvare) \|' "$plan" 2>/dev/null || true)
            fatti=$(grep -cE '^\| [A-Z]{3}[0-9]{2} \| \*\*fatto\*\*' "$plan" 2>/dev/null || true)
            [ "${appr:-0}" -eq 0 ] && [ "${fatti:-0}" -eq 0 ] && continue
            printf -- '- %s: %s fatti, %s ancora aperti\n' \
                "$(basename "$(dirname "$plan")")" "${fatti:-0}" "${appr:-0}"
            found=1
        done
        [ "$found" -eq 0 ] && echo "- nessun piano con punti aperti"
        echo

        # Cosa risulta toccato: dice quali registri il lavoro stava aggiornando.
        echo "### File modificati"
        (cd "$ROOT" && git status --short 2>/dev/null | head -20) || echo "- git non disponibile"
        echo

        # I cancelli aperti: un turno che riprende senza saperlo si blocca senza capire perché.
        pending=$(ls "$ROOT/.claude/.perf-gate" 2>/dev/null | wc -l | tr -d ' ')
        [ "$pending" -gt 0 ] && echo "### ATTENZIONE: cancello perf/leak aperto su $pending file — il turno non si chiuderà finché non è liberato"
        echo
        echo "_Salvato il $(date +%F\ %T)._"
    } > "$STATE" 2>/dev/null || true
    echo '{}'
    ;;

--load)
    [ -f "$STATE" ] || { echo '{}'; exit 0; }
    # Lo stato si consuma: riproporlo a ogni compattazione successiva lo renderebbe rumore.
    ctx=$(cat "$STATE" | tr -d '"' | tr '\n' '~')
    rm -f "$STATE"
    printf '{"hookSpecificOutput":{"hookEventName":"PostCompact","additionalContext":"%s"}}\n' \
        "$(printf '%s' "$ctx" | sed 's/~/\\n/g')"
    ;;

*)
    echo "uso: $0 --save | --load" >&2
    exit 2
    ;;
esac
