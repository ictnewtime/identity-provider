#!/usr/bin/env bash
# Misura il costo di lettura della documentazione: quanti token paga l'agente per arrivare a un
# contenuto. È la metrica del task 20260806-meta-docs-refactor (punto TMD40, poi TMD44).
#
# Oggi il grafo non esiste ancora: ogni foglia si raggiunge in UN salto dalla radice, quindi
#   costo(foglia) = radice + foglia
# Quando i nodi intermedi ci saranno (TMD43), qui si aggiungerà il costo del cammino vero.
#
# DIVERGENZA FRA LE DUE META-DOC (controlli 4 e 5, punti TMD63 / TMC08). La meta-doc ha due alberi
# paralleli sullo stesso argomento: le SINTESI in docs/ai/abstract/ (per agire) e le COMPLETE in
# docs/ai/full/ (per capire). Senza un rilevatore, due alberi che descrivono la stessa cosa divergono
# in silenzio: è AVU07 — la documentazione che afferma il falso senza che nulla lo dica —
# MOLTIPLICATO, perché la copia non aggiornata resta leggibile e credibile, e chi apre la sintesi non
# ha modo di sapere che la completa è andata altrove.
#
# Si confronta `full-checked:` della sintesi con la data dell'ULTIMO COMMIT della completa
# (`git log -1 --format=%cs`), non con la sua mtime: un `checkout` riazzera le mtime, e il rilevatore
# diventerebbe rumore il primo giorno. Il ripiego sulla mtime c'è solo per il file non ancora
# committato — che altrimenti non avrebbe data — e in quel caso il messaggio lo dichiara.
#
# COSA NON COPRE (ABA16), perché un controllo che non lo dichiara sembra coprire tutto:
#   - confronta DATE, non contenuti: una sintesi e una completa toccate lo stesso giorno passano
#     anche se dicono cose opposte. `full-checked:` resta una dichiarazione di chi ha riletto;
#   - la granularità è il GIORNO (`%cs`): completa modificata dopo la sintesi nella stessa giornata
#     non si distingue da una rilettura fatta dopo;
#   - non verifica il verso opposto (sintesi più recente della completa): è legittimo, ma copre anche
#     il caso in cui la sintesi si riscrive senza aprire la completa — cioè ciò che TMD60 vieta;
#   - del contratto di corrispondenza di TMD61 verifica le sole DUE metà meccaniche — completa
#     dichiarata inesistente, completa senza sintesi. NON verifica che il percorso relativo
#     corrisponda (due sintesi possono dichiarare la stessa completa: oggi accade), né che la sintesi
#     eviti di nominare i sotto-nodi della completa;
#   - `solo-completo` si riconosce solo nel FRONTMATTER: nel corpo la stessa parola è la regola
#     citata, e due complete la nominano spiegandola. Una dichiarazione scritta fuori dal frontmatter
#     non esenta — e non deve, altrimenti basterebbe parlare dell'eccezione per ottenerla;
#   - del PRIMO LIVELLO pulito (TSV08) guarda solo i `.md`: una cartella nuova o un file di altro tipo
#     non vengono segnalati. E non verifica che le quattro cartelle attese ESISTANO — se `abstract/`
#     sparisse, il primo livello resterebbe «pulito»;
#   - SEGNALA e non fa fallire: l'uscita di questo script resta governata dalla soglia di costo, come
#     per gli altri problemi di grafo.
#
# Uso:   ./scripts/check-docs-graph.sh          report leggibile
#        ./scripts/check-docs-graph.sh --json   una riga JSON per l'hook
#        ./scripts/check-docs-graph.sh --top N  le N foglie più care (default 10)
#
# PORTING: ROOT, DOCS, ABSTRACT e FULL sono gli unici percorsi da cambiare su un altro repo.

set -uo pipefail

