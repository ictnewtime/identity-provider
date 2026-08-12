# Regole per Claude — identity-provider2

**Punto di ingresso: [docs/ai/index.md](docs/ai/index.md)**, si legge **a ogni prompt**. Questo file
non lo duplica: fissa le regole del lavoro di prodotto (`docs/task/`) e i vincoli di questo repo.

Identificatori, nomi di file/cartelle e messaggi di commit in **inglese**; contenuto dei documenti in
**italiano**.

## L'export è parziale — cosa non esiste

`docs/ai/` è stato importato da un altro progetto **in modo parziale**. Non esistono: `docs/ai/full/`,
`docs/ai/dev-guide/`, `docs/ai/reports/`, `docs/task/index.md`, `docs/requirements-actual.md`,
`docs/todo-manual.md`, i gemelli `*-custom.md` in `abstract/`.

Quindi: **i link verso quei percorsi sono rotti e non si inseguono** — non sono un errore da
correggere né un file da inventare. Vale sull'agente ciò che è già scritto: `full/` non si apre
comunque. Se serve una casella che manca, si dice al developer prima di crearla.

Restano validi e leggibili: [abstract/](docs/ai/abstract/index.md) con `planning`, `writing-code`,
`testing`, `perf-leak`, `findings`, `where-documents-go`, `identifiers`, `graph-navigation`.

## `docs/task/` — la struttura

```
docs/task/
├── todo/            lavoro da fare
├── done/            lavoro chiuso — non si cancella: `mv` da todo/ a done/
├── backlog/         dubbi e proposte non ancora decise (backlog.md)
└── vulnerability/   difetti accertati, con livello e mitigazione (vulnerability.md)
```

**Dove va un rilievo**: rompe qualcosa che esiste → `vulnerability/vulnerability.md` · manca
qualcosa di deciso → un task in `todo/` · è un dubbio o un'idea → `backlog/backlog.md` · riguarda il
**modo di lavorare** e non il prodotto → sempre `backlog/backlog.md`, finché `docs/ai/full/` non
esiste. Una voce che non rompe niente **non è un difetto**: è un miglioramento, e va nel lavoro.

**Le domande aperte di un task restano nel § 4 della sua analisi**: nel backlog va ciò che **nessun
piano copre**. Due copie della stessa domanda sono due verità che divergono.

Dentro `todo/` e `done/` le cartelle si chiamano **`YYYYMMDD-<short-description>`**, dove la
descrizione è in inglese, in kebab-case, **massimo 3 parole**: `20260812-provider-search-fix`.
La data è quella di nascita del task e **non cambia** quando il task passa in `done/`.
Un task per **obiettivo**, non per componente.

**Il suffisso `-vN`** marca le tranche di uno **stesso lotto** diviso per obiettivo — un elenco di
rilievi che arriva tutto insieme e si lavora a pezzi: `…-findings-v1`, `-v2`, `-v3`. Le tranche
condividono il nome e si citano a vicenda in testa all'analisi; ognuna ha la **sua sigla** di ID. Non
è un numero di versione: `-v2` non sostituisce `-v1`, gli sta accanto.

**Ogni cartella ha una riga in [docs/task/index.md](docs/task/index.md)**, e ogni riga ha la sua
cartella — è quel file a dire cosa contiene una cartella il cui nome non basta a dirlo. Lo verifica
`./scripts/check-task-index.sh`, nei due versi.

In ogni cartella **foglia** (quella finale, che non ne contiene altre) stanno **sempre due file**:

| File | Cos'è |
|---|---|
| `analysis.md` | l'analisi in 5 sezioni — si scrive **per prima** |
| `action-plan.md` | il piano d'azione: la tabella dei punti, dopo l'analisi |

## `analysis.md` — le 5 sezioni, in quest'ordine

1. **Obiettivo** — cosa deve essere vero alla fine, per esteso, e perché adesso.
2. **Situazione attuale** — le dipendenze attuali e gli eventuali **breaking change**. Ogni punto
   porta il comando o il `file:riga` che lo dimostra: niente ricostruzioni a memoria.
