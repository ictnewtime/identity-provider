#!/usr/bin/env bash
# Verifica i collegamenti interni della documentazione. Tre controlli distinti:
#
#   1. ROTTI    — il file di destinazione deve esistere.
#   2. BUGIARDI — se il testo del link è scritto come un percorso, deve descrivere la destinazione
#                 vera. `[backlog.md](./todo.md)` risolve, ed è falso: il difetto AVU/VKD di un
#                 link bugiardo è che chi legge si fida del testo e non apre.
#   3. ANCORE   — il frammento dopo `#` deve esistere: un titolo che produca quello slug, o un numero
#                 di riga entro la fine del file. Aggiunto il 2026-08-06 (AVU11, punto TSA02): il
#                 file giusto con la sezione sbagliata è il caso peggiore, perché chi segue il rimando
#                 arriva, non trova, e ci scrive dentro. Al primo giro ha trovato 32 ancore morte —
#                 24 da due spostamenti dichiarati e mai propagati, 2 riferimenti di riga oltre la
#                 fine di file di codice, una sopravvissuta alla rinomina `ATN3` → `ABA03`.
#
# COSA NON COPRE (ABA16), perché un controllo che non lo dichiara sembra coprire tutto:
#   - le citazioni di sezione **in prosa**: `(§ Quando una voce si sposta)` non è un link, e una di
#     queste puntava a un titolo sciolto dentro il file che la conteneva;
#   - i link **esterni** (`http://`, `https://`): non si esce in rete da un controllo di fine turno;
#   - se il testo del link **descrive** la sezione giusta: si verifica che l'ancora esista, non che il
#     titolo dica quello che il testo promette;
#   - i riferimenti di riga entro il file **puntano ancora al codice giusto**? Si verifica solo che la
#     riga esista. Una funzione che si sposta di venti righe non viene rilevata.
#
# Riconosce due forme di percorso:
#   /docs/ai/rules.md    dalla radice del repo — quello da usare quando si cambia cartella
#   ./vicino.md          relativo — ammesso solo dentro la stessa cartella
#
# I **blocchi di codice** vengono saltati: un link dentro un fence è un esempio, non un collegamento.
# `docs/task/done/` è escluso dal controllo 2: è storia, e la storia non si riscrive
# (docs/ai/abstract/planning.md).
#
# Uso:   ./scripts/check-links.sh          elenco, exit 1 se trova qualcosa
#        ./scripts/check-links.sh --json   output per l'hook di Claude Code

set -uo pipefail

ROOT=${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/.." && pwd)}
MODE=${1:-text}

[ -d "$ROOT/docs" ] || { [ "$MODE" = "--json" ] && echo '{}'; exit 0; }

# file<TAB>testo<TAB>destinazione per ogni link markdown, saltando i blocchi di codice.
links=$(find "$ROOT/docs" -name '*.md' -print0 2>/dev/null | xargs -0 awk '
    FNR == 1 { infence = 0 }
    /^[ \t]*```/ { infence = !infence; next }
    infence { next }
    {
        line = $0
        while (match(line, /\[[^]]*\]\((\.|\/|#)[^)]*\)/)) {
            m = substr(line, RSTART, RLENGTH)
            p = index(m, "](")
            text = substr(m, 2, p - 2)
            target = substr(m, p + 2, length(m) - p - 2)
            print FILENAME "\t" text "\t" target
            line = substr(line, RSTART + RLENGTH)
        }
    }
')

broken=""
lying=""
dangling=""
n_frag=0

# Gli slug dei titoli di un file, nella forma che usa la forge: si toglie ciò che non è
# alfanumerico/spazio/trattino/underscore, si abbassa, e OGNI spazio diventa UN trattino — le corse di
# spazi non si collassano. È la ragione per cui `## VKD28 — il consumer …` produce `vkd28--il-consumer…`
# con due trattini: l'em-dash spariscee lascia due spazi. Lo slug non lo scelgo io: il frammento deve
# funzionare nel browser di chi legge, non in questo controllo.
declare -A SLUG_CACHE

slugs_of() {
    local file=$1
    if [ -n "${SLUG_CACHE[$file]+x}" ]; then printf '%s' "${SLUG_CACHE[$file]}"; return; fi
    local out
    out=$(awk '
        FNR == 1 { infence = 0 }
        /^[ \t]*```/ { infence = !infence; next }
        infence { next }
        /^#{1,6}[ \t]/ {
            sub(/^#{1,6}[ \t]+/, "")
            gsub(/`/, ""); gsub(/\*\*/, ""); gsub(/\*/, ""); gsub(/~~/, "")
            while (match($0, /\[[^]]*\]\([^)]*\)/)) {
                m = substr($0, RSTART, RLENGTH); p = index(m, "](")
                $0 = substr($0, 1, RSTART - 1) substr(m, 2, p - 2) substr($0, RSTART + RLENGTH)
            }
            print
        }
    ' "$file" 2>/dev/null | tr '[:upper:]' '[:lower:]' \
      | sed -E 's/[^[:alnum:][:space:]_-]//g; s/[[:space:]]/-/g')
    SLUG_CACHE[$file]=$out
    printf '%s' "$out"
}

