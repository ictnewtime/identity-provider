---
full: ../full/where-documents-go.md
full-checked: 2026-08-07
---

# Come si citano le voci

Ogni voce — di un todo, un backlog, un registro di difetti, un piano — ha un **ID univoco in tutto
`docs/`**, che serve a ritrovarla mesi dopo da un commit o da un'altra pagina.

```
T  TC  01
│  │   └── progressivo a due cifre, nell'ordine in cui le voci nascono
│  └────── 2 lettere: acronimo del documento o del task
└───────── 1 lettera: l'AREA in cui la voce è NATA
```

| Area | Lettera |
|---|---|
| `docs/ai/` — la procedura | `A` |
| `docs/task/backlog/` · `todo/` · `done/` · `vulnerability/` | `B` · `T` · `D` · `V` |
| `docs/<componente>/` · `docs/<entità esterna>/` | `L` · `C` |
| `docs/ai/full/` · `dev-guide/` · `manual-activity/` | `F` · `G` · `M` — riservate |

**La lettera è quella di nascita e non cambia mai**: un task proposto in `backlog/` resta `B…` anche
in `todo/` e poi in `done/`. Un ID è un'etichetta, non un indirizzo — è la ragione per cui lo schema
regge agli spostamenti, che sono la norma.

**Le due regole che lo rendono affidabile**: un ID **non si riusa mai**, nemmeno quando la voce è
chiusa o il documento sparisce; una **sigla non si libera mai**, nemmeno per i task chiusi. Lo
verifica `scripts/check-ids.sh`.

**Quando due si scontrano**: una voce che si divide prende una lettera in coda (`TTC01a`); una sigla
già presa si distingue con una lettera **prima** del progressivo (`TTCa01`).

**Ogni documento dichiara in testa le proprie sigle**, così chi lo apre le ha sotto gli occhi.
Attenzione: non scrivere `` `parola` = `` nelle due righe sotto la dichiarazione — il controllo lo
legge come una sigla ([AVU10](/docs/ai/full/backlog.md)).

approfondimento: [../full/where-documents-go.md](../full/where-documents-go.md) — la forma completa,
i casi di collisione e la conversione degli ID storici.
