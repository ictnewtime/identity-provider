#!/usr/bin/env bash
# Fa rispettare l'univocità degli ID: due documenti non possono dichiarare la stessa sigla.
#
# Guarda TUTTO docs/, done/ compreso: una sigla non si libera mai, nemmeno quando il task che la
# usava è chiuso (docs/ai/abstract/identifiers.md).
#
# Uso:   ./scripts/check-ids.sh          elenco delle sigle, exit 1 se ci sono duplicati
#        ./scripts/check-ids.sh --json   output per l'hook di Claude Code
#
# Riconosce le dichiarazioni scritte come:   **Identificatori** ... `SIGLA` = ...
#
# LA FORMA DI UNA SIGLA — correzione di AVU10, il 2026-08-06.
#
# Prima di oggi il controllo prendeva QUALSIASI `` `token` = `` entro tre righe da
# `**Identificatori**`, senza guardare com'era fatto il token. Tre piani d'azione avevano, due righe
# sotto la dichiarazione, la legenda di una colonna — `` `auto` = uno script lo stabilisce, `man` =
# qualcuno lo legge `` — e il controllo ha dichiarato `auto` e `man` «sigle duplicate», exit 1, su
# documenti corretti. Un controllo che fallisce a vuoto si smette di leggere, e da quel momento non
# segnala più nemmeno i duplicati veri: è l'unica cosa per cui esiste.
#
# Ora un token si accetta solo se ha la FORMA di una sigla: due-quattro lettere MAIUSCOLE, più
# l'eventuale lettera minuscola della disambiguazione (`TTCa`, docs/ai/abstract/identifiers.md).
# `auto` e `man` non passano; `TMC`, `ATO`, `VKD`, `TPO`, `LFDT` sì.
#
# Il verso opposto è il rischio peggiore: un filtro troppo stretto smette di trovare i duplicati
# veri, e il verde si legge come garanzia. Per questo gli scarti si STAMPANO in modo `text`: se il
# filtro butta via una sigla vera, si vede invece di sparire in silenzio.
#
# COSA NON COPRE (ABA16), perché un controllo che non lo dichiara sembra coprire tutto:
#   - le dichiarazioni scritte SENZA `=` — «**Identificatori**: `LFDT`, gli stessi della proposta», o
#     con un trattino al posto dell'uguale. Il 2026-08-06 sono TRE e restano invisibili: `ABA`
#     (docs/ai/full/backlog.md), `LFDT` e `LFTS` (i due piani `20260803-lead-fetcher-*`) — contate
#     rifacendo l'estrazione qui sotto senza la condizione sul segno `=`.
#     Il segno `=` resta il solo innesco perché allargarlo rimetterebbe dentro AVU10 dall'altro lato:
#     una sigla CITATA in prosa accanto a una dichiarazione verrebbe letta come dichiarata. Succede
#     già — docs/ai/full/backlog.md dichiara `ATO` e due righe sotto cita `ATL`, la sigla storica — e il
#     giorno in cui la sigla citata appartiene a un ALTRO documento il controllo inventa un duplicato;
#   - la dichiarazione oltre la TERZA riga dalla riga `**Identificatori**`;
#   - le sigle di forma diversa da quella scritta sopra: con cifre, con cinque lettere o più. Il
#     2026-08-06 non ne esiste nessuna — elencando i token della finestra che NON passano il filtro
#     escono solo parole di legenda, percorsi, ID interi (`LVU03`) e il segnaposto `<SIGLA>` del
#     modello — ma se nascessero questo controllo non le vedrebbe;
#   - l'univocità dell'ID INTERO. «Un ID non si riusa mai» è l'altra metà della convenzione: qui si
#     verifica solo che la SIGLA non sia condivisa da due documenti, non che `TMC10` esista una volta
#     sola né che un progressivo chiuso non venga riassegnato;
#   - tutto ciò che sta fuori da `docs/`: le sigle citate in `.claude/`, negli script o nei commit non
#     entrano nel conteggio;
#   - i blocchi di codice non vengono saltati: una dichiarazione d'esempio dentro un fence conta come
#     vera.

