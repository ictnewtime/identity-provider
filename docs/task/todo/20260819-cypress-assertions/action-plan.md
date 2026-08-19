# Piano — i test Cypress senza asserzione

Sigla dichiarata dall'analisi: `TCY` — qui non si ridichiara.

Stato: **da approvare** · Data: 2026-08-19 · Analisi: [analysis.md](./analysis.md), che questo piano
**cita e non ripete**. `F…` e `D…` puntano lì. V — `auto`: lo stabilisce un comando · `man`: lo legge
una persona.

I punti sono ordinati per **dipendenza**: la decisione sta sopra, perché da `D1` dipende se il punto
ripetuto (`TCY03`) sia lavoro utile o lavoro in codice che non viene eseguito.

## Onda 1 — la decisione, e la sua conseguenza

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TCY01 | da approvare | **`D1`(a)** — `sonar-project.properties` nel repository, con `sonar.exclusions` su `cypress/e2e-bk/**`. È la strada indicata il 2026-08-12 quando `TSA07` fu scartato, e mai percorsa: chiude `BDB17`. **Nel repository e non nel workflow**, perché la pipeline non si tocca — lo scanner legge il file da sé, e `sonar.exclusions` non è passata da riga di comando, quindi non viene sovrascritta | `sonar-project.properties` (nuovo) | medio — cambia cosa il gate misura | man | al primo scan i rilievi `S2699` da `cypress/e2e-bk/` **spariscono dal report**, e il numero di file analizzati cala. È anche la verifica che `D3` chiedeva: se non cala, il file non viene letto |
| TCY02 | da approvare | **Stesso file, stessa riga di costo**: escludere anche `vendor/**`, `node_modules/**`, `public/build/**`, `storage/**`. Con `-Dsonar.sources=.` finiscono nell'analisi, ed è il sospetto scritto in `BDB17` e mai verificato: far pagare il quality gate su codice di terzi. Sta con `TCY01` perché è lo stesso file, ma è una decisione diversa e va approvata a parte | `sonar-project.properties` | medio | man | il numero di file analizzati nel report cala di un ordine di grandezza; nessun rilievo resta con percorso `vendor/` o `node_modules/` |

## Onda 2 — i test

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TCY03 | da approvare — **attivo solo se `D1` sceglie (b)** | **Il punto ripetuto**, come chiesto: aggiungere almeno un'asserzione agli **11** `it()` di `cypress/e2e-bk/` che non ne hanno **nessuna** (§ 2 dell'analisi ha l'elenco con `file:riga`). Stessa forma su tutti, un file per volta: `actions.cy.js` (2), `cypress_api.cy.js` (3), `misc.cy.js` (2), `connectors.cy.js`, `navigation.cy.js`, `querying.cy.js`, `waiting.cy.js` (1 ciascuno). **Se il report mostra che Sonar non riconosce `.should()` (`D2`), gli `it()` da correggere qui diventano 78** e il punto va rifatto a quel numero, non allargato di nascosto | 7 file in `cypress/e2e-bk/2-advanced-examples/` | basso — codice che non viene eseguito (`F4`) | auto | lo script di `TCY05` non segnala più nessun `it()` senza asserzione in `e2e-bk`; al primo scan i rilievi `S2699` di quei file spariscono |
| TCY04 | da approvare | **Il caso che resta comunque** (`F5`): gli **8** `it()` di `cypress/e2e/` — i test **veri**, quelli che girano — asseriscono tutti con `.should()` e **nessuno** usa `expect()`. Se la regola non riconosce la catena Cypress sono rilievi anche loro, e qui l'asserzione va aggiunta **davvero**: un test che gira senza asserzione passa sempre e sembra controllare qualcosa. Indipendente da `D1`: l'esclusione di `e2e-bk` non li tocca | `cypress/e2e/auth/`, `cypress/e2e/provider/`, `cypress/e2e/user/` | medio — sono i test veri | man | i test continuano a passare **e** l'asserzione nuova fallisce se la si rompe di proposito. Serve il container di [e2e-test-container](../20260812-e2e-test-container/) per eseguirli |
| TCY05 | da approvare | **Un controllo che tenga**: `scripts/check-cypress-assertions.sh` segnala gli `it()` senza asserzione **prima** del gate, invece di scoprirlo dal report di Sonar a deploy fermo. Su `cypress/e2e/` sempre; su `e2e-bk/` solo se `D1` sceglie (b) — se quella cartella è esclusa dall'analisi, controllarla qui la rimetterebbe dentro da un'altra porta | `scripts/check-cypress-assertions.sh` (nuovo) | basso | auto | **provato nei due versi**: togliendo l'asserzione da un test vero lo script esce con codice ≠ 0; rimessa, esce 0 |
| TCY06 | da approvare | Chiudere **`BDB17`** nel backlog citando `TCY01`: la voce chiedeva se esistessero esclusioni e resta aperta da sette giorni. Un dubbio sciolto che rimane scritto come dubbio si rilegge come lavoro da fare | `docs/task/backlog/backlog.md` | basso | man | `BDB17` è spuntato e nomina il punto che l'ha sciolto, come già fatto per `BDB10` |

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
  [e2e-test-container](../20260812-e2e-test-container/), che non c'è ancora. `TCY04` si verifica
  quando quello arriva.
