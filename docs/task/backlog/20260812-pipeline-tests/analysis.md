# Analisi — portare i test nella pipeline di deploy

**Identificatori**: `BPT` = task pipeline-tests

Stato: **backlog** — inquadrato, non avviato · Data: 2026-08-12

## 1. Obiettivo

Far girare i test **dentro la pipeline**, in modo che un test rosso fermi il deploy invece di essere
scoperto dopo.

Nasce come raccolta: il developer ha deciso il 2026-08-12 di **non toccare la pipeline per ora**, e
questo task diventa la casella unica di tutto ciò che modifica i workflow. Vi confluisce `TSA13`
(preparare le credenziali E2E in pipeline) da
[static-analysis-findings-v1](../../done/20260812-static-analysis-findings-v1/action-plan.md), e vi
appartiene anche `TSD07` di
[swagger-deploy-tests](../../todo/20260812-swagger-deploy-tests/action-plan.md), che è rimasto lì e va
deciso (`D1`).

Sta in `backlog/` e non in `todo/` perché **la decisione di partire non è stata presa**: qui c'è
l'inquadramento, non il via.

## 2. Situazione attuale

| # | Fatto | Prova |
|---|---|---|
| F1 | La pipeline ha **due job e nessun test**: `sonar-scan` e `ansible-deploy` | `.github/workflows/deploy-staging.yml`, `deploy-production.yml` |
| F2 | Un cancello che blocca **esiste già**: SonarQube con `-Dsonar.qualitygate.wait=true`. Il meccanismo per fermare un rilascio c'è, e non passa dai test | `deploy-staging.yml:38` |
| F3 | I job girano su runner `self-hosted` dentro il container `dockerhub.newtimegroup.it/ci-runner:v1` | `deploy-staging.yml:10-15` |
| F4 | Il deploy è Ansible: build su `[builder] localhost` (il runner), push su registry, pull sulla macchina di staging raggiunta **per IP** | `ansible/deploy.playbook.yml`, `ansible/builder/tasks/identity-provider.yml`, inventory in `deploy-staging.yml` |
| F5 | I test PHP usano **sqlite in memoria**: nessun MariaDB necessario | `phpunit.xml:21-22` |
| F6 | L'immagine finale è costruita con `composer install --no-dev`: **PHPUnit non c'è dentro** | `Dockerfile:43`; `composer.json:29-38` |
| F7 | I test PHP esistenti sono **due `ExampleTest`** generati dallo scaffolding | `tests/Unit/ExampleTest.php`, `tests/Feature/ExampleTest.php` |
| F8 | Cypress punta a `http://localhost:8001`: gli E2E hanno bisogno di **un'applicazione viva** a quell'indirizzo | `cypress.config.js:32` (`baseUrl`) |
| F9 | La porta 8001 è quella del `docker compose` locale, che avvia app, MariaDB e Mailpit | `docker-compose.yml` |
| F10 | Gli E2E accedono con credenziali lette da `cypress.env.json`, cioè da utenti che devono **già esistere** nel database | `cypress/e2e/auth/login.cy.js:36-38` |
| F11 | `package.json` ha `cy:run` ma **nessuno script `test`** per il PHP | `package.json:11-12` |

> **Non ho verificato niente sull'ambiente di CI**: non ho accesso all'immagine `ci-runner:v1` né al
> runner. Cosa contenga — Docker, un browser, PHP, `pdo_sqlite` — è ignoto, ed è il § 4.

### Dipendenze e breaking change

- **Da qui in poi un test rosso ferma un rilascio.** È lo scopo, ma cambia il modo di lavorare di chi
  spinge sul ramo, e va detto prima che accada la prima volta.
- **I due tipi di test hanno esigenze opposte** ed è il vincolo che struttura tutto (§ 3): i test PHP
  non hanno bisogno di niente, gli E2E hanno bisogno di **tutto** — applicazione, database, browser.
- **`TSD06`** (generare Swagger nel Dockerfile) **non appartiene a questo task**: tocca il Dockerfile,
  non i workflow, e il developer ha escluso la pipeline, non l'immagine.

## 3. Analisi

### Due famiglie di test, due problemi diversi

**I test PHP non hanno ostacoli.** `F5` dice che girano su sqlite in memoria; i test di Laravel
attraversano lo stack HTTP dentro il processo, senza socket. Nessun database esterno, nessuna rete,
nessun indirizzo. L'unico requisito è un ambiente con PHP, le dipendenze **complete** (`F6`: nella
immagine finale non ci sono) e `pdo_sqlite`. Un job che fa checkout, `composer install` e
`php artisan test` è tutto ciò che serve — ed è la metà che si può fare subito.

**Gli E2E sono un problema di ambiente, non di test.** `F8` e `F10`: Cypress guida un browser vero
contro un'applicazione viva su `localhost:8001`, con utenti che devono esistere nel database. Quindi
prima dei test bisogna **avere un ambiente completo**, e le strade sono tre:

1. **`docker compose up` dentro il job**, contro l'applicazione appena costruita. È l'unica che
   verifica *prima* del deploy — quindi l'unica che rispetta l'obiettivo. Costa: serve Docker dentro
   il runner (`F3`, ignoto), serve tempo di avvio, e serve che il `docker-compose.yml` locale sia
   utilizzabile in CI o ne nasca uno per la CI.