REPO=${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/.." && pwd)}
DOCS="$REPO/docs"
ROOT="$DOCS/ai/index.md"
ABSTRACT="$DOCS/ai/abstract"
FULL="$DOCS/ai/full"

# Soglie del piano: obiettivo e fallimento accettabile (TMD45).
TARGET=1400
CEILING=2000

# Stima: ~4 byte per token su markdown italiano. Verificata su index.md (4137 byte ≈ 1000 token).
BYTES_PER_TOKEN=4

MODE=text
TOP=10
while [ $# -gt 0 ]; do
    case "$1" in
        --json) MODE=json ;;
        --top)  shift; TOP=${1:-10} ;;
    esac
    shift
done

[ -f "$ROOT" ] || { [ "$MODE" = json ] && echo '{}'; exit 0; }

root_bytes=$(wc -c < "$ROOT" | tr -d ' ')
root_tok=$(( root_bytes / BYTES_PER_TOKEN ))

# Ogni .md di docs/ tranne la radice. Il costo è radice + foglia, in token stimati.
# Le ZONE CHIUSE non stanno sul cammino dell'agente: non le attraversa, quindi non le paga.
# Contarle nel cammino peggiore misurerebbe un costo che nessuno sostiene — e nasconderebbe quello
# vero. `docs/task/done/` è storia di decisioni; `full/` e `dev-guide/` si aprono su richiesta.
rows=$(find "$DOCS" -name '*.md' -type f ! -path "$ROOT" \
        ! -path "$DOCS/task/done/*" ! -path "$DOCS/ai/full/*" ! -path "$DOCS/ai/dev-guide/*" \
        2>/dev/null | while read -r f; do
    b=$(wc -c < "$f" | tr -d ' ')
    printf '%s\t%s\t%s\n' "$(( (root_bytes + b) / BYTES_PER_TOKEN ))" "$b" "${f#$REPO/}"
done | sort -rn)

[ -n "$rows" ] || { [ "$MODE" = json ] && echo '{}'; exit 0; }

worst=$(echo "$rows" | head -1 | cut -f1)
worst_file=$(echo "$rows" | head -1 | cut -f3)
count=$(echo "$rows" | wc -l | tr -d ' ')
total=$(echo "$rows" | cut -f1 | paste -sd+ | bc)
avg=$(( total / count ))
over=$(echo "$rows" | awk -F'\t' -v c="$CEILING" '$1 > c' | wc -l | tr -d ' ')


# ---------------------------------------------------------------------------
# TMD44 — le proprietà del grafo, oltre al costo.
# Zone chiusa e README esclusi dagli orfani: la prima per costruzione (l'agente non le attraversa),
# i secondi perché servono alla forge e non sono nodi.
# ---------------------------------------------------------------------------
graph_problems=""

