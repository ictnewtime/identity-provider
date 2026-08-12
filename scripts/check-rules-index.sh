#!/usr/bin/env bash
# Verifica che l'indice e le regole dicano la stessa cosa: stesso numero, stessa regola.
#
# Il difetto che corregge (casi 1 e 2 del piano docs/task/done/20260806-meta-docs-consistency/,
# punto TMC05): il 2026-08-06 `R7` erano **due regole diverse** — l'indice chiamava così quella dei
# contatori, `rules.md` chiamava così *Onestà sul risultato* — e la regola sull'onestà **non era
# nell'indice**, quindi non entrava mai in contesto. L'indice è l'unico file che si legge a ogni
# prompt: una regola che non ha la sua riga lì è scritta e non applicata, e un numero che significa
# due cose rende ambiguo ogni rimando che lo cita.
#
# Tre controlli:
#   1. NON RAGGIUNGIBILE — ogni `## Rn` dei file di `.claude/rules/` ha la sua riga nella tabella
#                          dell'indice.
#   2. SENZA REGOLA      — il verso opposto: una riga d'indice che non corrisponde a nessuna sezione
#                          di `.claude/rules/` promette un dettaglio che non esiste.
#   3. DIVERGENTE        — indice e regola parlano della stessa cosa. Non si confrontano le stringhe:
#                          la riga d'indice è una **sintesi**, non una copia del titolo. Si misura il
#                          vocabolario condiviso — quante parole significative della riga ricorrono
#                          nella regola — e sotto la soglia si segnala. Con `R7` doppia la
#                          condivisione crolla, perché sono due argomenti diversi.
#
# In più, quasi gratis: lo stesso numero usato due volte — nell'indice, o in due file di regole
# diversi, che dopo lo spezzamento di TMD09 è un errore possibile e prima non lo era.
#
# COSA NON COPRE (ABA16), perché un controllo che non lo dichiara sembra coprire tutto:
#   - il **senso** della sintesi: si misura il vocabolario, non il significato. Una riga d'indice che
#     riusa le parole della regola per dire il contrario passa il controllo;
#   - il **verso opposto** del confronto 3: non si chiede che le parole della regola compaiano
#     nell'indice. È deliberato — il titolo `R3 — Lingua` non compare nella sua riga e non è un
#     difetto: la riga sintetizza il contenuto, non ripete il titolo;
#   - l'**ordine**: l'indice può elencare le regole in un ordine diverso da quello dei file, e non
#     viene segnalato. Dopo TMD09 l'ordine dei file è per argomento, non per numero;
#   - che una regola sia nel file **giusto** per argomento, o che `paths:` sia quello sensato: il
#     raggruppamento è un giudizio, non una stringa;
#   - gli **altri riferimenti**: se una fase, una skill o un file `*-custom.md` chiama `R7` una cosa
#     diversa da entrambi, non se ne accorge nessuno. Questo confronta l'indice con `.claude/rules/`;
#   - le regole **di progetto** dei `*-custom.md`: hanno una numerazione propria (C1, C2…) e un
#     indice proprio;
#   - una regola **spezzata in due** che conserva metà del vocabolario resta sopra soglia. La soglia
#     protegge dal falso positivo (AVU10) al prezzo di lasciar passare le divergenze parziali.
#
# Uso:   ./scripts/check-rules-index.sh          elenco, exit 1 se trova qualcosa
#        ./scripts/check-rules-index.sh --json   output per l'hook di Claude Code
#
# PORTING: INDEX e RULES_DIR sono gli unici percorsi da cambiare su un altro repo. Se là l'indice non è
# una tabella `| Rn | … |` e le regole non sono titoli `## Rn — …`, vanno cambiate anche le due
# espressioni che le riconoscono, marcate FORMA nel codice.

set -uo pipefail

