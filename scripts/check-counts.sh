#!/usr/bin/env bash
# Verifica che i conteggi SCRITTI nei documenti corrispondano al conteggio VERO.
#
# Il difetto che corregge (caso 5 del piano
# docs/task/done/20260806-meta-docs-consistency/action-plan.md, punto TMC06): il README diceva
# «ventiquattro file» quando erano 28, e mandava a `rules.md` per «R0-R14» quando le regole
# arrivavano a R16. Nessun controllo confrontava una cifra scritta con la cifra vera, e una cifra
# sembra un fatto: chi la rilegge decide sulla base del numero senza rifare il conto.
#
# Quattro forme, scelte perché il confronto è **meccanico e non opinabile**:
#
#   1. RIGHE DI UN FILE CITATO — una riga che contiene **un solo** link a un `.md` e **una sola**
#      cifra «N righe» promette la lunghezza di quel file. È la forma dei costi dichiarati negli
#      indici di `abstract/`, `full/` e `dev-guide/`, su cui poggia tutta l'aritmetica del budget:
#      un costo dichiarato più basso del vero non è un'imprecisione, è un tetto che non vincola.
#      Tolleranza del 10% (minimo 3 righe), perché la cifra è scritta `~N`.
#      Una cifra vale come **misura** solo se è scritta `~N`, se un articolo la definisce («le 199
#      righe di …») o se sta **dopo** il link: altrimenti è una **soglia** («4 foglie oppure 300
#      righe») o un **tetto** («nel tetto delle 50 righe»), e una soglia non misura nessun file.
#   2. INTERVALLO DELLE REGOLE — `R0-Rb`: un intervallo che **parte da R0** promette *tutte* le
#      regole, quindi `b` deve essere la regola più alta definita in `docs/ai/rules.md`.
#   3. CONTEGGIO IN UN'INTESTAZIONE — `## <titolo> — N difetti` (o `N voci`): il confronto è con le
#      righe di dato delle tabelle di **quella** sezione, che sono esattamente ciò che il titolo
#      conta.
#   4. FILE DI UNA CARTELLA — «N file» / «N documenti» con una cartella nominata nella stessa riga.
#      Si accetta se il numero coincide con i `.md` della cartella **o** con quelli del solo primo
#      livello: sono due letture legittime di «file», e scegliere per il documento produrrebbe un
#      falso positivo.
#
# COSA NON COPRE (ABA16), perché un controllo che non lo dichiara sembra coprire tutto:
#   - i conteggi **in parole**: «ventiquattro file», «le otto procedure», «le quattro regole». Sono
#     la forma che ha prodotto il caso originale, e restano fuori: in italiano «due righe», «tre
#     proposte», «cinque controlli» sono quantificatori di prosa e non conteggi, e riconoscerli
#     produrrebbe centinaia di segnalazioni su testo corretto — 411 righe di `docs/` contengono la
#     forma. Il rischio è AVU10: un controllo che grida sul corretto si smette di leggere;
#   - i conteggi di un **registro citato per link** — «[vulnerability.md] contiene 8 difetti».
#     Provato e scartato: un registro tiene anche le voci chiuse e si spezza in sezioni, quindi
#     «N difetti» non ha un solo referente vero. Sulla forma 3 il referente è la sezione sotto il
#     titolo, e lì è esatto;
#   - i conteggi di **token**, di occorrenze (`grep`) e di righe `fatto` di un piano: dipendono da un
#     comando che il documento non dichiara. È il difetto AVU12, e la sua metà scritta è `R16`;
#   - le cifre in `docs/ai/reports/` e in `docs/task/done/`: sono **istantanee datate**. Un report
#     dice quanto era grande un file quel giorno, e riscriverlo sarebbe riscrivere la storia — la
#     stessa esclusione di `check-links.sh`;
#   - le **soglie** e i **tetti** scritti in righe, e le cifre che contano il contenuto di un file
#     invece della sua lunghezza («12 righe che portano a un solo file ciascuna» è la mappa dentro
#     `index.md`, non `index.md`). Restano fuori di proposito: sono la forma che produce falsi
#     positivi, e nessun controllo distingue una prescrizione da una misura leggendo un numero;
#   - gli intervalli di regole fuori da `docs/ai/`, e quelli che non partono da `R0`: `R10-R12` non
#     promette tutte le regole, e un `R0-R15` in un task o in un report è il lavoro di quel giorno;
#   - se il numero è **giusto per la ragione giusta**: la forma 4 accetta due letture di «file», e
#     un documento che ne intendeva una terza passa comunque;
#   - i conteggi dentro i **blocchi di codice**: sono esempi, non affermazioni.
#
# Uso:   ./scripts/check-counts.sh          elenco, exit 1 se trova qualcosa
#        ./scripts/check-counts.sh --json   output per l'hook di Claude Code
#
# PORTING: DOCS, RULES e le esclusioni di `skip_history` sono gli unici percorsi da cambiare su un
# altro repo. Le quattro forme non nominano nulla di questo progetto.

