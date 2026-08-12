#!/usr/bin/env bash
# Fa rispettare la metà meccanica di R16: **una cifra dichiara il comando che la produce** (punto
# TMC02). Cerca nella meta-doc le cifre che AFFERMANO UNA MISURA e non portano provenienza.
#
# Il difetto che corregge è il caso 7 del piano
# (/docs/task/done/20260806-meta-docs-consistency/action-plan.md): «~6.290 token» dichiarato in un
# piano e mai calcolato — il valore vero era 14.181. Una cifra sembra un fatto, e senza il comando che
# la produce non è confutabile: chi rilegge decide sulla base del numero. È lo schema di AVU12.
#
# COSA COSTITUISCE UNA MISURA — solo una cifra (o un numero scritto in parole) IMMEDIATAMENTE SEGUITA
# da un sostantivo di quantità: `604 righe`, `~2.000 token`, `ventiquattro file`, `21 voci`. La forma
# opposta — sostantivo poi cifra — NON si cerca: su questo repo produce solo `punto 7`, `riga 11`,
# `difetti 0`, cioè riferimenti e non misure (12 occorrenze, 12 falsi positivi).
#
# COSA COSTITUISCE PROVENIENZA — una fra queste, nella FINESTRA della misura:
#   1. un comando in backtick (`wc -l`, `git log`, `./scripts/x.sh`) — anche il solo nome di uno
#      script è il comando che produce il numero;
#   2. un prompt `$ ...`;
#   3. un blocco di codice adiacente (la finestra tocca una riga di fence);
#   4. una citazione `file:riga` — `todo.md:104` — o un riferimento `#L104`;
#   5. un **calcolo mostrato**: `2.000 - 1.089 - 400 = 511 token` dice da dove viene il 511 meglio di
#      un comando. Si riconosce da un `=` con cifre da entrambi i lati.
# La FINESTRA è la riga stessa più due sopra e due sotto. Se la misura sta in una **riga di tabella**,
# la finestra sale fino a due righe sopra l'inizio della tabella (massimo 8 righe di risalita): la
# forma ricorrente della meta-doc è «Misurato con `X`:» seguito dalla tabella dei numeri, e senza
# questa estensione ogni tabella misurata sarebbe segnalata. Non si allarga di più: un registro di
# sessanta righe non deve ereditare la provenienza da una riga qualsiasi che cita uno script.
#
# NON è una misura, e viene esentato: una cifra dentro un `inline code` o fra "virgolette" — è
# materiale citato, non un'affermazione di chi scrive; e una cifra che una **parola-spia** marca come
# soglia («sotto le 40 righe», «tetto di 2.000 token»). Nelle tabelle la spia si cerca
# nell'INTESTAZIONE DELLA COLONNA, ed è il criterio che separa le due tabelle vere di questa meta-doc:
# una colonna `Tetto`/`Soglia` detta una regola ed esce, una colonna `Costo` misura un file e resta.
#
# COSA NON COPRE (ABA16), perché un controllo che non lo dichiara sembra coprire tutto:
#   - **non verifica che il numero sia giusto**: verifica che dichiari da dove viene. Un `wc -l`
#     accanto a una cifra sbagliata passa. Il conteggio vero è mestiere di `check-counts.sh` (TMC06);
#   - **`reports/` è fuori** (vedi sotto), ed è la rinuncia che pesa: il caso 7, quello che motiva
#     questa regola, sta in un report. La regola resta verificata dove la si può ancora correggere;
#   - **le frazioni `N su N`** — «27 su 27», «2 su 8» — non sono cercate: non hanno il sostantivo
#     accanto. È esattamente la forma di AVU12, e resta scoperta. Il caso del 2026-08-07 lo dimostra:
#     «43 su 45 difetti aperti», sbagliato di quattordici, **non** sarebbe stato visto da qui nemmeno
#     con docs/task/ nello scopo. È stato trovato a mano, spostando la riga;
#   - **le percentuali** («il 61%»), le **date**, i numeri di **regola** (`R7`), le **sigle** (`AVU10`)
#     e i **numeri di riga**: nessuno di questi afferma una misura, e cercarli produrrebbe il rumore
#     che spegne un controllo (AVU10);
#   - **una soglia scritta in un modo non previsto** viene segnalata come misura. Al 2026-08-06 succede
#     in un caso su ventisei: «il **nodo** instrada e basta, 40 righe» è un tetto senza parola-spia;
#   - **le ipotesi in prosa** restano rumore: «una ricerca che apre trenta file», «una cella di
#     quindici righe» hanno la forma di una misura e non misurano niente. Tre casi su ventisei;
#   - i numeri in parole **sotto il dieci** non si cercano: in italiano «due righe», «tre file» sono
#     indefiniti («un paio di righe»), e su questo repo lo erano in tutte le occorrenze. Il prezzo è
#     che «altri cinque file» — una misura vera — non viene visto;
#   - **la provenienza è di posizione, non di senso**: un comando due righe sopra vale, anche se
#     produce un'altra cifra. Verifica che qualcuno l'abbia dichiarata, non che sia quella giusta;
#   - i sostantivi sono un **elenco chiuso** (sotto): una misura in «tre cartelle» non è vista.
#
# I **blocchi di codice** vengono saltati: una cifra dentro un fence è un esempio, non un'affermazione.
#
# Uso:   ./scripts/check-number-provenance.sh          elenco, exit 1 se trova qualcosa
#        ./scripts/check-number-provenance.sh --json   output per l'hook di Claude Code
#
# NUMPROV_WIDE=1 spegne le esenzioni (soglie, materiale citato, finestra di tabella): serve a
# **rimisurare il rumore** quando si tocca l'elenco dei sostantivi, perché il rapporto fra i due numeri
# è la prova che il controllo è tarato e non solo silenzioso. Al 2026-08-06, su `docs/ai/` soltanto:
#   $ ./scripts/check-number-provenance.sh              | tail -n +5 | wc -l   → 26   (stretto)
#   $ NUMPROV_WIDE=1 ./scripts/check-number-provenance.sh | tail -n +5 | wc -l → 51   (largo)
# Sopra le ~25 segnalazioni il controllo va restretto, non spento: è AVU10.
#
# DOPO L'ESTENSIONE A docs/task/, il 2026-08-07: **65 stretto, 130 largo**. Sopra la soglia, e va
# guardato in faccia invece di essere tarato via — perché il campione è stato letto e sono **veri**:
# cifre in piani e analisi che non dicono da dove vengono. La ripartizione:
#   18  docs/ai/                      debito preesistente, la metà mancante di AVU12
#   33  20260806-meta-docs-refactor   un task con tutti i punti chiusi: quando entra in done/ esce
#   14  tutto il resto
# La previsione era «alla chiusura del refactor torna a 32». Chiuso il 2026-08-07: **31** — la
# previsione era sbagliata di uno, e si vede solo perché era scritta. Resta sopra i 25 finché non si
# paga il debito di docs/ai/. Restringerlo vorrebbe dire nascondere 31 cifre che nessuno può confutare.
#
# PORTING: dipende dalla LINGUA, non dal progetto. Gli elenchi da riscrivere su un repo in un'altra
# lingua sono NOUNS (i sostantivi di quantità), NUMWORDS (i numeri scritti in parole) e SPY (le
# parole-spia di una soglia). Le cartelle bersaglio sono in ROOTS (vedi /docs/ai/dev-guide/porting.md).