# 1. Orfani: ogni foglia di docs/ai deve essere citata da qualcuno, o essere la radice.
while IFS= read -r f; do
    [ -n "$f" ] || continue
    rel=${f#$REPO/}
    base=$(basename "$f")
    case "$rel" in docs/ai/index.md|*/README.md|docs/ai/reports/*) continue ;; esac
    if ! grep -rqF "$base" "$DOCS" --include='*.md' --exclude="$base" 2>/dev/null; then
        graph_problems="${graph_problems}orfano: $rel"$'\n'
    fi
done <<< "$(find "$DOCS/ai" -name '*.md' 2>/dev/null)"

# 2. Tetto dei nodi: un index.md oltre il tetto ha smesso di instradare e ha cominciato a spiegare —
#    cioè è una foglia travestita, che si carica sempre.
while IFS= read -r n; do
    [ -n "$n" ] || continue
    lines=$(wc -l < "$n")
    limit=40
    [ "${n#$REPO/}" = "docs/ai/index.md" ] && limit=50
    [ "$lines" -gt "$limit" ] && graph_problems="${graph_problems}nodo oltre il tetto: ${n#$REPO/} ($lines/$limit righe)"$'\n'
done <<< "$(find "$DOCS" -name 'index.md' 2>/dev/null)"

# 3. Marcatori: un link verso full/ da fuori full/ porta `approfondimento:`, altrimenti è
#    indistinguibile da una discesa e l'agente lo seguirebbe.
while IFS= read -r hit; do
    [ -n "$hit" ] || continue
    src=${hit%%:*}
    case "$src" in *"/full/"*|*"/reports/"*|*"index.md") continue ;; esac
    graph_problems="${graph_problems}link a full/ senza marcatore: ${src#$REPO/}"$'\n'
done <<< "$(grep -rln '](\.\./full/\|](/docs/ai/full/' "$DOCS/ai" --include='*.md' 2>/dev/null | xargs -r grep -Ln 'approfondimento:' 2>/dev/null || true)"

# 4. Il primo livello di docs/ai/ è pulito: quattro cartelle e `index.md`, niente altro (TSV08).
#    È la struttura V3, decisa dal developer il 2026-08-07. Senza questo controllo la struttura regge
#    finché qualcuno se la ricorda — e nel giro di un giorno un file sciolto ricompare, perché scriverlo
#    al primo livello è sempre la cosa più comoda.
#    Il costo di lasciarlo tornare non è estetico: un file al primo livello **non dichiara la propria
#    natura**, e mappa, registro, modello e gemello custom finiscono affiancati e indistinguibili.
while IFS= read -r stray; do
    [ -n "$stray" ] || continue
    graph_problems="${graph_problems}file sciolto in docs/ai/: ${stray#$REPO/} — al primo livello sta solo index.md (TSV08)"$'\n'
done <<< "$(find "$DOCS/ai" -maxdepth 1 -type f -name '*.md' ! -name 'index.md' 2>/dev/null)"

# ---------------------------------------------------------------------------
# TMD63 / TMC08 — sintesi e completa non divergono in silenzio (il perché in testa al file).
# Tenute fuori da `graph_problems`: sono un invariante diverso, e un solo conteggio che sale per due
# ragioni dice «qualcosa non va».
# ---------------------------------------------------------------------------
divergences=""
declared_fulls=""

# Un campo del frontmatter — SOLO il primo blocco `---`: fuori da lì `full:` è prosa.
fm_field() {
    awk -v k="$2" '
        NR == 1     { if ($0 != "---") exit; next }
        $0 == "---" { exit }
        index($0, k ":") == 1 { sub("^" k ":[ \t]*", ""); print; exit }
    ' "$1" 2>/dev/null
}

# Presenza della chiave, valore o no: `solo-completo` si dichiara anche nuda.
fm_has() {
    awk -v k="$2" '
        NR == 1     { if ($0 != "---") exit; next }
        $0 == "---" { exit }
        $0 == k || index($0, k ":") == 1 { found = 1; exit }
        END { exit !found }
    ' "$1" 2>/dev/null
}

# 4. Ogni sintesi che dichiara una completa: la completa esiste, e non è più recente della rilettura.
#    Una sintesi SENZA `full:` è legittima — non tutti i concetti hanno una versione completa.
while IFS= read -r a; do
    [ -n "$a" ] || continue
    rel_a=${a#$REPO/}
    decl=$(fm_field "$a" full)
    [ -n "$decl" ] || continue

    # `full:` è relativo alla cartella della sintesi, non alla radice: `cd` collassa i `..`.
    dir=$(dirname "$(dirname "$a")/$decl")
    target=""
    [ -d "$dir" ] && target="$(cd "$dir" && pwd -P)/$(basename "$decl")"
    if [ -z "$target" ] || [ ! -f "$target" ]; then
        divergences="${divergences}completa dichiarata e inesistente: $rel_a → $decl"$'\n'
        continue
    fi
    declared_fulls="${declared_fulls}${target}"$'\n'

    checked=$(fm_field "$a" full-checked)
    if [ -z "$checked" ]; then
        divergences="${divergences}sintesi con full: e senza full-checked: $rel_a"$'\n'
        continue
    fi

    rel_f=${target#$REPO/}
    origin="ultimo commit"
    fdate=$(git -C "$REPO" log -1 --format=%cs -- "$rel_f" 2>/dev/null)
    if [ -z "$fdate" ]; then
        fdate=$(date -r "$target" +%F 2>/dev/null)
        origin="data di modifica, file non ancora committato"
    fi
    [ -n "$fdate" ] || continue

    if [[ "$fdate" > "$checked" ]]; then
        divergences="${divergences}divergenza: $rel_f del $fdate ($origin) è più recente di full-checked: $checked in $rel_a"$'\n'
    fi
done <<< "$(find "$ABSTRACT" -name '*.md' 2>/dev/null | sort)"

# 5. Nessuna completa orfana (TMD61): o esiste la sintesi allo stesso percorso relativo, o qualcuno
#    la dichiara nel proprio `full:`, oppure dichiara `solo-completo` — l'unica eccezione prevista.
while IFS= read -r f; do
    [ -n "$f" ] || continue
    fm_has "$f" solo-completo && continue
    [ -f "$ABSTRACT/${f#$FULL/}" ] && continue
    printf '%s' "$declared_fulls" | grep -qxF "$f" && continue
    divergences="${divergences}completa senza sintesi: ${f#$REPO/} (nessuna sintesi allo stesso percorso, nessun full: che la dichiari, nessun solo-completo)"$'\n'
done <<< "$(find "$FULL" -name '*.md' 2>/dev/null | sort)"

divergences=$(printf '%s' "$divergences" | sed '/^$/d')
ndiv=$([ -n "$divergences" ] && echo "$divergences" | wc -l | tr -d ' ' || echo 0)

graph_problems=$(printf '%s' "$graph_problems" | sed '/^$/d')

if [ "$MODE" = json ]; then
    ng=$([ -n "$graph_problems" ] && echo "$graph_problems" | wc -l | tr -d ' ' || echo 0)
    printf '{"systemMessage": "Grafo: %s problemi, %s divergenze sintesi/completa. Costo peggiore %s token (%s), %s foglie sopra %s."}\n' \
        "$ng" "$ndiv" "$worst" "$(basename "$worst_file")" "$over" "$CEILING"
    exit 0
fi

echo "Costo di lettura della documentazione — stima a ~$BYTES_PER_TOKEN byte/token"
echo
printf '  radice          %s  (%s byte, %s token)\n' "${ROOT#$REPO/}" "$root_bytes" "$root_tok"
printf '  foglie          %s\n' "$count"
echo
printf '  CAMMINO PEGGIORE   %6s token   %s\n' "$worst" "$worst_file"
printf '  media              %6s token\n' "$avg"
printf '  obiettivo          %6s token   (TMD45)\n' "$TARGET"
printf '  soglia fallimento  %6s token   → %s foglie la superano\n' "$CEILING" "$over"
echo
closed=$(find "$DOCS/task/done" "$DOCS/ai/full" "$DOCS/ai/dev-guide" -name '*.md' 2>/dev/null | wc -l | tr -d ' ')
closed_b=$(find "$DOCS/task/done" "$DOCS/ai/full" "$DOCS/ai/dev-guide" -name '*.md' -exec cat {} + 2>/dev/null | wc -c)
printf '  zone chiuse        %6s file, %s token — fuori dal cammino, si aprono su richiesta\n' \
    "$closed" "$(( closed_b / BYTES_PER_TOKEN ))"
echo
echo "Le $TOP foglie più care:"
echo "$rows" | head -"$TOP" | awk -F'\t' '{printf "  %6s token  %7s byte  %s\n", $1, $2, $3}'
echo
if [ -n "$graph_problems" ]; then
    echo "Proprieta' del grafo — $(echo "$graph_problems" | wc -l | tr -d ' ') problemi:"
    echo
    echo "$graph_problems"
    echo
fi

if [ -n "$divergences" ]; then
    echo "Sintesi e completa — divergenze: $ndiv (TMD63)"
    echo
    echo "$divergences"
    echo
fi

if [ "$worst" -gt "$CEILING" ]; then
    echo "Sopra la soglia: $(( worst - CEILING )) token da recuperare sul cammino peggiore."
    exit 1
fi
echo "Cammino peggiore entro la soglia."
