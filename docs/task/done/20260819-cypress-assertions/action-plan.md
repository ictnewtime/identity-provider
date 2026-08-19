# Piano — i test Cypress senza asserzione

Sigla dichiarata dall'analisi: `TCY` — qui non si ridichiara.

Stato: **chiuso** (2026-08-19) — nessun punto implementato: due scartati perché il codice è stato cancellato, quattro per decisione del developer · Data: 2026-08-19 · Analisi: [analysis.md](./analysis.md), che questo piano
**cita e non ripete**. `F…` e `D…` puntano lì. V — `auto`: lo stabilisce un comando · `man`: lo legge
una persona.

> **Aggiornato il 2026-08-19.** Il developer ha risposto a `D1` **cancellando**: via
> `cypress/e2e-bk/` e via `cypress/e2e/provider/crud-provider.cy.js`, quest'ultimo per un rilievo
> diverso — *«Add some tests to this file or delete it»* — perché aveva **zero `it()`**. `TCY01` e
> `TCY03` sono **scartati**: erano il modo di convivere con quei file. Resta l'igiene (`TCY02`,
> `TCY06`), il controllo che impedisce il ritorno (`TCY05`), e un solo punto con del codice dentro
> (`TCY04`), che vive o muore con la risposta a `D2`.

I punti sono ordinati per **dipendenza**: la decisione stava sopra, ed è stata presa.

## Onda 1 — la decisione, e la sua conseguenza

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TCY01 | **scartato** (2026-08-19) | Escludere `cypress/e2e-bk/` da SonarQube. **Non ha più oggetto: il developer ha cancellato la cartella** (`git ls-files cypress/e2e-bk` non stampa niente), e non si esclude dall'analisi ciò che non esiste. Scartato e non riscritto: uno scarto col perché si rilegge, un punto riscritto no. Resta valida la parte del ragionamento che non riguardava `e2e-bk` — le esclusioni di `TCY02` | — | — | — | — |
| TCY02 | **scartato** (2026-08-19) | Escludere `vendor/`, `node_modules/`, `public/build/`, `storage/` dall'analisi. **Scartato per decisione del developer il 2026-08-19**, che chiude il task con le cancellazioni. **Cosa resta scoperto**: con `-Dsonar.sources=.` quelle cartelle continuano a essere analizzate, quindi il quality gate può fermarsi su codice di terzi e lo scan costa più del necessario. Non è stato verificato quanto: serviva il numero di file nel report | — | — | — | — |

## Onda 2 — i test

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TCY03 | **scartato** (2026-08-19) | Il punto ripetuto: un'asserzione agli 11 `it()` di `cypress/e2e-bk/`. **Scartato con `TCY01`**, e per la stessa ragione: i file non ci sono più. Era il punto che il developer aveva chiesto di preparare, e la sua analisi ha portato a cancellare invece che a correggere — il lavoro è servito a non farlo | — | — | — | — |
| TCY04 | **scartato** (2026-08-19) | L'asserzione vera negli **8 `it()`** di `cypress/e2e/`. **Scartato per decisione del developer il 2026-08-19**. **Cosa resta scoperto**: se SonarQube non riconosce `.should()` come asserzione (`D2`, mai verificata), quegli 8 restano rilievi e il gate torna rosso al primo scan — questa volta su test che **girano davvero**, dove l'asserzione non sarebbe teatro | — | — | — | — |
| TCY05 | **scartato** (2026-08-19) | Lo script che segnala gli `it()` senza asserzione e i file di spec senza nemmeno un `it()`. **Scartato per decisione del developer il 2026-08-19**. **Cosa resta scoperto**: il caso che ha prodotto `crud-provider.cy.js` — uno scheletro copiato, `describe` più `beforeEach`, zero test, col nome di un altro file — non ha nessuna guardia. Se ricapita, lo dirà di nuovo SonarQube, a deploy fermo | — | — | — | — |
| TCY06 | **scartato** (2026-08-19) | Chiudere `BDB17` nel backlog. **Scartato**: il developer ha scartato **`BDB17` stesso** il 2026-08-19, quindi non c'è più una voce da chiudere | — | — | — | — |

## Cosa questo piano non copre

- **Il workflow `deploy-staging.yml`**: la pipeline non si tocca (decisione del 2026-08-12, per cui
  `TSD13` è in [pipeline-tests](../../backlog/20260812-pipeline-tests/)). Da qui la scelta del file di
  proprietà invece di un argomento in più allo scanner. Se `D3` rivelasse che il file non viene letto,
  il seguito è del developer, non di questo piano.
- **Cancellare `cypress/e2e-bk/`**: già deciso il contrario il 2026-08-12 (`D3` di `TSA`, `TSA07`
  scartato, voce `BDB10`). Qui non si ridiscute.
- **Il resto del quality gate** (`D5`): questo piano chiude una regola, `S2699`. Se il gate fosse rosso
  anche per altro, il deploy resterebbe fermo — e lo si scopre solo guardando il report intero.
- **Eseguire i test E2E**: serve il container di
  [e2e-test-container](../../todo/20260812-e2e-test-container/), che non c'è ancora. `TCY04` si verifica
  quando quello arriva.