set -uo pipefail

ROOT=${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/.." && pwd)}
# Due alberi, non uno — esteso il 2026-08-07. `docs/ai/` è la procedura, `docs/task/` è il lavoro: le
# cifre inventate del 2026-08-07 stavano in un piano, cioè nel secondo, e nessuno le guardava.
ROOTS="$ROOT/docs/ai $ROOT/docs/task"
MODE=${1:-text}
WIDE=${NUMPROV_WIDE:-0}

# Sostantivi di quantità: un numero davanti a uno di questi afferma una misura.
NOUNS=${NUMPROV_NOUNS:-'file|righe|riga|token|byte|voci|voce|difetti|difetto|punti|punto|occorrenze|occorrenza'}

# Numeri scritti in parole, da DIECI in su. `un file` è un articolo, non un conteggio; e sotto il
# dieci l'italiano usa il numero come indefinito — «due righe» vale «un paio di righe», e su tutto
# docs/ai/ non c'era una sola occorrenza in cui `due|tre|quattro|cinque` misurasse qualcosa. Le radici
# troncate (`vent`, `trent`) coprono i composti — ventiquattro, trentadue, ventotto.
NUMWORDS='dieci|undici|dodici|tredici|quattordici|quindici|sedici|diciassette|diciotto|diciannove|vent[aeio][a-z]*|trent[aeio][a-z]*|quarant[aeio][a-z]*|cinquant[aeio][a-z]*|sessant[aeio][a-z]*|settant[aeio][a-z]*|ottant[aeio][a-z]*|novant[aeio][a-z]*|cent[oi]|mille|mila'

targets=""
for d in $ROOTS; do [ -d "$d" ] && targets="$targets $d"; done
[ -n "$targets" ] || { [ "$MODE" = "--json" ] && echo '{}'; exit 0; }

# DUE ESCLUSIONI, LO STESSO CRITERIO: è storia, e correggerla vorrebbe dire riscrivere il passato.
# `reports/` è un archivio in sola aggiunta — il verbale di ieri non si emenda (R15). `docs/task/done/`
# sono i task chiusi: la cifra sta lì perché quel giorno si è deciso su quella, ed è check-links.sh a
# usare già la stessa esclusione. Ciò che resta è tutto ciò su cui qualcuno **deciderà ancora**.
hits=$(find $targets -name '*.md' -not -path '*/reports/*' -not -path '*/done/*' -print0 2>/dev/null | sort -z | xargs -0 awk \
    -v nouns="$NOUNS" -v numwords="$NUMWORDS" -v wide="$WIDE" '
    function is_table(s) { return s ~ /^[ \t]*\|/ }

    # Parole-spia di una soglia: il numero è una REGOLA, non la misura di qualcosa.
    BEGIN { SPY = "(tett[oi]|soglia|soglie|budget|limite|sotto|oltre|entro|massim|minim|almeno|fino a|più di|meno di|supera|nasce a|muore|si spezza|resta|restano|resto di|sta in|per ogni|ciascun|o più|o meno)" }

    # Provenienza in una riga: comando, prompt, fence o citazione file:riga.
    function has_prov(s) {
        if (s ~ /^[ \t]*```/) return 1
        if (s ~ /(^|[ (])\$ [^ ]/) return 1
        if (s ~ /`[^`]*(wc|grep|rg|find|awk|sed|git|ls|jq|xargs|cat|tr|sort|uniq|head|tail|printf|node|php|python|docker|npm|make|bash|sh) /) return 1
        if (s ~ /`[^`]*(\.sh|\.js|\.php|\.py)`/) return 1          # il nome di uno script È il comando
        if (s ~ /(\.md|\.sh|\.js|\.php|\.json|\.ya?ml|\.txt):[0-9]+/) return 1
        if (s ~ /#L[0-9]+/) return 1
        # Un calcolo mostrato è la derivazione del numero, quindi è la sua provenienza: «2.000 − 1.089
        # (radice) − 400 (nodo) = 511 token» dice da dove viene il 511 meglio di un comando. Si cerca
        # `=` con cifre da entrambi i lati, non i segni di operazione: il meno di quella riga è un
        # U+2212, e un carattere multibyte dentro una classe di caratteri awk non si comporta.
        if (s ~ /[0-9][^=]*=[^=]*[0-9]/) return 1
        return 0
    }

    # Il numero sta dentro un `inline code`? Allora è materiale CITATO, e non lo afferma il documento:
    # in «una misura dichiarata `~6.290 token` e mai calcolata» quella cifra è un esempio del difetto,
    # non il difetto. Stessa ragione per cui si saltano i blocchi di codice.
    # Stessa cosa per le VIRGOLETTE: «il caso "1500 righe, ne aggiungo una"» non misura un file, cita
    # un caso ipotetico. Un numero dentro una citazione non lo afferma chi scrive.
    function in_code(s, pos,   k, n, q, c) {
        n = 0; q = 0
        for (k = 1; k < pos; k++) {
            c = substr(s, k, 1)
            if (c == "`") n++
            else if (c == "\"") q++
        }
        return (n % 2 || q % 2)
    }

    # Il numero è una SOGLIA, cioè una regola, e non la misura di qualcosa? Si guarda solo la frase che
    # lo circonda — fino al confine di frase o di cella più vicino — perché una riga che parla di tetti
    # e poi misura un file va segnalata.
    function is_threshold(i, pos, len,   s, pre, post, k) {
        s = L[i]
        pre = tolower(substr(s, 1, pos - 1))
        for (k = length(pre); k > 0; k--)
            if (substr(pre, k, 1) ~ /[.;!?|]/) break
        pre = substr(pre, k + 1)
        if (pre ~ SPY) return 1
        # Anche DOPO il numero: «300 righe o più. Sotto la soglia…» mette la spia a destra.
        post = tolower(substr(s, pos + len, 30))
        sub(/[.;!?|].*$/, "", post)
        if (post ~ SPY) return 1
        # In una tabella la spia sta nella riga di INTESTAZIONE della colonna, non nella cella: `| **nodo** |
        # instrada e basta | 40 righe |` sotto una colonna **Tetto** dichiara un tetto. È il criterio
        # che distingue le due tabelle vere di questa meta-doc: `Tetto`/`Soglia` sono regole ed escono,
        # `Costo` è una misura di un file e resta — che è precisamente il caso che TMC02 vuole vedere.
        return (is_table(s) && header_cell(i, pos) ~ SPY)
    }

    # La cella di intestazione della colonna in cui cade `pos`.
    function header_cell(i, pos,   up, nc, k, a) {
        up = i
        while (up - 1 >= 1 && is_table(L[up - 1])) up--
        if (up == i) return ""                                  # la riga È essa stessa intestazione
        if (L[up + 1] !~ /^[ \t]*\|[ \t]*:?-/) return ""        # senza separatore non è una tabella
        # La cella `n` sta fra il n-esimo e il (n+1)-esimo `|`, e `split` su una riga che comincia per
        # `|` mette la cella 1 nel campo 2: il campo cercato è (numero di `|` prima) + 1.
        nc = 0
        for (k = 1; k < pos; k++) if (substr(L[i], k, 1) == "|") nc++
        split(tolower(L[up]), a, "|")
        return a[nc + 1]
    }

    FNR == 1 {
        if (nf > 0) flush()
        nf = 0
        split("", L); split("", fence)   # il file precedente può essere più lungo di questo
        file = FILENAME
    }
    { nf++; L[nf] = $0; fence[nf] = ($0 ~ /^[ \t]*```/) }
    END { flush() }

    function flush(   i, infence, skip, start, end, j, line, m, p, pos, rest, prov, up, hops, key) {
        infence = 0
        for (i = 1; i <= nf; i++) {
            if (fence[i]) { infence = !infence; skip[i] = 1; continue }
            skip[i] = infence
        }

        for (i = 1; i <= nf; i++) {
            if (skip[i]) continue
            line = L[i]
            p = 0
            while (1) {
                rest = substr(line, p + 1)
                if (!match(rest, "(^|[^[:alnum:].,])([0-9]+([.,][0-9]+)*|" numwords ")[ \t]+(" nouns ")([^[:alpha:]]|$)")) break
                m = substr(rest, RSTART, RLENGTH)
                p = p + RSTART + RLENGTH - 1

                # `pos` deve puntare alla CIFRA, non al carattere di confine che il regex si porta
                # dietro: se punta al confine, `"1500 righe` conta zero virgolette prima di sé e la
                # citazione non viene vista. Lo scarto si misura dopo il taglio davanti e prima di
                # quello dietro, perché solo il primo sposta la posizione.
                sub(/^[^[:alnum:]]+/, "", m)
                pos = p - RLENGTH + 1 + (RLENGTH - length(m))
                sub(/[^[:alnum:]]+$/, "", m)

                # Un numero in parole è una misura solo se la parola sta da sola: `settimane` no.
                if (m !~ /^[0-9]/ && m !~ ("^(" numwords ")[ \t]")) continue
                if (wide != "1" && in_code(line, pos)) continue
                if (wide != "1" && is_threshold(i, pos, length(m))) continue

                # Finestra: riga ±2, estesa in su lungo la tabella (max 8 risalite).
                start = i - 2; end = i + 2
                if (wide != "1" && is_table(line)) {
                    up = i; hops = 0
                    while (up - 1 >= 1 && is_table(L[up - 1]) && hops < 8) { up--; hops++ }
                    if (up - 2 < start) start = up - 2
                }
                if (start < 1) start = 1
                if (end > nf) end = nf

                prov = 0
                for (j = start; j <= end; j++) if (has_prov(L[j])) { prov = 1; break }
                # Deduplica: «22 righe elencate non sono 22 righe verificate» è una misura sola detta
                # due volte, e contarla due volte gonfia il rumore senza aggiungere informazione.
                key = file ":" i ":" m
                if (!prov && !(key in seen)) { seen[key] = 1; printf "%s:%d: %s\n", file, i, m }
            }
        }
    }
' 2>/dev/null | sed "s|^$ROOT/||")

if [ -z "$hits" ]; then
    if [ "$MODE" = "--json" ]; then echo '{}'; else
        echo "R16 ok: ogni misura dichiarata in docs/ai/ e docs/task/ ha la sua provenienza"
    fi
    exit 0
fi

count=$(printf '%s\n' "$hits" | wc -l | tr -d ' ')

if [ "$MODE" = "--json" ]; then
    # Una riga sola, senza virgolette non escapate: l'hook deve restare JSON valido.
    msg=$(printf '%s\n' "$hits" | head -5 | cut -c1-160 | tr '\n' ';' | tr -d '"')
    printf '{"systemMessage": "R16: %s misure senza provenienza nella meta-doc e nei task. Una cifra dichiara il comando che la produce, o data e fonte. %s"}\n' "$count" "$msg"
    exit 0
fi

echo "R16 violata: $count misure dichiarate senza provenienza in docs/ai/ e docs/task/."
echo "  (una cifra sembra un fatto: senza il comando che la produce non è confutabile — AVU12.)"
echo "  Si aggiunge il comando in backtick, o \`file:riga\`, entro due righe. Non si cancella il numero."
echo
printf '%s\n' "$hits"
exit 1