2. **E2E dopo il deploy, contro staging.** Facile da montare — l'ambiente esiste già — ma **non ferma
   niente**: constata a cose fatte. È lo smoke test di `BDB18`, che è un lavoro utile ma diverso.
3. **Un ambiente effimero dedicato** sollevato dal runner. È la (1) con più governo e più costo.

La (1) è quella coerente con l'obiettivo, e la (2) va aperta comunque perché copre un rischio che la
(1) non copre: che l'ambiente reale sia rotto per ragioni che il build non vede.

**Da qui la scomposizione**: i test PHP e gli E2E **non sono lo stesso lavoro** e non vanno approvati
insieme. Il primo è un job di dieci righe; il secondo è una decisione sull'infrastruttura di CI.
Metterli nella stessa onda significherebbe tenere fermo il primo aspettando il secondo.

### Quanto vale oggi un job di test

Poco, ed è onesto dirlo: `F7` — i test PHP sono due `ExampleTest` generati dallo scaffolding. Un job
che li esegue non protegge da niente. **Il valore non è nel job, è nel fatto che esista prima dei
test**: se il cancello arriva dopo, ogni test scritto nel frattempo è verde per definizione e nessuno
sa se lo sarebbe stato. I test che gli danno senso sono già pianificati altrove — `TSD02`–`TSD05`
(Swagger), `TCC01`–`TCC02` (middleware e lista audit) — e quello è il momento in cui questo task
smette di essere teorico.

### Il cancello esiste già, e non è dove uno se lo aspetta

`F2`: SonarQube blocca il deploy da oggi. Vuol dire che la pipeline **sa già fermarsi**, e che il
lavoro qui non è costruire un meccanismo ma aggiungere un secondo criterio a uno che c'è. Cambia
anche l'ordine: due cancelli in sequenza si mettono col più veloce davanti (`D4`).

### Rapporto con gli altri task

`TSD07` — «job di test nella pipeline» — è nato dentro il task Swagger e **descrive esattamente
questo lavoro**. Due punti in due task che modificano gli stessi due file sono due verità che
divergono: o `TSD07` si sposta qui, o questo task lo cita e non lo duplica. È `D1`, e va deciso prima
di scrivere una riga di YAML.

## 4. Da decidere

### Vincoli

- **D1** — `TSD07` (il job di test, in `swagger-deploy-tests`) si **sposta qui** o resta lì? Restando
  lì, la decisione di non toccare la pipeline vale per un task e non per l'altro, e il primo che parte
  scrive il job che l'altro dà per scritto.
- **D2** — cosa contiene `ci-runner:v1` (`F3`)? Servono almeno: PHP con `pdo_sqlite` e Composer per i
  test PHP; Docker (o accesso al socket) e un browser per gli E2E. **Senza questa risposta non si può
  scrivere nessun job**, e nemmeno stimarne il costo.

### Conflitti

- **D3** — gli E2E girano **prima** del deploy contro un ambiente effimero (ferma il rilascio, costa
  infrastruttura) o **dopo** contro staging (facile, non ferma niente)? Sono due lavori diversi con
  due nomi diversi, e vale la pena non chiamarli entrambi «test E2E in pipeline».
- **D5** — un test rosso ferma **anche la produzione**, o solo staging? Fermare la produzione è il
  punto; ma è la decisione che si vorrebbe aver preso deliberatamente la prima volta che succede.

### Ignoto

- **D4** — l'ordine dei cancelli: test prima di SonarQube o dopo? I test falliscono in secondi, il
  quality gate aspetta minuti (`F2`). Ma non so se l'ordine attuale risponda a un vincolo che non
  vedo.
- **D6** — esiste un ambiente di CI dove far girare MariaDB, o il runner è isolato dai database come
  il container di build? La domanda era già stata posta per i test Swagger, dove non serviva: **qui
  serve davvero**, perché gli E2E non possono usare sqlite in memoria.

## 5. Consigli

| Domanda | Raccomandazione |
|---|---|
| **D1** | **Spostare `TSD07` qui**, e lasciare in `swagger-deploy-tests` solo i test e la generazione nel Dockerfile — che non toccano la pipeline e possono procedere subito. Così la decisione «per ora la pipeline non si tocca» ha una sola casella, e si vede tutta insieme. |
| **D2** | Primo passo assoluto: un job usa-e-getta che stampa `php -v`, `php -m`, `composer -V`, `docker -v`, `node -v`. Cinque righe, un'esecuzione, e tutto il resto del piano diventa scrivibile invece che immaginato. |
| **D3** | **Entrambi, con nomi diversi.** Prima del deploy i test PHP, che non hanno ostacoli; dopo il deploy uno smoke test contro staging (`BDB18`). Gli E2E completi prima del deploy sono la cosa giusta ma la più cara: si aprono quando `D2` dice che il runner può ospitarli. |
| **D4** | Test **prima** di Sonar: falliscono in secondi contro i minuti del quality gate. Da confermare con chi ha scritto il workflow. |
| **D5** | Su **entrambi**. Un cancello che vale solo su staging insegna a saltarlo spingendo direttamente sul ramo di produzione. |
| **D6** | Rientra in `D2`. Se il runner ha Docker, MariaDB in CI è un servizio in più nel compose; se non ce l'ha, gli E2E prima del deploy non sono possibili e la strada è la (2) del § 3. |

Il piano: [action-plan.md](./action-plan.md).
