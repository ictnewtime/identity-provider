# Analisi — i test Cypress senza asserzione bloccano il quality gate

**Identificatori**: `TCY` = task cypress-assertions

Stato: da approvare · Data: 2026-08-19

## 1. Obiettivo

Togliere dal quality gate di SonarQube i rilievi **bloccanti** `javascript:S2699` — *«Add at least one
assertion to this test case»* — che oggi arrivano tutti dai file Cypress.

Perché adesso: sono **bloccanti**, quindi non sono un debito che si paga con calma. Il job
`sonar-scan` gira con `-Dsonar.qualitygate.wait=true`
([deploy-staging.yml:43](../../../../.github/workflows/deploy-staging.yml)), cioè **aspetta l'esito e
fallisce col gate**. Finché il gate è rosso, il deploy non parte.

C'è un secondo motivo, ed è quello che cambia la forma della soluzione: **il seguito era già scritto e
fermo**. Chiudendo `TSA07` il 2026-08-12 il developer ha deciso di **tenere** `cypress/e2e-bk/` (`D3`),
e la conseguenza fu annotata subito: *«i rilievi dentro quella cartella vanno **esclusi lato
SonarQube** invece che eliminati»* — parcheggiata in
[`BDB17`](../../backlog/backlog.md). Questo task è il momento in cui quella voce si scioglie.

## 2. Situazione attuale

### Dove sono i rilievi, contati

Conteggio fatto con un parser che bilancia le graffe di ogni `it()` e ne guarda il corpo — non un
`grep`, che su questi file conta anche le asserzioni dentro le stringhe di esempio.

| # | Fatto | Prova |
|---|---|---|
| F1 | `cypress/e2e-bk/` contiene **20 file** e **123 `it()`**. Di questi, **11 non hanno asserzione in nessuna forma**: né `expect()`/`assert`, né `.should()`/`.and()` | tabella qui sotto, con `file:riga` |
| F2 | Altri **67 `it()`** di quella cartella hanno **solo** `.should()`/`.and()`, mai `expect()`. Se la regola di Sonar non riconosce la catena Cypress, sono rilievi anche quelli | stesso parser, colonna «solo `.should()`» |
| F3 | I due esempi portati dal developer — `actions.cy.js` `.click()` e `connectors.cy.js` `.each()` — stanno **entrambi** fra gli 11 di `F1` | righe 68 e 8, tabella sotto |
| F4 | **`cypress/e2e-bk/` non viene eseguita da Cypress.** `specPattern` punta a `cypress/e2e/**` e basta: quei 123 test non girano mai, né in locale né altrove | [cypress.config.js:35](../../../../cypress.config.js) |
| F5 | I test **veri** sono `cypress/e2e/` — 4 file, **8 `it()`** — e **nessuno di essi usa `expect()`**: tutti asseriscono con `.should()`. Se vale `F2`, sono flaggati anche loro | stesso parser su `cypress/e2e/` |
| F6 | Sonar analizza la **radice intera**: il workflow passa `-Dsonar.sources=.` e nel repository **non esiste** `sonar-project.properties`, quindi non c'è nessuna esclusione dichiarata da noi | [deploy-staging.yml:41](../../../../.github/workflows/deploy-staging.yml); `ls sonar-project.properties` → non esiste |

Gli **11** `it()` di `F1`, per esteso:

