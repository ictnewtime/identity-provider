# Piano d'azione — portare i test nella pipeline di deploy

Sigla dichiarata dall'analisi: `BPT` — qui non si ridichiara.

Stato: **backlog** — nessun punto è approvabile finché il developer non decide di partire · Data:
2026-08-12 · Analisi: [analysis.md](./analysis.md)

Fatti e alternative stanno nell'analisi: `F…` e `D…` puntano lì. V — `auto`: lo stabilisce un
comando · `man`: lo legge una persona.

> **Perché tutti i punti sono `da approvare` e nessuno è pronto**: il developer ha deciso il
> 2026-08-12 di non toccare la pipeline per ora. Questo piano esiste perché la decisione sia
> *rimandata*, non persa — e perché quando si riprenderà, l'inquadramento ci sia già.

## Onda 1 — sapere dove si sta scrivendo

Nessuna riga di YAML prima di questo. Oggi il piano è scritto su un ambiente di CI che non ho potuto
guardare (`F3`), e ogni punto sotto ne dipende.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| BPT01 | da approvare | **`D2`, `D6`** — un job usa-e-getta che stampa `php -v`, `php -m`, `composer -V`, `docker -v`, `node -v` dentro `ci-runner:v1`. Cinque righe e un'esecuzione: da lì si sa se i test PHP possono girare (serve `pdo_sqlite`), se gli E2E sono possibili (serve Docker e un browser) e se un MariaDB in CI è alla portata | workflow temporaneo, da rimuovere dopo | nullo | auto | l'esecuzione stampa l'elenco, e le risposte finiscono nel § 2 dell'analisi |
| BPT02 | da approvare | **`D1`** — decidere dove vive il job di test: `TSD07` di [swagger-deploy-tests](../../todo/20260812-swagger-deploy-tests/action-plan.md) descrive **lo stesso lavoro** di questo task. O si sposta qui, o questo piano lo cita senza duplicarlo | `docs/task/todo/20260812-swagger-deploy-tests/action-plan.md` | basso, ma se non si fa restano **due punti che scrivono gli stessi due file** | man | uno solo dei due task contiene il punto che modifica i workflow |

## Onda 2 — i test che non hanno ostacoli

Solo PHP. Nessun database, nessuna rete, nessun indirizzo: `phpunit.xml` è già su sqlite in memoria
(`F5`) e i test di Laravel non aprono socket. È la metà del lavoro che si può fare appena `BPT01`
risponde.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| BPT04 | da approvare | Job `tests` che fa checkout, `composer install` (**con** le dipendenze di sviluppo: nell'immagine finale PHPUnit non c'è, `F6`) ed esegue `php artisan test`. Blocca il deploy se fallisce | `.github/workflows/deploy-staging.yml`, `deploy-production.yml` | medio — da qui un test rosso ferma un rilascio, ed è lo scopo | man | una esecuzione con un test volutamente rosso **non arriva** al job di deploy. Va provato una volta, di proposito |
| BPT05 | da approvare | **`D4`** — collocare il job **prima** di `sonar-scan`: i test falliscono in secondi, il quality gate aspetta minuti (`F2`). Da confermare con chi ha scritto il workflow | stessi file | basso | man | in un'esecuzione fallita, il tempo trascorso prima del rosso |
| BPT06 | da approvare | **`D5`** — applicare il cancello **anche alla produzione**, non solo a staging: un cancello che vale su un ramo solo insegna a spingere sull'altro | `deploy-production.yml` | medio | man | i due workflow hanno lo stesso job di test |

## Onda 3 — gli E2E, che sono un problema di ambiente

**Subordinata a `BPT01` e a `D3`.** Cypress guida un browser vero contro un'applicazione viva su
`localhost:8001` (`F8`), con utenti che devono già esistere nel database (`F10`): prima dei test
serve **un ambiente completo**, e se il runner non può ospitarlo questa onda non si fa — si fa lo
smoke test dopo il deploy, che è `BDB18` e non è la stessa cosa.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| BPT07 | da approvare | Sollevare l'ambiente nel job — applicazione, MariaDB — contro l'immagine appena costruita, e attendere che risponda su `:8001` prima di procedere | i workflow, più un `docker-compose.ci.yml` se quello locale non è riusabile | **alto** — è il punto che decide se l'onda è fattibile | man | il job raggiunge `http://localhost:8001/up` con 200 dentro la CI |
| BPT03 | da approvare | **Migrato da `TSA13`** ([v1](../../todo/20260812-static-analysis-findings-v1/action-plan.md), spostato il 2026-08-12). Eseguire `scripts/prepare-e2e-credentials.sh` **nella pipeline**, prima degli E2E: genera le password, scrive `cypress.env.json`, semina `e2e.admin` ed `e2e.user`. Nessun segreto da custodire, perché non esistono prima dell'esecuzione | i workflow | medio | man | i test E2E accedono con credenziali generate in **quella** esecuzione, e nessun segreto compare nei log |
| BPT08 | da approvare | Eseguire `npm run cy:run` e bloccare il deploy se fallisce. Ultimo: senza `BPT07` non ha dove girare e senza `BPT03` non ha con cosa accedere | i workflow | medio | man | un test E2E volutamente rosso ferma il deploy |

## Cosa questo piano non copre

- **Lo smoke test dopo il deploy** contro l'ambiente reale (`BDB18`): è l'altra metà, e copre un
  rischio che nessuna onda qui copre — che staging sia rotto per ragioni che il build non vede. È
  anche l'unico posto dove il problema dell'IP senza nome di dominio esiste davvero (`F4`).
- **Scrivere i test.** Questo piano costruisce il cancello; ciò che ci passa dentro sta altrove:
  `TSD02`–`TSD05` (Swagger), `TCC01`–`TCC02` (middleware e lista audit). Oggi i test PHP sono **due
  `ExampleTest`** generati dallo scaffolding (`F7`): un job che li esegue non protegge da niente, e il
  suo valore è solo esistere prima che i test veri arrivino.
- **`TSD06`** (generare Swagger nel Dockerfile): tocca l'immagine, non i workflow. Resta nel suo task
  e può procedere.
- **La configurazione di SonarQube** (esclusioni, soppressioni): vive fuori da questo repo — `BDB17`.