while IFS=$'\t' read -r f text target; do
    [ -n "${f:-}" ] || continue
    rel=${f#$ROOT/}
    dir=$(dirname "$f")
    path_part=${target%%#*}

    # 1. Il file esiste? Un'ancora nello STESSO file — `](#sezione)` — non ha percorso: il bersaglio è
    #    il file stesso. Dodici rimandi di questa forma passavano fuori dal controllo perché la
    #    condizione era «se non c'è percorso, salta».
    if [ -z "$path_part" ]; then
        abs=$f
    else
        case "$path_part" in
            /*) abs="$ROOT$path_part" ;;
            *)  abs="$dir/$path_part" ;;
        esac
        if [ ! -e "$abs" ]; then
            broken="${broken}${rel} → ${target}"$'\n'
            continue
        fi
    fi

    # 3. Il FRAMMENTO esiste? Il file giusto con la sezione sbagliata è il caso peggiore: chi segue il
    #    rimando arriva, non trova, e ci scrive dentro. Due regole distinte, perché sono due cose
    #    diverse — trattarle uguali segnalerebbe i ~40 riferimenti di riga come ancore inesistenti.
    case "$target" in
        *"#"*)
            frag=${target#*#}
            if [ -n "$frag" ] && [ -f "$abs" ]; then
                n_frag=$((n_frag + 1))
                case "$frag" in
                    L[0-9]*)
                        # Riferimento di riga: #L42 o #L205-L216. Vale il numero più alto, e vale su
                        # QUALSIASI file: 102 di questi rimandi puntano dentro `.js`, `.php` e `.sh`,
                        # e una riga citata invecchia a ogni modifica del sorgente.
                        last=$(printf '%s' "$frag" | tr -cd '0-9-' | tr '-' '\n' | sort -n | tail -1)
                        total=$(wc -l < "$abs" 2>/dev/null | tr -d ' ')
                        if [ -n "$last" ] && [ -n "$total" ] && [ "$last" -gt "$total" ] 2>/dev/null; then
                            dangling="${dangling}${rel} → ${target}   (il file ha ${total} righe)"$'\n'
                        fi ;;
                    *)
                        # Ancora di titolo: solo su markdown, perché solo lì i titoli esistono.
                        case "$path_part" in
                            *.md|"")
                                if ! printf '%s\n' "$(slugs_of "$abs")" | grep -qxF -- "$frag"; then
                                    dangling="${dangling}${rel} → ${target}   (nessun titolo produce questa ancora)"$'\n'
                                fi ;;
                            *) n_frag=$((n_frag - 1)) ;;
                        esac ;;
                esac
            fi ;;
    esac

    # 2. Il testo descrive la destinazione? Solo se il testo È scritto come un percorso.
    #    Le tre normalizzazioni sotto evitano i falsi positivi che rendono un controllo ignorabile
    #    (docs/ai/full/backlog.md, AVU10): un controllo che grida sul corretto smette di essere letto.
    case "$rel" in docs/task/done/*) continue ;; esac   # storia: non si riscrive

    claim=$text
    claim=${claim//\`/}                       # i backtick sono formattazione, non parte del percorso
    claim=$(printf '%s' "$claim" | sed -E 's/:[0-9]+(-[0-9]+)?$//')   # suffisso :riga, convenzione di F1
    claim=${claim#./}; claim=${claim#/}

    case "$claim" in *" "*) continue ;; esac   # una frase non promette un percorso
    case "$claim" in *.*) ;; *) continue ;; esac

    # Si confronta il percorso RISOLTO, non la stringa: `./task/x.md` e `docs/task/x.md` sono lo
    # stesso file scritto in due modi, e segnalarli sarebbe rumore.
    real=$(realpath -m --relative-to="$ROOT" "$abs" 2>/dev/null) || real=${path_part#/}

    # Se il testo e' scritto come percorso RELATIVO (../ o ./), va risolto come lo e' stata la
    # destinazione: confrontare una forma relativa con una risolta e' il quarto falso positivo
    # trovato oggi, e un controllo che grida sul corretto si smette di leggere (AVU10).
    case "$text" in
        */../*|../*) claim_abs=$(realpath -m --relative-to="$ROOT" "$dir/$claim" 2>/dev/null)
                     [ -n "$claim_abs" ] && claim=$claim_abs ;;
    esac

    case "$claim" in
        */*) case "/$real" in *"/$claim") ;; *) lying="${lying}${rel}: [${text}] → ${real}"$'\n' ;; esac ;;
        *)   [ "$(basename "$real")" = "$claim" ] || lying="${lying}${rel}: [${text}] → ${real}"$'\n' ;;
    esac