| File e riga | Titolo del test |
|---|---|
| `cypress/e2e-bk/2-advanced-examples/actions.cy.js:68` | `.click() - click on a DOM element` |
| `cypress/e2e-bk/2-advanced-examples/actions.cy.js:285` | `cy.scrollTo() - scroll the window or element to a position` |
| `cypress/e2e-bk/2-advanced-examples/connectors.cy.js:8` | `.each() - iterate over an array of elements` |
| `cypress/e2e-bk/2-advanced-examples/cypress_api.cy.js:11` | `.add() - create a custom command` |
| `cypress/e2e-bk/2-advanced-examples/cypress_api.cy.js:42` | `.debug() - enable or disable debugging` |
| `cypress/e2e-bk/2-advanced-examples/cypress_api.cy.js:146` | `Control what is printed to the Command Log` |
| `cypress/e2e-bk/2-advanced-examples/misc.cy.js:73` | `cy.screenshot() - take a screenshot` |
| `cypress/e2e-bk/2-advanced-examples/misc.cy.js:78` | `Cypress.Screenshot.defaults() - change default config of screenshots` |
| `cypress/e2e-bk/2-advanced-examples/navigation.cy.js:30` | `cy.reload() - reload the page` |
| `cypress/e2e-bk/2-advanced-examples/querying.cy.js:88` | `best practices - selecting elements` |
| `cypress/e2e-bk/2-advanced-examples/waiting.cy.js:10` | `cy.wait() - wait for a specific amount of time` |

### Il numero dei rilievi non lo so, e cambia la risposta

Il developer scrive «tutti i file non hanno assertion». Dal codice le forme sono **due**, e il conto è
molto diverso a seconda di quale la regola riconosca:

- se Sonar riconosce `.should()` come asserzione → i rilievi sono **11**, tutti in `e2e-bk`;
- se non la riconosce → sono **78** in `e2e-bk` **più 8 in `cypress/e2e/`**, cioè anche nei test veri.

I due esempi portati non distinguono i casi, perché appartengono a entrambi gli insiemi (`F3`). È la
domanda `D2` del § 4, e la risposta sta nel report, non nel codice.

### Dipendenze e breaking change

- **Nessun breaking change sul prodotto**: qui non si tocca codice dell'applicazione. Il rischio è
  tutto sul **gate**, cioè su cosa la pipeline misura.
- **La pipeline non si tocca** — decisione del developer del 2026-08-12, per cui `TSD13` è finito in
  [pipeline-tests](../../backlog/20260812-pipeline-tests/). Da qui la forma dell'esclusione: un
  `sonar-project.properties` **nel repository**, che lo scanner legge da sé, invece di un argomento in
  più nel workflow.
- **`-Dsonar.sources=.` arriva da riga di comando e vince** sulla chiave omonima del file. Le
  esclusioni no: `sonar.exclusions` non è passata da nessuna parte, quindi il file è l'unico posto
  dove può stare. Resta da verificare che venga letto (`D3`).
- **`cypress/e2e-bk/` non si cancella**: deciso il 2026-08-12 (`D3` di `TSA`, voce `BDB10`).

## 3. Analisi

### Le quattro strade viste

| Strada | Cosa comporta | Perché sì / perché no |
|---|---|---|
| **(a) Escludere `cypress/e2e-bk/` dall'analisi** | un `sonar-project.properties` con `sonar.exclusions` | **È la strada già indicata il 2026-08-12** e non ancora percorsa (`BDB17`). Quel codice non è nostro: sono gli esempi che Cypress genera all'installazione. Un gate rosso per codice di terzi misura la libreria, non il prodotto |
| **(b) Aggiungere un'asserzione a ogni test** | 11 modifiche (o 78, secondo `D2`) | È ciò che il developer ha chiesto di preparare come **punto ripetuto**. Ma quei test **non girano** (`F4`): l'asserzione non verrebbe mai eseguita. Rende verde il gate scrivendo codice che nessuno esegue |
| **(c) Cancellare la cartella** | via 20 file | **Escluso**: già deciso il contrario (`D3`, `TSA07` scartato). Non si ridiscute qui |
| **(d) Marcare i rilievi «won't fix» dal server** | nessun file cambia | Non è ripetibile: vive nel database di SonarQube, non nel repository, e al primo progetto nuovo il lavoro si rifà. E non toglie il costo di analizzare `vendor/` e `node_modules/` |

