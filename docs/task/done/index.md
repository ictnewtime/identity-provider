# Task chiusi

Un task chiuso **non si cancella**: si sposta qui da `todo/` con `mv`, e prende una riga in questa
tabella. Resta come storia della decisione — il piano dice cosa si è scelto e perché, e sei mesi dopo
è l'unica cosa che lo dice.

`docs/task/done/` è una **zona chiusa** in lettura: non si apre lavorando, solo quando si ricostruisce
un perché.

| Task | Obiettivo | Sigla | Chiuso il |
|---|---|---|---|
| [./20260812-static-analysis-findings-v1/](./20260812-static-analysis-findings-v1/) | Rilievi SonarQube su frontend Vue e test E2E | `TSA` | 2026-08-12 |

## `20260812-static-analysis-findings-v1` — com'è finita

Non tutti i punti sono stati fatti, ed è il caso normale: quello che conta è che ognuno abbia un esito
scritto. **Otto chiusi, sei scartati, uno spostato.**

**L'obiettivo è raggiunto**: nessuna password letterale resta in `database/seeders/` né in
`cypress/e2e/`, verificato con `grep`. Le credenziali E2E ora si **generano** — `TSA10`,
`scripts/prepare-e2e-credentials.sh` — invece di essere custodite, e `cypress.env.json` non è più
tracciato da git (`TSA14`).

**Cosa è stato scartato, e perché** — è la parte che non si ricostruisce dal codice:

| Punto | Perché |
|---|---|
| `TSA02` | rotazione delle credenziali: **non serve**, erano dummy e solo locali (`D2`) |
| `TSA05`, `TSA05b` | togliere `autocomplete="new-password"`: sarebbe stata una **regressione** — il motivo sta in [/docs/doc-code-guide-line.md](/docs/doc-code-guide-line.md) |
| `TSA06` | soppressione dei falsi positivi in SonarQube: la configurazione vive **fuori dal repo** |
| `TSA07` | cancellare `cypress/e2e-bk/`: si tiene (`D3`) |
| `TSA09` | ESLint nel repo: niente nuove dipendenze di sviluppo (`D5`) |

**`TSA13` è stato spostato**, non scartato: eseguire lo script in pipeline è ora `BPT03` in
[../backlog/20260812-pipeline-tests/](../backlog/20260812-pipeline-tests/action-plan.md), fermo per
decisione del developer.

**Quattro punti sono chiusi con la verifica delegata** — `TSA10a`, `TSA11`, `TSA12`, `TSA03`: la loro
prova è un'esecuzione di Cypress, che oggi non è ripetibile, ed è il `TEC04` di
[../todo/20260812-e2e-test-container/](../todo/20260812-e2e-test-container/action-plan.md). **Se
quella li smentisce, il task torna in `todo/`**: un task chiuso non è un task immune.

**Cosa resta aperto altrove**: `VDF04` (l'`autocomplete` manca su tre form) e `VDF08` (la password del
seeder è fuori dal codice, ma il valore vecchio resta nella storia git) in
[../vulnerability/vulnerability.md](../vulnerability/vulnerability.md); `BDB24` e `BDB25` — nessun
runner JS e `npm run build` non eseguibile in locale — in
[../backlog/backlog.md](../backlog/backlog.md).
