#!/usr/bin/env bash
# Tara la stima byte→token, su cui poggiano TUTTE le soglie della meta-doc (TMD45, i tetti delle
# foglie, la soglia di 2.000). Oggi vale 4 byte/token ed è **un'assunzione mai verificata**: se il
# rapporto vero fosse 3,5 o 4,5, ogni cifra scritta si sposta del 15%.
#
# L'agente non può misurarlo: `/context` è un comando del developer. Questo script prepara il
# confronto, così la taratura costa una lettura sola invece di una sessione.
#
# Uso:   ./scripts/calibrate-tokens.sh                 le stime sui file caricati sempre
#        ./scripts/calibrate-tokens.sh <file>          su un file specifico
#        ./scripts/calibrate-tokens.sh --set 3.7       riscrive BYTES_PER_TOKEN in check-docs-graph.sh

set -uo pipefail
ROOT=${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/.." && pwd)}

if [ "${1:-}" = "--set" ]; then
    v=${2:?serve il valore, es. 3.7}
    sed -i "s/^BYTES_PER_TOKEN=.*/BYTES_PER_TOKEN=$v/" "$ROOT/scripts/check-docs-graph.sh"
    echo "BYTES_PER_TOKEN = $v. Rilancia ./scripts/check-docs-graph.sh: tutte le soglie si spostano."
    exit 0
fi

target=${1:-"$ROOT/CLAUDE.md $ROOT/docs/ai/index.md"}
bytes=$(cat $target 2>/dev/null | wc -c)
words=$(cat $target 2>/dev/null | wc -w)
lines=$(cat $target 2>/dev/null | wc -l)

echo "Taratura byte → token"
echo
printf '  file            %s\n' "$(echo $target | sed "s|$ROOT/||g")"
printf '  byte            %s\n' "$bytes"
printf '  parole          %s\n' "$words"
printf '  righe           %s\n' "$lines"
echo
echo "  Le stime possibili:"
printf '    byte/4.0 (in uso)   %6d token\n' "$(( bytes * 10 / 40 ))"
printf '    byte/3.5            %6d token\n' "$(( bytes * 10 / 35 ))"
printf '    byte/4.5            %6d token\n' "$(( bytes * 10 / 45 ))"
printf '    parole x 1.6        %6d token\n' "$(( words * 16 / 10 ))"
echo
echo "  COSA FARE, ed è una lettura sola:"
echo "    1. in una sessione appena avviata, lancia  /context"
echo "    2. leggi il valore dei 'Memory files' (CLAUDE.md e ciò che importa)"
echo "    3. dividi i byte qui sopra per quel numero → è il rapporto vero"
echo "    4. ./scripts/calibrate-tokens.sh --set <rapporto>"
echo
echo "  Finché non è fatto, ogni soglia della meta-doc porta un'incertezza del ~15%."