Le strade **(a)** e **(b)** non si escludono a vicenda, ma **l'ordine conta**: se si esclude la
cartella, aggiungere asserzioni lì dentro diventa lavoro che non produce niente. Per questo nel piano
la decisione sta **sopra** il punto ripetuto, e quel punto è dichiarato attivo solo se la decisione
non lo rende inutile.

### Il caso che resta comunque

Qualunque sia la decisione su `e2e-bk`, `F5` non si sposta: **i test veri non usano `expect()`**. Se
la regola non riconosce `.should()`, quegli 8 rilievi restano anche dopo l'esclusione — e lì
l'asserzione va aggiunta **davvero**, perché quei test girano e un test senza asserzione che gira è
peggio di uno che non gira: passa sempre, e sembra che stia controllando qualcosa.

### Codice cancellato

Nessuno. Nemmeno nella strada (a): l'esclusione non tocca i file, cambia cosa lo scanner guarda.

### Cosa aggiungere oltre a ciò che è stato chiesto

Il developer ha chiesto il punto ripetuto e poi di valutare il resto. Il resto che vedo:

1. **L'esclusione** (sopra) — che è anche la chiusura di `BDB17`.
2. **`vendor/` e `node_modules/`**: con `sonar.sources=.` finiscono nell'analisi. È il sospetto
   scritto in `BDB17` e non ancora verificato; lo stesso file che esclude `e2e-bk` li esclude, e il
   costo è una riga.
3. **Un controllo che tenga**: correggere gli `it()` di oggi non impedisce che il prossimo spec nasca
   senza asserzioni. Uno script in `scripts/` lo rileva **prima** del gate — sui test veri, dove
   l'asserzione ha un senso.

## 4. Da decidere

### Vincoli

- **`D1`** — `cypress/e2e-bk/`: **si esclude dall'analisi** (strada a) oppure **si correggono i test**
  (strada b)? Le due non si escludono, ma se la risposta è (a) il punto ripetuto non serve.
- **`D4`** — se si correggono: quale forma di asserzione? `expect(true).to.be.true` è teatro
  esplicito; una vera (`cy.url().should(...)`) è teatro travestito, perché il test non gira comunque.

### Conflitti

- **`D2`** — quanti sono i rilievi, e **in quali cartelle**? Serve il report: se compare anche un file
  di `cypress/e2e/`, allora Sonar non riconosce `.should()` e cambia lo scopo del lavoro (`F5`).

### Ignoto

- **`D3`** — un `sonar-project.properties` nel repository viene **letto**, dato che il workflow passa
  già `-Dsonar.sources=.` da riga di comando? Verifica: il numero di file analizzati nel report, prima
  e dopo. È la stessa verifica che `BDB17` chiedeva.
- **`D5`** — il gate è rosso **solo** per questa regola, o `S2699` è la voce più visibile di un elenco
  più lungo? Se ci fosse dell'altro, il deploy resterebbe fermo anche a lavoro finito.

## 5. Consigli

- **`D1` → (a), e (b) solo se il report lo impone.** `e2e-bk` è codice generato da Cypress, non
  scritto qui, e non viene eseguito: un gate che si blocca su di esso sta misurando la libreria. È
  anche la strada che il developer aveva già indicato il 2026-08-12. Il punto ripetuto resta nel piano
  perché è stato chiesto, ma con lo stato che dipende da questa risposta.
- **`D2` → guardare il report prima di scrivere codice.** È la differenza fra 11 modifiche in codice
  morto e 8 modifiche nei test veri, che sono le uniche che valgono qualcosa.
- **`D3` → verificarlo al primo scan**, non dopo. Se il file non venisse letto, l'esclusione va
  chiesta al server o al workflow — e il workflow oggi non si tocca.
- **`D4` → un'asserzione vera anche nei test morti**, se si va di (b): il giorno che quella cartella
  tornasse viva, `expect(true).to.be.true` sarebbe un test che passa sempre.
- **`D5` → chiedere il gate completo.** Chiudere questa regola e trovare il deploy ancora fermo
  sarebbe il modo peggiore di scoprire che l'elenco era più lungo.
