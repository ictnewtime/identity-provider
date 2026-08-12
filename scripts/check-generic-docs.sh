#!/usr/bin/env bash
# Fa rispettare R9: i file di PROCEDURA della meta-doc non nominano nulla di questo progetto.
#
# File di procedura: index.md, rules.md, docs-structure.md, manual-tests.md, porting.md, README.md,
# action-plan-template.md e le fasi senza suffisso -custom.
# File di istanza (esclusi): *-custom.md, todo.md, backlog.md, vulnerability.md, full/procedure-defects.md
#   — registrano lo stato di QUESTO progetto, quindi i nomi veri li devono contenere (R9).
#
# Uso:   ./scripts/check-generic-docs.sh          output leggibile, exit 1 se trova qualcosa
#        ./scripts/check-generic-docs.sh --json   output JSON per l'hook di Claude Code
#
# PORTING: la lista qui sotto è l'**unico** posto in cui stanno i nomi propri del progetto.
# Su un altro repo si riscrive questa e basta (vedi docs/ai/dev-guide/porting.md).

set -uo pipefail

NAMES=${DOCS_PROJECT_NAMES:-'rabbit|vtiger|salaros|infisical|cronicle|mailpit|melzi|lead-fetcher|consumer-lead|numeri_primi|facebook|discord|sqlite|lead-req|lead-dlq|lead-err|lead-retry|php artisan|docker|npm '}

ROOT=${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/.." && pwd)}
DOCS="$ROOT/docs/ai"
MODE=${1:-text}

[ -d "$DOCS" ] || { [ "$MODE" = "--json" ] && echo '{}'; exit 0; }

hits=$(grep -rniE "$NAMES" "$DOCS" --include='*.md' 2>/dev/null \
    | grep -vE 'custom\.md|/todo\.md|/backlog\.md|/vulnerability\.md|/procedure-defects\.md|/reports/' \
    | sed "s|$ROOT/||")

if [ -z "$hits" ]; then
    if [ "$MODE" = "--json" ]; then echo '{}'; else echo "R9 ok: nessun nome proprio nei file di procedura"; fi
    exit 0
fi

count=$(echo "$hits" | wc -l | tr -d ' ')

if [ "$MODE" = "--json" ]; then
    # Una riga sola, senza virgolette non escapate: l'hook deve restare JSON valido.
    msg=$(echo "$hits" | head -5 | cut -c1-160 | tr '\n' ';' | tr -d '"')
    printf '{"systemMessage": "R9 violata: %s occorrenze di nomi propri nei file di procedura della meta-doc. %s"}\n' "$count" "$msg"
    exit 0
fi

echo "R9 violata: $count occorrenze di nomi propri in file di procedura."
echo "Vanno spostate nel gemello *-custom.md, non cancellate (docs/ai/rules.md, R9)."
echo
echo "$hits"
exit 1
