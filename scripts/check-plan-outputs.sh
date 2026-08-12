#!/usr/bin/env bash
# Verifica che un punto dichiarato `fatto` abbia prodotto la sua USCITA, e dice quanti non ha coperto.
#
# Il difetto che corregge: il 2026-08-06 la chiusura di un task è stata valutata contando le righe
# `fatto` — 64 su 67 — e un controllo buttato in /tmp riportava «27 uscite su 27, zero mancanti». I
# punti `fatto` erano 64, quelli automatizzabili 42: `27 su 27` era un numeratore con tre denominatori
# possibili e nessuno scritto, e i punti scoperti non erano nominati da nessuna parte. Il controllo
# non era l'asserzione sbagliata: era che NESSUNO CONTAVA LE ASSENTI.
#
# Per questo la COPERTURA è il controllo e le asserzioni sono il contorno, e vale nei due versi:
#   - un punto `fatto` con V `auto` senza riga di manifesto è un FALLIMENTO che nomina l'ID;
#   - una riga di manifesto per un ID che nel piano non è `fatto` è un FALLIMENTO — altrimenti si
#     pre-compila il manifesto e si dichiara dopo.
#
# COSA NON COPRE (ABA16: un controllo dichiara la parte di regola che non applica):
#   - i punti con V `man` non hanno un'uscita meccanica: si pretende che siano ELENCATI con la ragione
#     dell'esclusione, non che siano verificati. 22 righe elencate non sono 22 righe verificate;
#   - `contains` verifica che una stringa ci sia, non che dica il vero;
#   - il vocabolario è CHIUSO a sei tipi: un'uscita non esprimibile va scritta come `man` con la
#     ragione, non forzata in `exists`. Una riga `man` su un punto `auto` è un fallimento.
#
# Perché il vocabolario è chiuso e non una riga di shell per asserzione: un manifesto diventerebbe
# codice arbitrario eseguito da un hook a ogni turno.
#
# Uso:   ./scripts/check-plan-outputs.sh                  tutti i manifesti, exit 1 sui fallimenti
#        ./scripts/check-plan-outputs.sh --json           output per l'hook Stop
#        ./scripts/check-plan-outputs.sh --closing <dir>  chiusura: il manifesto DEVE esistere
#
# Formato del manifesto: docs/ai/abstract/planning.md § Le uscite dei punti.
#
# PORTING: TASKS è l'unico percorso da cambiare su un altro repo.

set -uo pipefail

ROOT=${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/.." && pwd)}
TASKS="$ROOT/docs/task"
MODE=${1:-text}
CLOSING_DIR=${2:-}

cd "$ROOT" || exit 0

problems=""
n_ok=0
n_man=0
n_manifest=0

fail() { problems="${problems}$1"$'\n'; }

# Le pipe escapate dentro una cella di tabella spostano le colonne: `Edit\|Write` in TMD48 ha fatto
# contare 41 punti `auto` invece di 42. Si neutralizzano prima di tagliare, e si ripristinano dopo.
unpipe() { printf '%s' "$1" | sed 's/§PIPE§/|/g'; }

# Estrae una cella da una riga di tabella markdown, 1-based sulle celle interne.
cell() { printf '%s' "$1" | cut -d'|' -f"$(( $2 + 1 ))" | sed 's/^[[:space:]]*//; s/[[:space:]]*$//'; }

# Toglie i backtick e il grassetto che servono a chi legge, non al confronto.
plain() { printf '%s' "$1" | sed 's/^`//; s/`$//; s/^\*\*//; s/\*\*$//'; }

# L'espressione regolare di `contains` sta fra la PRIMA coppia di backtick della cella `Atteso`; il
# resto è la prosa che spiega perché quell'uscita conta. Prendere la cella intera fa entrare la prosa
# nel confronto: 22 righe corrette segnalate come rotte, e i due rilievi veri sepolti nel rumore.
# Prima stesura di questo script, corretta subito: è il caso negativo di TMC11, visto dall'interno.
first_code() {
    case "$1" in
        *'`'*'`'*) printf '%s' "$1" | sed 's/^[^`]*`//; s/`.*$//' ;;
        *) printf '%s' "$1" ;;
    esac
}