3. **Analisi** — nuovi requisiti, cambiamenti e **cancellazioni di codice**; le alternative viste,
   anche quelle scartate subito, col perché.
4. **Da decidere** — **in fondo e tutto insieme**, diviso in *vincoli · conflitti · ignoto*: le
   domande a cui risponde il **developer**. Sparse nel documento si leggono a pezzi e non si risponde.
5. **Consigli** — per ogni domanda del § 4 la raccomandazione dell'agente, che **non sostituisce** la
   risposta.

## `action-plan.md` — la lista dei punti

Tabella con le colonne: **`ID · Stato · Punto · File toccati · Rischio · V · Come si verifica`**.

- **ID**: sigla univoca in tutto `docs/` — 1 lettera d'area (`T` per `todo/`, `D` per `done/`) + 2
  lettere del task + progressivo a 2 cifre → `TPS01`. Non si riusa mai:
  [identifiers.md](docs/ai/abstract/identifiers.md).
- **Un punto = un intervento verificabile**, col suo modo di verifica. «Migliorare la testabilità»
  non è un punto.
- **V** è `auto` (lo stabilisce uno script) o `man` (lo legge una persona).
- **Stato**: `da approvare` → `approvato` → `fatto`, oppure `scartato` col perché. `fatto` solo se
  **verificato** — test eseguito o comportamento osservato.
- Punti ordinati per **dipendenza**; decisioni sopra, spostamenti sotto.
- Il piano **cita l'analisi, non la ripete**. A fine implementazione si aggiorna **lo stesso file**:
  il piano non si cancella, è la storia della decisione.

## Le regole che bloccano

- **Nessuna implementazione senza piano approvato** (R10) e **prima del piano viene l'analisi**
  (R17): alla richiesta si risponde col piano, non col codice. Ci si ferma al primo punto non
  approvato; un `procedi` esplicito vale per tutti. Eccezione: la correzione **banale e reversibile**
  (un refuso, una costante) si annuncia in una riga e si fa.
- **Il versionamento è del developer**: git in sola lettura, nessun commit né push. A fine giornata si
  *propone* il messaggio di commit, in inglese, citando gli ID chiusi.
- **Scritture sui sistemi reali e sul database**: solo su approvazione esplicita, volta per volta.
- **Perf/leak obbligatorio su ogni service** — è policy dell'organizzazione, nessuna eccezione: il
  turno non si chiude senza. Checklist a 5 voci, esito dichiarato **voce per voce** con il perché di
  ogni «non applicabile»: [perf-leak.md](docs/ai/abstract/perf-leak.md).
- **Niente resta solo in chat** (R0): analisi, decisioni, dubbi e rischi finiscono in un file —
  dove, lo dice [findings.md](docs/ai/abstract/findings.md).
- **Onestà sul risultato** (R16): se i test falliscono si riporta l'output, se una fase è saltata si
  dice, e ogni cifra dichiara il comando che la produce.
- Ogni risposta si chiude col **riepilogo**: `[x]` fatto · `[ ]` aperto · `[?]` decidi tu, più due
  lavori fra cui scegliere e il difetto aperto più grave.

## Questo progetto

Laravel 12 / PHP 8.2 — Passport, JWT, Socialite, Inertia. Il codice sta in `app/`
(`Services/`, `Repositories/`, `Http/{Controllers,Requests,Resources,Middleware}/`, `Models/`).

- **Test**: `docker exec idp_app_2 php artisan test` — non c'è PHP locale.
- **Composer**: via immagine `composer:2`, non installato in locale.
- **Ambiente**: `docker compose up` → app su `:8001`, MariaDB su `:3307`, Mailpit su `:8025`.
- **Controlli**: gli script in [scripts/](scripts/) (`check-ids.sh`, `check-links.sh`,
  `check-task-index.sh`, `check-perf-gate.sh`, …) arrivano dallo stesso export e presuppongono
  cartelle qui assenti: si eseguono a mano, e un loro fallimento va letto prima di inseguirlo.