set -uo pipefail

ROOT=${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/.." && pwd)}
DOCS="$ROOT/docs"
RULES="$DOCS/ai/rules.md"
MODE=${1:-text}

[ -d "$DOCS" ] || { [ "$MODE" = "--json" ] && echo '{}'; exit 0; }

problems=""
n_checked=0

# Le istantanee datate non si riscrivono: un report dice quanto era grande un file quel giorno.
skip_history() {
    case "${1#$ROOT/}" in
        docs/ai/reports/*|docs/task/done/*) return 0 ;;
        *) return 1 ;;
    esac
}

files=$(find "$DOCS" -name '*.md' 2>/dev/null | sort)

# ---------------------------------------------------------------------------
# Forma 1 — «N righe» di un file citato nella stessa riga.
# ---------------------------------------------------------------------------
declared_lines=$(printf '%s\n' "$files" | while read -r f; do
    [ -n "$f" ] || continue
    skip_history "$f" && continue
    awk '
        FNR == 1 { infence = 0 }
        /^[ \t]*```/ { infence = !infence; next }
        infence { next }
        # Un tetto non è una misura: «nel tetto delle 50 righe» prescrive, non descrive.
        /tetto/ { next }
        # «N righe CHE …» conta il contenuto, non il file: «12 righe che portano a un solo file
        # ciascuna» è la mappa dentro `index.md`, non la lunghezza di `index.md`. Primo falso
        # positivo trovato eseguendo il controllo sul repo vero.
        /[0-9]+ righe che/ { next }
        {
            line = $0

            # Un solo link a un .md: se ce ne sono due, la cifra non dice a quale si riferisce.
            nlink = 0; target = ""; lpos = 0
            rest = line; base = 0
            while (match(rest, /\[[^]]*\]\([^)]*\.md\)/)) {
                m = substr(rest, RSTART, RLENGTH)
                p = index(m, "](")
                nlink++
                target = substr(m, p + 2, length(m) - p - 2)
                lpos = base + RSTART
                base = base + RSTART + RLENGTH - 1
                rest = substr(rest, RSTART + RLENGTH)
            }
            if (nlink != 1) next

            # Una sola cifra «N righe»: due cifre sulla stessa riga sono un confronto, non una misura.
            nfig = 0; fig = ""; fpos = 0; tilde = 0; article = 0
            rest = line; base = 0
            while (match(rest, /~?[0-9]+(\.[0-9][0-9][0-9])* righe/)) {
                m = substr(rest, RSTART, RLENGTH)
                nfig++
                fpos = base + RSTART
                tilde = (substr(m, 1, 1) == "~")
                article = (substr(line, fpos - 3, 3) ~ /[Ll]e $/)
                gsub(/[^0-9]/, "", m)
                fig = m
                base = base + RSTART + RLENGTH - 1
                rest = substr(rest, RSTART + RLENGTH)
            }
            if (nfig != 1) next

            # La cifra MISURA il file citato se è scritta `~N`, se un articolo la definisce («le 199
            # righe di …») o se sta **dopo** il link. Altrimenti è una SOGLIA — «4 foglie oppure 300
            # righe», con un link altrove nella riga — e una soglia non misura nessun file. Non era
            # ancora un falso positivo: passava perché il file citato ha per caso 297 righe.
            if (!tilde && !article && fpos < lpos) next

            print FILENAME "\t" FNR "\t" target "\t" fig
        }
    ' "$f"
done)

while IFS=$'\t' read -r f line target declared; do
    [ -n "${f:-}" ] || continue
    case "$target" in
        /*) abs="$ROOT$target" ;;
        *)  abs="$(dirname "$f")/$target" ;;
    esac
    [ -f "$abs" ] || continue        # un link rotto è di check-links.sh, non di questo controllo
    real=$(wc -l < "$abs" 2>/dev/null | tr -d ' ')
    [ -n "$real" ] || continue
    n_checked=$((n_checked + 1))
    tol=$((declared / 10))
    [ "$tol" -lt 3 ] && tol=3
    diff=$((real - declared)); [ "$diff" -lt 0 ] && diff=$((-diff))
    if [ "$diff" -gt "$tol" ]; then
        problems="${problems}${f#$ROOT/}:${line}: ${target} — dichiarate ${declared} righe, sono ${real}"$'\n'
    fi
done <<< "$declared_lines"

# ---------------------------------------------------------------------------
# Forma 2 — l'intervallo delle regole. Solo `R0-Rb`: parte da R0, quindi promette tutte le regole.
# Solo in `docs/ai/`, che descrive lo stato di adesso: in un task o in un report un intervallo è il
# lavoro di quel giorno, e resta vero anche quando le regole crescono.
# ---------------------------------------------------------------------------
if [ -f "$RULES" ]; then
    max_rule=$(grep -oE '^#{2,3} R[0-9]+' "$RULES" | grep -oE '[0-9]+' | sort -n | tail -1)
    if [ -n "${max_rule:-}" ]; then
        for f in $(printf '%s\n' "$files" | grep '/docs/ai/' | grep -v '/docs/ai/reports/'); do
            while IFS=: read -r line hit; do
                [ -n "${line:-}" ] || continue
                b=$(printf '%s' "$hit" | grep -oE 'R0 *[-–—] *R?[0-9]+' | head -1 | grep -oE '[0-9]+$')
                [ -n "${b:-}" ] || continue
                n_checked=$((n_checked + 1))
                if [ "$b" -ne "$max_rule" ]; then
                    problems="${problems}${f#$ROOT/}:${line}: intervallo R0-R${b} — la regola più alta definita è R${max_rule}"$'\n'
                fi
            done < <(awk '
                FNR == 1 { infence = 0 }
                /^[ \t]*```/ { infence = !infence; next }
                infence { next }
                /R0 *[-–—] *R?[0-9]+/ { print FNR ":" $0 }
            ' "$f" 2>/dev/null)
        done
    fi
fi

# ---------------------------------------------------------------------------
# Forma 3 — «## <titolo> — N difetti»: si contano le righe di dato delle tabelle della sezione.
# ---------------------------------------------------------------------------
heading_counts=$(printf '%s\n' "$files" | while read -r f; do
    [ -n "$f" ] || continue
    skip_history "$f" && continue
    awk '
        function flush() {
            if (pnoun != "") { print FILENAME "\t" pline "\t" ptitle "\t" pnoun "\t" pdecl "\t" rows + 0 }
            pnoun = ""; rows = 0
        }
        FNR == 1 { flush(); infence = 0 }
        /^[ \t]*```/ { infence = !infence; next }
        infence { next }
        /^#+[ \t]/ {
            match($0, /^#+/); lvl = RLENGTH
            if (pnoun != "" && lvl <= plvl) flush()
            head = $0
            # Il titolo deve CHIUDERSI col conteggio: «— 16 difetti». Una cifra in mezzo al titolo
            # non conta la sezione: parla di qualcosa altro.
            if (match(head, /[—–-] *\**[0-9]+\** +(difetti|voci) *$/)) {
                seg = substr(head, RSTART, RLENGTH)
                noun = (seg ~ /difetti/) ? "difetti" : "voci"
                gsub(/[^0-9]/, "", seg)
                sub(/^#+[ \t]+/, "", head)
                pnoun = noun; pdecl = seg; pline = FNR; plvl = lvl; ptitle = head; rows = 0
            }
            next
        }
        # Riga di dato = riga di tabella dopo il separatore. Il separatore riparte a ogni tabella,
        # così una sezione con due tabelle somma le righe di entrambe e non conta le intestazioni.
        /^\|[ :|-]*-[ :|-]*\|/ { intable = 1; next }
        /^\|/ { if (intable && pnoun != "") rows++; next }
        { intable = 0 }
        END { flush() }
    ' "$f"
done)

while IFS=$'\t' read -r f line title noun declared real; do
    [ -n "${f:-}" ] || continue
    n_checked=$((n_checked + 1))
    if [ "$declared" -ne "$real" ]; then
        problems="${problems}${f#$ROOT/}:${line}: «${title}» — dichiarati ${declared} ${noun}, le righe sono ${real}"$'\n'
    fi
done <<< "$heading_counts"

# ---------------------------------------------------------------------------
# Forma 4 — «N file» / «N documenti» con una cartella nominata nella stessa riga.
# ---------------------------------------------------------------------------
declared_files=$(for f in $(printf '%s\n' "$files" | grep '/docs/ai/' | grep -v '/docs/ai/reports/'); do
    awk '
        FNR == 1 { infence = 0 }
        /^[ \t]*```/ { infence = !infence; next }
        infence { next }
        {
            line = $0
            # Una sola cartella nominata, in backtick e con lo slash finale: `docs/ai/`.
            ndir = 0; dir = ""
            rest = line
            while (match(rest, /`[A-Za-z0-9._\/-]+\/`/)) {
                m = substr(rest, RSTART + 1, RLENGTH - 2)
                ndir++; dir = m
                rest = substr(rest, RSTART + RLENGTH)
            }
            if (ndir != 1) next

            nfig = 0; fig = ""
            rest = line
            while (match(rest, /[0-9]+ (file|documenti)/)) {
                m = substr(rest, RSTART, RLENGTH)
                nfig++
                gsub(/[^0-9]/, "", m)
                fig = m
                rest = substr(rest, RSTART + RLENGTH)
            }
            if (nfig != 1) next

            print FILENAME "\t" FNR "\t" dir "\t" fig
        }
    ' "$f"
done)

while IFS=$'\t' read -r f line dir declared; do
    [ -n "${f:-}" ] || continue
    abs="$ROOT/${dir#/}"
    [ -d "$abs" ] || continue
    deep=$(find "$abs" -name '*.md' 2>/dev/null | wc -l | tr -d ' ')
    flat=$(find "$abs" -maxdepth 1 -name '*.md' 2>/dev/null | wc -l | tr -d ' ')
    n_checked=$((n_checked + 1))
    if [ "$declared" -ne "$deep" ] && [ "$declared" -ne "$flat" ]; then
        problems="${problems}${f#$ROOT/}:${line}: ${dir} — dichiarati ${declared} file, sono ${deep} (${flat} al primo livello)"$'\n'
    fi
done <<< "$declared_files"

problems=$(printf '%s' "$problems" | sed '/^$/d')

if [ -z "$problems" ]; then
    if [ "$MODE" = "--json" ]; then echo '{}'; else
        echo "Conteggi ok: $n_checked cifre dichiarate in docs/ coincidono col conteggio vero"
    fi
    exit 0
fi

count=$(echo "$problems" | wc -l | tr -d ' ')

if [ "$MODE" = "--json" ]; then
    msg=$(echo "$problems" | head -4 | tr '\n' ';' | tr -d '"')
    printf '{"systemMessage": "Conteggi sbagliati: %s su %s cifre verificate. %s"}\n' "$count" "$n_checked" "$msg"
    exit 0
fi

echo "$count conteggi NON corrispondono al vero (su $n_checked cifre verificate):"
echo "  (una cifra sembra un fatto: chi la rilegge decide sul numero senza rifare il conto. Si"
echo "   corregge il numero, non il conteggio — e si dichiara il comando che lo produce, R16.)"
echo
echo "$problems"
exit 1