set -uo pipefail

ROOT=${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/.." && pwd)}
DOCS="$ROOT/docs"
MODE=${1:-text}

[ -d "$DOCS" ] || { [ "$MODE" = "--json" ] && echo '{}'; exit 0; }

# esito<TAB>token<TAB>file per ogni `token` = trovato. La dichiarazione può occupare più righe: si
# guarda la riga con **Identificatori** e le due successive, e si prendono i `token` in backtick.
# L'esito dice se il token ha la forma di una sigla: gli scarti si stampano, non si perdono.
#
# La forma è scritta a lettere ripetute e NON come `[A-Z]{2,4}`: mawk 1.3.4 sbaglia gli intervalli
# accanto a un letterale — misurato il 2026-08-06 su questa macchina, `` `[A-Z]{3,4}` `` trova
# `` `TMC` `` e `` `[A-Z]{2,4}` `` no, sulla stessa riga. Non si tocca per farla più elegante.
raw=$(find "$DOCS" -name '*.md' -print0 | xargs -0 awk '
    FNR==1 { hold = 0 }
    /^\*\*Identificatori\*\*/ { hold = 3 }
    hold > 0 {
        line = $0
        while (match(line, /`[A-Za-z][A-Za-z0-9-]*`[ ]*=/)) {
            tok = substr(line, RSTART+1, RLENGTH-1)
            sub(/`[ ]*=$/, "", tok)
            f = FILENAME
            kind = (tok ~ /^[A-Z][A-Z][A-Z]?[A-Z]?[a-z]?$/) ? "sigla" : "scarto"
            print kind "\t" tok "\t" f
            line = substr(line, RSTART+RLENGTH)
        }
        hold--
    }
' | sed "s|$ROOT/||" | sort -u)

decl=$(printf '%s\n' "$raw" | awk -F'\t' '$1 == "sigla" { print $2 "\t" $3 }')
scarti=$(printf '%s\n' "$raw" | awk -F'\t' '$1 == "scarto" { print $2 "\t" $3 }')

# Gli scarti non sono un errore: sono la prova che il filtro non sta nascondendo nulla. Si mostrano
# solo in modo `text` — in `--json` l'hook parla solo quando blocca.
show_scarti() {
    [ "$MODE" = "--json" ] && return 0
    [ -n "$scarti" ] || return 0
    echo
    echo "Scartati perché non hanno forma di sigla (due-quattro maiuscole):"
    echo "$scarti" | awk -F'\t' '{printf "  %-8s %s\n", $1, $2}'
}

if [ -z "$decl" ]; then
    if [ "$MODE" = "--json" ]; then echo '{}'; else echo "Nessuna sigla dichiarata: niente da verificare"; fi
    show_scarti
    exit 0
fi

dups=$(echo "$decl" | cut -f1 | sort | uniq -d)

if [ -z "$dups" ]; then
    count=$(echo "$decl" | wc -l | tr -d ' ')
    if [ "$MODE" = "--json" ]; then echo '{}'; else
        echo "ID ok: $count sigle dichiarate, nessun duplicato"
        echo
        echo "$decl" | awk -F'\t' '{printf "  %-8s %s\n", $1, $2}'
        show_scarti
    fi
    exit 0
fi

detail=$(for d in $dups; do echo "$decl" | awk -F'\t' -v s="$d" '$1==s {printf "%s in %s; ", s, $2}'; done)

if [ "$MODE" = "--json" ]; then
    printf '{"systemMessage": "Sigle duplicate: %s Una sigla non si libera mai: sceglierne una nuova (docs/ai/abstract/identifiers.md)."}\n' "$(echo "$detail" | tr -d '"')"
    exit 0
fi

echo "Sigle duplicate — l'ID non è più univoco in docs/:"
echo
for d in $dups; do
    echo "  $d"
    echo "$decl" | awk -F'\t' -v s="$d" '$1==s {print "    - "$2}'
done
show_scarti
echo
echo "Una sigla non si libera mai: chi arriva dopo ne sceglie un'altra."
exit 1
