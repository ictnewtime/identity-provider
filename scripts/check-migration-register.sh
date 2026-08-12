#!/usr/bin/env bash
# Fa rispettare la REGOLA DI CONSERVAZIONE (docs/ai/full/migration-register.md): niente si scioglie senza
# che ogni sua sezione sia passata per il registro.
#
# L'unità non è il file, è la SEZIONE: un file di procedura contiene affermazioni che vanno in posti
# diversi, e un registro per file perderebbe esattamente ciò che dovrebbe proteggere.
#
# Il controllo non dice se una ricollocazione è GIUSTA — quello lo stabilisce chi rilegge la
# destinazione, ed è `man`. Dice che nessuna sezione è sparita senza che qualcuno l'abbia guardata.
#
# Uso:   ./scripts/check-migration-register.sh             copertura, exit 1 se manca qualcosa
#        ./scripts/check-migration-register.sh --json      output per l'hook
#        ./scripts/check-migration-register.sh --generate  stampa le righe mancanti, da incollare
#
# PORTING: SRC e REG sono gli unici percorsi da cambiare.

set -uo pipefail

ROOT=${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/.." && pwd)}
SRC="$ROOT/docs/ai"
REG="$SRC/full/migration-register.md"
MODE=${1:-text}

[ -d "$SRC" ] || { [ "$MODE" = "--json" ] && echo '{}'; exit 0; }

# Ogni sezione ## o ### della meta-doc, esclusi i report (registro cronologico, non procedura) e il
# registro stesso.
sections=$(find "$SRC" -name '*.md' ! -path '*/reports/*' ! -name 'migration-register.md' \
    -print0 2>/dev/null | xargs -0 awk '
    /^###?#? / {
        f = FILENAME
        sub(/^.*\/docs\/ai\//, "", f)
        title = $0
        sub(/^#+ /, "", title)
        print f "\t" title
    }' | sort)

n_src=$(echo "$sections" | grep -c . || echo 0)

if [ ! -f "$REG" ]; then
    if [ "$MODE" = "--json" ]; then
        printf '{"systemMessage": "Registro di migrazione assente: %s sezioni non censite."}\n' "$n_src"
    elif [ "$MODE" = "--generate" ]; then
        echo "$sections" | awk -F'\t' '{printf "| `%s` | %s | censito | — | — |\n", $1, $2}'
    else
        echo "Registro assente: $REG"
        echo "Genera le righe con: ./scripts/check-migration-register.sh --generate"
    fi
    [ "$MODE" = "--json" ] && exit 0
    exit 1
fi

missing=""
while IFS=$'\t' read -r file title; do
    [ -n "${file:-}" ] || continue
    # La riga del registro deve citare il file e il titolo della sezione.
    if ! grep -Fq "\`$file\`" "$REG" || ! grep -F "\`$file\`" "$REG" | grep -Fq "$title"; then
        missing="${missing}${file} → ${title}"$'\n'
    fi
done <<< "$sections"

missing=$(printf '%s' "$missing" | sed '/^$/d')
n_missing=$([ -n "$missing" ] && echo "$missing" | wc -l | tr -d ' ' || echo 0)

if [ "$MODE" = "--generate" ]; then
    [ -z "$missing" ] && { echo "Nessuna sezione mancante."; exit 0; }
    echo "$missing" | sed 's/ → /\t/' | awk -F'\t' '{printf "| `%s` | %s | censito | — | — |\n", $1, $2}'
    exit 0
fi

if [ "$n_missing" -eq 0 ]; then
    n_ver=$(grep -cE '\| \*?\*?verificato\*?\*? \|' "$REG" 2>/dev/null); n_ver=${n_ver:-0}
    if [ "$MODE" = "--json" ]; then echo '{}'; else
        echo "Registro di migrazione ok: $n_src sezioni censite, $n_ver verificate"
    fi
    exit 0
fi

if [ "$MODE" = "--json" ]; then
    msg=$(echo "$missing" | head -3 | tr '\n' ';' | tr -d '"')
    printf '{"systemMessage": "Registro di migrazione: %s sezioni su %s non censite. %s"}\n' "$n_missing" "$n_src" "$msg"
    exit 0
fi

echo "$n_missing sezioni su $n_src NON sono a registro:"
echo "  (una sezione non censita è una sezione che può sparire senza che nessuno se ne accorga)"
echo
echo "$missing"
exit 1
