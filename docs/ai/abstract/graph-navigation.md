---
full: ../full/graph-navigation.md
full-checked: 2026-08-07
---

# Come si attraversa questa documentazione

**Tre livelli.** Il **nodo** (`index.md`) instrada e basta, 40 righe. La **foglia** dice *cosa fare*.
L'**approfondimento** (`full/`) dice *perché*, e l'agente non lo apre — salvo che il developer lo
indichi, o che si stia **modificando la meta-doc** toccando quel concetto.

> **Dal 2026-08-07 `full/` non è più solo «il perché»**: contiene **tutto ciò che non è l'indice né una
> sintesi** — i registri, i modelli, i gemelli `*-custom`, il registro di migrazione (`TSV04`). Resta
> **chiusa lo stesso**, e per i registri è una scelta consapevole: `full/backlog.md` accumula quello che
> emerge sulla meta-doc e **non si rilegge**, perché rileggerlo a ogni iterazione costava più di quanto
> rendesse. La seconda condizione qui sopra — «se stai modificando la meta-doc» — **non** riapre i
> registri: vale per il concetto che si sta toccando, non per la lista.

**Una foglia è incompleta per costruzione, ed è corretto.** Conoscere un frammento è lo stato
normale: chi carica tutto non è più informato, è più caro. Ma *cosa fare* sta **per intero** nella
foglia — se per agire serve l'approfondimento, il taglio è nel posto sbagliato.

**Eccezione senza deroghe**: il contenuto d'azione delle regole di **sicurezza** non scende mai in
`full/`.

**Tre tipi di arco**, e due vanno marcati:

| Arco | Come si scrive | Si segue scendendo? |
|---|---|---|
| discesa | link normale | sì, **se il concetto serve** |
| approfondimento | `approfondimento:` + link in `full/` | solo alle condizioni sopra |
| rimando | `rimando:` + link a un nodo già raggiungibile | **no**: orienta chi legge |

**Le zone chiuse**, che l'agente non attraversa: `full/` (il capire) · `dev-guide/` (le procedure del
developer, se ne legge solo l'indice) · **`docs/task/done/`** (storia di decisioni: si apre solo
quando si ricostruisce un perché).

**Se un sotto-grafo non c'entra col lavoro, non si scende.**

**Tetti**, misurati sui byte veri: nodo **40 righe** · foglia **~40 righe** a profondità 2 — è ciò
che resta di 2.000 token tolte radice (1.089) e nodo (400).

Un nodo nasce a **4 foglie o 300 righe**, e muore sotto entrambe. Profondità massima 2: dopo un
`/compact` si riparte dalla radice e si ripaga ogni salto.

**Tre famiglie, tre soglie**: i **concetti** non crescono e oltre il tetto si **sintetizzano**; i
**registri** crescono, ~1000 righe, si spezzano per stato; i **documenti di task** si spezzano fra
*inquadrare* ed *eseguire*.

**Chi instrada cosa**: ciò che si lega a un percorso di file va in una regola con `paths:`, una
procedura eseguibile va in uno skill, il grafo prende i documenti che legge anche il developer.

approfondimento: [../full/graph-navigation.md](../full/graph-navigation.md) — l'aritmetica dei
tetti, il contratto di corrispondenza fra sintesi e completa, e il rilevatore di divergenza.