# I BERSAGLI SONO RELATIVI ALLA RADICE DEL REPO, sempre — non alla cartella del manifesto. Una sola
# regola invece di due: risolvere prima dalla radice e poi accanto al manifesto renderebbe ambiguo un
# bersaglio come `action-plan.md`, che esiste in undici cartelle di task.
verify_one() {
    local id=$1 kind=$2 target=$3 expected=$4 plan=$5
    local src dst

    case "$target" in
        /*) fail "$plan · $id · bersaglio assoluto: '$target'. I bersagli sono relativi alla radice del repo"; return 1 ;;
    esac

    case "$kind" in
        exists)
            [ -e "$target" ] && return 0
            fail "$plan · $id · exists: manca $target"; return 1 ;;
        exec)
            [ -f "$target" ] || { fail "$plan · $id · exec: manca $target"; return 1; }
            [ -x "$target" ] && return 0
            fail "$plan · $id · exec: $target esiste ma non è eseguibile"; return 1 ;;
        absent)
            [ -e "$target" ] || return 0
            fail "$plan · $id · absent: $target esiste ancora"; return 1 ;;
        contains)
            [ -f "$target" ] || { fail "$plan · $id · contains: manca $target"; return 1; }
            grep -qE -- "$expected" "$target" && return 0
            fail "$plan · $id · contains: $target non contiene /$expected/"; return 1 ;;
        no-match)
            # L'uscita di un punto che RIMUOVE è un'assenza, e un'assenza non è un percorso. Senza
            # questo tipo, quei punti finivano in un `absent` su un file inventato — una riga verde
            # che finge, cioè il difetto che questo controllo esiste per trovare.
            [ -e "$target" ] || { fail "$plan · $id · no-match: manca l'albero $target"; return 1; }
            # Il MANIFESTO si esclude da sé: contiene il pattern come testo da cercare, quindi
            # un'asserzione sull'assenza di una stringa troverebbe sempre la propria dichiarazione.
            # Trovato il 2026-08-07 su TPD04, che asserisce l'assenza di valori di credenziali (R6):
            # il solo riscontro era la riga del manifesto, e sembrava una violazione di R6.
            hits=$(grep -rlE -- "$expected" "$target" 2>/dev/null | grep -vFx -- "$plan" | wc -l | tr -d ' ')
            [ "${hits:-0}" -eq 0 ] && return 0
            fail "$plan · $id · no-match: /$expected/ compare ancora in $hits file sotto $target — $(grep -rlE -- "$expected" "$target" 2>/dev/null | grep -vFx -- "$plan" | head -3 | tr '\n' ' ')"; return 1 ;;
        moved)
            # Sorgente assente E destinazione presente, in un'asserzione sola: separate, si soddisfa
            # la prima cancellando e si chiama spostamento (R11).
            src=${target%% → *}; dst=${target##* → }
            if [ "$src" = "$dst" ]; then
                fail "$plan · $id · moved: bersaglio senza ' → ': $target"; return 1
            fi
            if [ -e "$src" ]; then
                fail "$plan · $id · moved: la sorgente $src esiste ancora"; return 1
            fi
            [ -e "$dst" ] && return 0
            fail "$plan · $id · moved: la destinazione $dst non esiste — spostamento a metà, contenuto perso"; return 1 ;;
        *)
            fail "$plan · $id · tipo non riconosciuto: '$kind' (ammessi: exists exec absent contains no-match moved man)"; return 1 ;;
    esac
}

check_manifest() {
    local manifest=$1
    local dir plan plan_file rel
    dir=$(dirname "$manifest")
    rel=${manifest#"$ROOT"/}; rel=${rel#./}
    n_manifest=$((n_manifest + 1))

    # Il manifesto dichiara il piano da cui legge gli stati: indovinarlo fra action-plan.md e waves.md
    # sarebbe la stessa approssimazione di ABA16.
    plan=$(sed -n 's/^[[:space:]]*piano:[[:space:]]*//p' "$manifest" | head -1)
    if [ -z "$plan" ]; then
        fail "$rel · manca la riga 'piano: <file>': senza il piano non si sa quali punti sono \`fatto\`"
        return
    fi
    plan_file="$dir/$plan"
    if [ ! -f "$plan_file" ]; then
        fail "$rel · il piano dichiarato non esiste: $plan_file"
        return
    fi

    # --- Gli stati dal piano: ID -> auto|man, solo per i punti `fatto`.
    local states
    states=$(sed 's/\\|/§PIPE§/g' "$plan_file" | awk -F'|' '
        /^\|[[:space:]]*[A-Z]{3}[0-9]+[[:space:]]*\|[[:space:]]*\*\*fatto\*\*/ {
            id=$2; v=$7;
            gsub(/^[[:space:]]+|[[:space:]]+$/,"",id);
            gsub(/[[:space:]]|`|\*/,"",v);
            if (v=="auto" || v=="man") print id" "v;
        }')

    if [ -z "$states" ]; then
        fail "$rel · nessun punto \`fatto\` trovato in $plan: il manifesto non ha nulla da coprire, o il piano non è nel formato atteso"
        return
    fi

    # --- Le righe del manifesto.
    local rows
    rows=$(sed 's/\\|/§PIPE§/g' "$manifest" | grep -E '^\|[[:space:]]*[A-Z]{3}[0-9]+[[:space:]]*\|')

    local id kind target expected line
    local seen_mech="" seen_man=""

    while IFS= read -r line; do
        [ -n "$line" ] || continue
        id=$(plain "$(cell "$line" 1)")
        kind=$(plain "$(cell "$line" 2)")
        target=$(unpipe "$(plain "$(cell "$line" 3)")")
        expected=$(cell "$line" 4)
        if [ "$kind" = "contains" ] || [ "$kind" = "no-match" ]; then
            expected=$(unpipe "$(first_code "$expected")")
        else
            expected=$(unpipe "$expected")
        fi

        # Verso opposto: una riga per un ID che nel piano non è `fatto`.
        local state
        state=$(printf '%s\n' "$states" | awk -v i="$id" '$1==i {print $2}')
        if [ -z "$state" ]; then
            fail "$rel · $id · riga di manifesto per un punto che nel piano non è \`fatto\`: si dichiara l'uscita prima del lavoro"
            continue
        fi

        if [ "$kind" = "man" ]; then
            if [ "$state" = "auto" ]; then
                fail "$rel · $id · riga \`man\` su un punto con V \`auto\`: l'esclusione non è una scelta di chi compila il manifesto"
                continue
            fi
            if [ -z "$expected" ]; then
                fail "$rel · $id · riga \`man\` senza la ragione dell'esclusione"
                continue
            fi
            n_man=$((n_man + 1))
            seen_man="$seen_man $id"
            continue
        fi

        if [ "$state" = "man" ]; then
            fail "$rel · $id · asserzione \`$kind\` su un punto con V \`man\`: il piano dice che si verifica a mano"
            continue
        fi
        if [ -z "$target" ]; then
            fail "$rel · $id · asserzione \`$kind\` senza bersaglio"
            continue
        fi
        if { [ "$kind" = "contains" ] || [ "$kind" = "no-match" ]; } && [ -z "$expected" ]; then
            fail "$rel · $id · \`contains\` senza la stringa attesa: verificherebbe solo che il file esista"
            continue
        fi

        if verify_one "$id" "$kind" "$target" "$expected" "$rel"; then
            n_ok=$((n_ok + 1))
        fi
        seen_mech="$seen_mech $id"
    done <<EOF
$rows
EOF

    # --- LA COPERTURA. È questo il controllo.
    local n_auto=0 n_man_plan=0 missing_auto="" missing_man=""
    while read -r pid pv; do
        [ -n "$pid" ] || continue
        if [ "$pv" = "auto" ]; then
            n_auto=$((n_auto + 1))
            case " $seen_mech " in *" $pid "*) ;; *) missing_auto="$missing_auto $pid" ;; esac
        else
            n_man_plan=$((n_man_plan + 1))
            case " $seen_man " in *" $pid "*) ;; *) missing_man="$missing_man $pid" ;; esac
        fi
    done <<EOF
$states
EOF

    if [ -n "$missing_auto" ]; then
        fail "$rel · punti \`fatto\` con V \`auto\` e nessuna uscita dichiarata:$missing_auto"
    fi
    if [ -n "$missing_man" ]; then
        fail "$rel · punti \`fatto\` con V \`man\` non elencati (serve una riga \`man\` con la ragione):$missing_man"
    fi

    if [ "$MODE" != "--json" ] && [ -z "$problems" ]; then
        echo "  $rel — $n_auto punti \`auto\` coperti, $n_man_plan \`man\` elencati e NON verificati"
    fi
}

# --- Modo chiusura: l'assenza del manifesto è fatale, ed è qui che deve esserlo.
if [ "$MODE" = "--closing" ]; then
    if [ -z "$CLOSING_DIR" ]; then
        echo "Uso: $0 --closing <cartella del task>"; exit 2
    fi
    CLOSING_DIR=${CLOSING_DIR%/}
    if [ ! -d "$CLOSING_DIR" ]; then
        echo "Chiusura rifiutata: $CLOSING_DIR non è una cartella"; exit 1
    fi
    if [ ! -f "$CLOSING_DIR/outputs.md" ]; then
        echo "Chiusura rifiutata: manca $CLOSING_DIR/outputs.md."
        echo "  Il criterio di chiusura ha tre gambe (docs/ai/abstract/planning.md): punti \`fatto\` o"
        echo "  \`scartato\` · uscite verificate · conferma di chi l'ha chiesto. Senza manifesto la"
        echo "  seconda si legge dalla tabella che il piano scrive di sé stesso."
        exit 1
    fi
    # PRIMA GAMBA: tutti i punti `fatto` o `scartato`. Aggiunta il 2026-08-07, perché mancava e non
    # era una dimenticanza innocua: `--closing` sul refactor rispondeva «Chiusura ammessa» mentre
    # `TMD09` era `implementato, non verificato` — uno stato che non è né l'uno né l'altro. Il
    # controllo presidiava la seconda gamba e il suo verde si leggeva come «chiudibile».
    # TRE STATI CHIUDONO UN PUNTO, non due — vocabolario del developer, 2026-08-07: `fatto`,
    # `scartato`, `spostato`. Il terzo è il punto che esce dal task verso un altro registro — tipico
    # todo-manual.md, che per regola non trattiene un task. `uscito dal task` è la forma con cui era
    # stato scritto TMD46 prima che il vocabolario avesse un nome: resta riconosciuta come sinonimo,
    # perché un piano già scritto non si riscrive per far tornare un controllo.
    plan_decl=$(sed -n 's/^[[:space:]]*piano:[[:space:]]*//p' "$CLOSING_DIR/outputs.md" | head -1)
    if [ -n "$plan_decl" ] && [ -f "$CLOSING_DIR/$plan_decl" ]; then
        aperti=$(sed 's/\\|/§PIPE§/g' "$CLOSING_DIR/$plan_decl" | awk -F'|' '
            /^\|[[:space:]]*[A-Z]{3}[0-9]+[[:space:]]*\|/ {
                id=$2; st=$3
                gsub(/^[[:space:]]+|[[:space:]]+$/,"",id)
                if (st !~ /fatto/ && st !~ /scartato/ && st !~ /spostato/ && st !~ /uscito dal task/) print id
            }' | tr '\n' ' ')
        if [ -n "$(printf '%s' "$aperti" | tr -d ' ')" ]; then
            echo "Chiusura rifiutata: punti né \`fatto\`, né \`scartato\`, né \`spostato\` — $aperti"
            echo "  Prima gamba del criterio (docs/ai/abstract/planning.md): un punto in uno stato"
            echo "  intermedio — 'implementato', 'parziale', 'da approvare' — non è chiuso. Si porta a"
            echo "  \`fatto\` verificandolo, a \`scartato\` col perché, o a \`spostato\` dicendo dove è"
            echo "  andato: sono tre stati diversi, e confonderli è il modo in cui un task si chiude"
            echo "  sembrando finito."
            exit 1
        fi
    fi

    check_manifest "$CLOSING_DIR/outputs.md"
    if [ -n "$problems" ]; then
        echo "Chiusura rifiutata — $(printf '%s' "$problems" | grep -c .) problemi:"
        echo
        printf '%s' "$problems"
        exit 1
    fi
    echo "Chiusura ammessa: $n_ok asserzioni verificate, $n_man punti \`man\` elencati e non verificati."
    exit 0
fi

# --- Modo normale: si verificano i manifesti CHE ESISTONO.
#
# Perché non tutti i task: accenderlo sulle cartelle senza manifesto dichiarerebbe non conformi nove
# task in un colpo, e un controllo che grida sul corretto si smette di leggere (TMC11). L'assenza è
# un debito DICHIARATO in docs/ai/full/backlog.md, e diventa fatale su --closing.
manifests=$(find "$TASKS" -name outputs.md -type f 2>/dev/null | sort)

if [ -z "$manifests" ]; then
    [ "$MODE" = "--json" ] && echo '{}' || echo "Nessun manifesto delle uscite in docs/task/: niente da verificare"
    exit 0
fi

[ "$MODE" = "--json" ] || echo "Uscite dei punti:"
while IFS= read -r m; do
    [ -n "$m" ] && check_manifest "$m"
done <<EOF
$manifests
EOF

if [ -z "$problems" ]; then
    if [ "$MODE" = "--json" ]; then echo '{}'; else
        echo
        echo "Uscite ok: $n_ok asserzioni verificate su $n_manifest manifesti, $n_man punti \`man\` elencati e non verificati"
    fi
    exit 0
fi

count=$(printf '%s' "$problems" | grep -c .)

if [ "$MODE" = "--json" ]; then
    msg=$(printf '%s' "$problems" | head -4 | tr '\n' ';' | tr -d '"' | sed 's/\\/ /g')
    printf '{"systemMessage": "Uscite dei punti: %s problemi. %s"}\n' "$count" "$msg"
    exit 0
fi

echo
echo "Uscite dei punti — $count problemi:"
echo "  (un punto \`fatto\` senza uscita verificata è una dichiarazione. Il conteggio delle righe"
echo "   \`fatto\` non distingue le due cose, ed è il modo in cui un task si chiude sembrando finito.)"
echo
printf '%s' "$problems"
exit 1