done <<< "$links"

broken=$(printf '%s' "$broken" | sed '/^$/d')
lying=$(printf '%s' "$lying" | sed '/^$/d')
dangling=$(printf '%s' "$dangling" | sed '/^$/d')
nb=$([ -n "$broken" ] && echo "$broken" | wc -l | tr -d ' ' || echo 0)
nl=$([ -n "$lying" ] && echo "$lying" | wc -l | tr -d ' ' || echo 0)
nd=$([ -n "$dangling" ] && echo "$dangling" | wc -l | tr -d ' ' || echo 0)

if [ "$nb" -eq 0 ] && [ "$nl" -eq 0 ] && [ "$nd" -eq 0 ]; then
    if [ "$MODE" = "--json" ]; then echo '{}'; else
        echo "Link ok: nessun collegamento rotto né bugiardo in docs/, e $n_frag frammenti verificati"
    fi
    exit 0
fi

if [ "$MODE" = "--json" ]; then
    msg=$(printf '%s\n%s\n%s' "$broken" "$lying" "$dangling" | sed '/^$/d' | head -4 | tr '\n' ';' | tr -d '"')
    printf '{"systemMessage": "Link: %s rotti, %s bugiardi, %s ancore inesistenti su %s frammenti. %s"}\n' "$nb" "$nl" "$nd" "$n_frag" "$msg"
    exit 0
fi

[ "$nb" -gt 0 ] && { echo "$nb link ROTTI — la destinazione non esiste:"; echo; echo "$broken"; echo; }
[ "$nl" -gt 0 ] && {
    echo "$nl link BUGIARDI — il testo è un percorso e non descrive la destinazione:"
    echo "  (chi legge si fida del testo e non apre. Si corregge il testo, non il link.)"
    echo
    echo "$lying"
}
[ "$nd" -gt 0 ] && {
    echo
    echo "$nd ANCORE INESISTENTI — il file c'è, la sezione no (su $n_frag frammenti verificati):"
    echo "  (è il caso peggiore: chi segue il rimando arriva, non trova, e ci scrive dentro. Nasce"
    echo "   da uno spostamento con i riferimenti lasciati indietro — R11.)"
    echo
    echo "$dangling"
}
exit 1
