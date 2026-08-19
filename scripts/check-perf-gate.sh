#!/usr/bin/env bash
# Rende NON SALTABILE il controllo perf/leak su ogni modifica di un service. È una policy
# dell'organizzazione senza eccezioni, quindi non può dipendere da meccanismi la cui disponibilità
# non è accertata: qui sono due hook di tipo `command`, gli stessi che in questo repo già funzionano.
#
#   --mark <file>   PostToolUse su Edit|Write: se il file è un service, lascia un marcatore.
#   --gate          Stop: se ci sono marcatori senza revisione, BLOCCA la chiusura del turno.
#   --clear         registra che la revisione è stata fatta e libera i marcatori.
#
# La differenza con una checklist: una checklist si applica quando qualcuno se ne ricorda. Qui il
# turno non finisce. Il rischio che resta è il contrario — un cancello che non si riesce a
# soddisfare — ed è per questo che `--clear` esiste e che il rollback è scritto.
#
# PORTING: SERVICE_RE è l'unico elenco da adattare.

set -uo pipefail

ROOT=${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/.." && pwd)}
STATE="$ROOT/.claude/.perf-gate"

# Cosa conta come service in questo progetto: ciò che orchestra un flusso o parla con un sistema
# esterno. NON le unità pure.
#
# Fino al 2026-08-07 la riga includeva `/src/libs/` per intero, quando in quella cartella stava
# tutto. Dopo TLT07 i service hanno la loro (`/src/service/`), i client la loro (`/src/clients/`) e
# in `/src/libs/` restano funzioni senza effetti: tenerla dentro avrebbe chiesto la revisione perf
# per una modifica a `chunker.js`, che è il modo più rapido per insegnare a liberare il cancello
# senza leggerlo.
SERVICE_RE='(/src/service/|/src/services/|/src/clients/|/app/Services/|\.service\.(js|ts)$|/app/Http/|/app/Jobs/|/app/Console/)'

mode=${1:-}
shift || true

case "$mode" in
--mark)
    # Il percorso arriva come argomento oppure dal JSON dell'evento su stdin. La seconda strada
    # esiste per non dipendere dai segnaposto `${tool_input.…}`, la cui disponibilità in questo
    # ambiente non è ancora accertata (TMD46): se non fossero espansi, il marcatore non scatterebbe
    # e il cancello sembrerebbe funzionare non trovando mai niente — il guasto peggiore possibile.
    f=${1:-}
    if [ -z "$f" ] || [ "$f" = '${tool_input.file_path}' ]; then
        f=$(cat 2>/dev/null | sed -n 's/.*"file_path"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -1)
    fi
    [ -n "$f" ] || exit 0
    printf '%s' "$f" | grep -qE "$SERVICE_RE" || exit 0
    mkdir -p "$STATE" 2>/dev/null || exit 0
    key=$(printf '%s' "$f" | tr '/' '_')
    printf '%s\n' "$f" > "$STATE/$key"
    exit 0
    ;;

--clear)
    mkdir -p "$STATE" 2>/dev/null
    files=$(cat "$STATE"/* 2>/dev/null | sort -u | tr '\n' ' ')
    rm -f "$STATE"/* 2>/dev/null
    printf '%s  revisione perf/leak dichiarata su: %s\n' "$(date +%F\ %T)" "${files:-nessun file}" \
        >> "$ROOT/.claude/perf-reviews.log"
    echo "Cancello perf/leak liberato. Registrato in .claude/perf-reviews.log"
    exit 0
    ;;

--gate|"")
    pending=$(ls "$STATE" 2>/dev/null | wc -l | tr -d ' ')
    [ "$pending" -gt 0 ] || { echo '{}'; exit 0; }
    files=$(cat "$STATE"/* 2>/dev/null | sort -u | head -5 | tr '\n' ';')
    reason="Controllo perf/leak non eseguito su ${pending} file di service modificati: ${files} E' una policy senza eccezioni. Esegui la revisione voce per voce (query N+1 / data leakage / scope tenant / memoria e streaming / query non vincolate, piu' i timeout), dichiarane l'esito, poi libera il cancello con: ./scripts/check-perf-gate.sh --clear"
    printf '{"decision":"block","reason":"%s"}\n' "$reason"
    exit 0
    ;;

*)
    echo "uso: $0 --mark <file> | --gate | --clear" >&2
    exit 2
    ;;
esac
