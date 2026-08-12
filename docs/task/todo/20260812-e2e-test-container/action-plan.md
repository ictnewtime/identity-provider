# Piano d'azione — un container dedicato per i test E2E

Sigla dichiarata dall'analisi: `TEC` — qui non si ridichiara.

Stato: **da approvare** · Data: 2026-08-12 · Analisi: [analysis.md](./analysis.md)

Fatti e alternative stanno nell'analisi: `F…` e `D…` puntano lì. V — `auto`: lo stabilisce un
comando · `man`: lo legge una persona.

**Questo piano non scrive un solo file di workflow.** I test in pipeline sono
[backlog/20260812-pipeline-tests](../../backlog/20260812-pipeline-tests/action-plan.md), fermo per
decisione del developer.

## Onda 1 — far partire Cypress, una volta

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TEC01 | da approvare | **`D3`** — provare l'esecuzione più corta possibile: `cypress/included:15.15.0` sulla rete `database-network`, repository montato, `CYPRESS_baseUrl=http://app`. Serve a scoprire se `node_modules` dell'host confligge col binario dell'immagine (§ 3). **Nessun file toccato**: è un comando `docker run` usa-e-getta | nessuno (accertamento) | basso, ma **decide la forma di tutto il resto** | auto | Cypress parte ed elenca le specifiche. Se fallisce cercando il binario, la risposta a `D3` è «volume anonimo su `node_modules`» |
| TEC02 | da approvare | **`D2`** — rendere `baseUrl` parametrico con **default invariato**: `process.env.CYPRESS_BASE_URL ?? "http://localhost:8001"`. Chi lancia da fuori non si accorge di niente | `cypress.config.js:32` | basso | auto | `npm run cy:run` da host continua a funzionare senza variabili; con `CYPRESS_BASE_URL=http://app` punta al servizio |
| TEC03 | da approvare | **`D1`** — servizio `cypress` nel `docker-compose.yml`, **dietro un profilo** così non parte mai da solo: immagine `cypress/included:15.15.0`, rete `database-network`, `CYPRESS_BASE_URL=http://app`, più la soluzione di `node_modules` decisa da `TEC01` | `docker-compose.yml` | basso — un profilo inattivo non cambia niente per chi non lo usa | auto | `docker compose up` senza profilo **non** scarica l'immagine; `docker compose --profile e2e run cypress` esegue i test |

## Onda 2 — chiudere i punti che aspettano questa verifica

È la ragione per cui il task esiste: quattro punti della tranche
[v1](../../done/20260812-static-analysis-findings-v1/action-plan.md) sono chiusi con la verifica
**delegata qui**, e il task è stato spostato in `done/` su quella delega. Se `TEC04` li smentisce, si
riaprono là: un task chiuso non è un task immune.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TEC04 | da approvare | Eseguire `scripts/prepare-e2e-credentials.sh` nel container **`app`** (serve `php artisan`, `F10`), poi i test dal container `cypress`. Chiude la verifica di `TSA10a`, `TSA11`, `TSA12` e `TSA03` | nessuno | medio — è una **scrittura sul database**, che R1 subordina ad approvazione esplicita | auto | `login.cy.js` passa con gli utenti generati; `crud-user.cy.js` passa con la password presa da `Cypress.env("newUserPassword")` |
| TEC05 | da approvare | Riportare l'esito in `done/20260812-static-analysis-findings-v1`: se i test passano, la delega si chiude e si annota; **se falliscono**, i punti tornano aperti e il task torna in `todo/`, con l'esito scritto dov'era sbagliata l'analisi e non in coda (R16) | `docs/task/done/20260812-static-analysis-findings-v1/action-plan.md` | basso | man | il piano della v1 dice cosa hanno stabilito i test, non che li aspetta |

## Onda 3 — le conseguenze

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TEC06 | da approvare | **`D4`, `BDB22`** — togliere Cypress dall'immagine di Laravel: `npm ci --omit=dev` oppure `CYPRESS_INSTALL_BINARY=0`. Con un container dedicato, la ragione per cui c'era è decaduta. **Tocca il `Dockerfile`, cioè il deploy**: punto separato e ultimo | `Dockerfile:49` | medio — se qualcosa in produzione dipendesse da una dipendenza di sviluppo, si scoprirebbe qui | auto | l'immagine si costruisce, l'applicazione risponde su `/up`, e la sua dimensione è misurabilmente minore |
| TEC07 | da approvare | Aggiornare `docs/TEST.md`: oggi dice «Cypress fuori dal container» e spiega cosa manca. Con `TEC03` diventa «Cypress nel suo container, oppure da fuori», e la tabella delle assenze diventa la **motivazione** della scelta invece di una diagnosi aperta | `docs/TEST.md` | basso | man | il documento descrive i due modi e non contraddice il compose |

## Cosa questo piano non copre

- **La pipeline**: `BPT07`/`BPT08`. Questo task ne è il prerequisito e si ferma prima.
- **La versione di Node nell'immagine** (`F6`, `BDB23`): resta non fissata. Con `TEC06` l'immagine non
  esegue più Cypress, quindi il vincolo `engines` decade — ma `npm run build` continua a girarci, e
  quella fragilità non la tocca nessun punto qui.
- **Scrivere test E2E nuovi**: il task rende eseguibili i tre che esistono, non ne aggiunge.
- **`D5`** — se i test avessero bisogno di Mailpit, il servizio va aggiunto alle dipendenze. Va letto
  prima di `TEC03`, ma non è un punto: è una riga di configurazione in più o in meno.