ROOT=${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/.." && pwd)}
INDEX="$ROOT/docs/ai/index.md"

# La fonte di verità del TESTO delle regole è `.claude/rules/`, dal 2026-08-07 (TMD04): `rules.md` è
# diventato una mappa e non contiene più i titoli `## Rn — …`. Prima di questo cambio il confronto era
# fra due file; ora l'indice si confronta con l'insieme dei file di regole.
# I file che cominciano con `_` sono sonde usa-e-getta e non contengono regole.
RULES_DIR="$ROOT/.claude/rules"
RULES_FILES=$(find "$RULES_DIR" -maxdepth 1 -name '*.md' ! -name '_*' 2>/dev/null | sort)
MODE=${1:-text}

# Soglia di vocabolario condiviso, in percentuale. Calibrata sui due stati reali della meta-doc, con
# questo stesso script e MIN_SHARE=101 per farsi stampare tutte le percentuali:
#
#   stato attuale (2026-08-06, dopo TMD28)   17 regole, la sintesi più libera al 66% (R4, 4 su 6)
#   stato committato (HEAD 76c595a)          le due divergenze vere al 5% (R7) e 0% (R6)
#
# La soglia sta in mezzo con margine dai due lati. Alzarla segnala sintesi legittime, e un controllo
# che grida sul corretto si smette di leggere (AVU10); abbassarla la rende cieca.
MIN_SHARE=50

# Se i due file non ci sono non si finge un verde: in modo `--json` si tace, perché l'hook non deve
# bloccare, ma a mano si dice che non c'era nulla da confrontare.
if [ ! -f "$INDEX" ] || [ -z "$RULES_FILES" ]; then
    if [ "$MODE" = "--json" ]; then echo '{}'; else
        echo "index.md o .claude/rules/*.md non trovati: niente da verificare"
    fi
    exit 0
fi

# Una riga per problema, nella forma `ETICHETTA<TAB>messaggio`; l'ultima riga porta il conteggio
# delle regole trovate, che serve al messaggio di successo.
# `q` è l'apostrofo: dentro un programma awk fra apici singoli non si può scrivere, e i messaggi sono
# in italiano.
report=$(awk -v min_share="$MIN_SHARE" -v index_file="$INDEX" -v q="'" '
    BEGIN {
        # Parole troppo comuni per dire qualcosa: fuori dal conto da entrambe le parti. Sotto le
        # quattro lettere si scarta comunque, quindi qui stanno solo le lunghe.
        split("della dello delle degli dalla dalle dallo nella nelle nello negli alla alle allo " \
              "agli questo questa questi queste quello quella quelli quelle come cosa dove essere " \
              "fare sono stato stata stati state anche perche quando quindi tutti tutto tutte " \
              "tutta senza altro altra altri altre molto meno solo cioe loro nostro suoi viene " \
              "vengono deve devono possono quale quali stesso stessa stesse stessi", s, " ")
        for (i in s) STOP[s[i]] = 1
    }

    # Le chiavi di un testo: parole di almeno quattro lettere, ridotte ai primi cinque caratteri,
    # perché singolare e plurale — o verbo e sostantivo — sono la stessa parola per chi legge
    # ("contatore"/"contatori", "ragionare"/"ragionamento").
    function harvest(text, out,    n, i, a, t) {
        text = tolower(text)
        gsub(/[^a-z0-9]+/, " ", text)
        n = split(text, a, " ")
        for (i = 1; i <= n; i++) {
            t = a[i]
            if (length(t) < 4 || (t in STOP)) continue
            out[substr(t, 1, 5)] = 1
        }
    }

    # FORMA 1 — la riga di tabella dentro index.md: `| R7 | testo |`.
    # NB: qui dentro NON si scrivono apostrofi, nemmeno nei commenti. Il programma awk sta fra apici
    # singoli, e un apostrofo italiano lo chiude a meta: bash si mette a interpretare il codice awk e
    # segnala `sintassi vicino al token non atteso "$0,"` venti righe piu sotto, lontano dalla causa.
    # Per i messaggi in italiano si usa la variabile `q`, che porta il carattere.
    FILENAME == index_file && /^\|[ \t]*R[0-9]+[ \t]*\|/ {
        match($0, /R[0-9]+/); num = substr($0, RSTART + 1, RLENGTH - 1)
        n = split($0, cell, "|")
        text = ""
        for (i = 3; i < n; i++) text = text " " cell[i]
        if (num in idx_text) print "DUP-IDX\tR" num ": numero ripetuto nella tabella dell" q "indice"
        else { n_idx++; idx_order[n_idx] = num }
        idx_text[num] = text
        next
    }

    # FORMA 2 — il titolo di sezione di rules.md: `## R7 — Titolo`. I sottotitoli `###` fanno parte
    # del corpo della regola e non la chiudono.
    FILENAME != index_file && /^#{1,2}[ \t]*R[0-9]+[ \t]*(—|-)/ {
        title = $0
        match($0, /R[0-9]+/); num = substr($0, RSTART + 1, RLENGTH - 1)
        sub(/^#{1,2}[ \t]*R[0-9]+[ \t]*(—|-)?/, "", title)
        sub(/^[^A-Za-z0-9]+/, "", title)
        if (num in rule_title) print "DUP-RULE\tR" num " — " title ": numero ripetuto fra i file di regole"
        else { n_rule++; rule_order[n_rule] = num }
        rule_title[num] = title
        rule_body[num] = ""
        cur = num
        next
    }

    FILENAME != index_file && /^#{1,2} / { cur = ""; next }
    FILENAME != index_file && cur != "" { rule_body[cur] = rule_body[cur] " " $0 }

    END {
        for (i = 1; i <= n_idx; i++) {
            num = idx_order[i]
            if (!(num in rule_title)) {
                print "ORFANA\tR" num ": riga d" q "indice senza la regola corrispondente in .claude/rules/"
                continue
            }
            delete ik; delete rk
            harvest(idx_text[num], ik)
            harvest(rule_title[num] " " rule_body[num], rk)
            tot = 0; hit = 0
            for (k in ik) { tot++; if (k in rk) hit++ }
            share = (tot > 0) ? int(hit * 100 / tot) : 100
            if (share < min_share)
                printf "DIVERGE\tR%s: indice e regola condividono %d parole su %d (%d%%) — la regola dice \"%s\"\n", \
                       num, hit, tot, share, rule_title[num]
        }
        for (i = 1; i <= n_rule; i++) {
            num = rule_order[i]
            if (!(num in idx_text))
                print "NONRAGG\tR" num " — " rule_title[num] ": definita in rules.md e assente dall" q "indice"
        }
        print "TOTALE\t" n_rule
    }
' "$INDEX" $RULES_FILES)

n_rules=$(printf '%s\n' "$report" | awk -F'\t' '$1 == "TOTALE" { print $2 }')
problems=$(printf '%s\n' "$report" | grep -v '^TOTALE	' | sed '/^$/d')
count=$(printf '%s' "$problems" | grep -c . || true)

if [ "$count" -eq 0 ]; then
    if [ "$MODE" = "--json" ]; then echo '{}'; else
        echo "Indice e regole concordano: $n_rules regole, tutte raggiungibili dall'indice e sullo stesso argomento"
    fi
    exit 0
fi

if [ "$MODE" = "--json" ]; then
    msg=$(printf '%s\n' "$problems" | cut -f2- | head -3 | tr '\n' ';' | tr -d '"')
    printf '{"systemMessage": "Indice e regole in disaccordo: %s. %s"}\n' "$count" "$msg"
    exit 0
fi

show() { printf '%s\n' "$problems" | awk -F'\t' -v tag="$1" '$1 == tag { print "  " $2 }'; }

echo "Indice e regole in disaccordo — $count problemi:"
echo "  (l'indice è l'unico file che si legge a ogni prompt. Una regola che non ha la sua riga lì è"
echo "   scritta e non applicata; un numero che significa due cose rende ambiguo ogni rimando.)"
for tag in NONRAGG ORFANA DIVERGE DUP-IDX DUP-RULE; do
    body=$(show "$tag")
    [ -n "$body" ] || continue
    echo
    case "$tag" in
        NONRAGG)  echo "REGOLE NON RAGGIUNGIBILI dall'indice:" ;;
        ORFANA)   echo "RIGHE D'INDICE senza regola:" ;;
        DIVERGE)  echo "NUMERI CHE DIVERGONO — sotto il $MIN_SHARE% di vocabolario condiviso:" ;;
        DUP-IDX)  echo "NUMERI RIPETUTI nella tabella dell'indice:" ;;
        DUP-RULE) echo "NUMERI RIPETUTI in rules.md:" ;;
    esac
    echo "$body"
done
exit 1
