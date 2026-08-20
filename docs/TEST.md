# Come si eseguono i test

**Due famiglie, due ambienti**, e non è simmetria: hanno vincoli opposti. I test di backend non
hanno bisogno di niente che duri, gli E2E hanno bisogno che tutto resti in piedi. È da lì che
discende tutto il resto di questo documento.

| Famiglia | Database | Ambiente | Perché |
|---|---|---|---|
| **backend** (PHPUnit) | sqlite in memoria | `Dockerfile.test.backend`, **senza compose** | il database vive dentro il processo di PHP e muore con lui: nessun servizio deve restare su |
| **E2E** (Cypress) | MariaDB `idp_test` | `docker-compose.test.yml` | un browser vero interroga un'applicazione viva, che ha bisogno di un database per tutta la sessione |

Prerequisito comune: l'ambiente preparato secondo [setup.db.md](setup.db.md) e [SETUP.md](SETUP.md).

---

## Test di backend — sqlite, senza compose

```sh
./scripts/run-test-backend.sh                    # tutta la suite
./scripts/run-test-backend.sh --filter=Audit     # una parte
```

**Basta questo, e non chiede niente.** Lo script prepara l'ambiente, costruisce l'immagine ed esegue i
test passando gli argomenti che riceve. Alla prima esecuzione **genera** il valore delle variabili che
il modello dichiara vuote — oggi una, `SEED_ADMIN_PASSWORD` — e lo scrive in `.env.test.backend`, che
git ignora. Dalla seconda in poi lo riusa: due esecuzioni di fila lavorano sullo stesso ambiente.

**Il container gira come te, e non scrive nell'albero.** `--user "$(id -u):$(id -g)"`: i file che
nascono dentro il container appartengono a chi ha lanciato lo script, non a root — fino al 2026-08-20 ogni
esecuzione ne lasciava indietro, e uno di quelli bloccava `npm run build`. E ciò che la suite scriverebbe
è deviato **fuori** dall'albero: i log su `stderr`, la cache dei risultati in `/tmp` (`phpunit.xml`),
la config cache in `/tmp/config-test.php`. Verificato: dopo un'esecuzione, `find . -newer` non trova
alcun file nuovo. Non è pulizia formale — `storage/logs/laravel.log` è di **www-data**, perché lo scrive
il container dell'applicazione, e un terzo utente che ci scrive dentro non esiste.

Generate e non digitate perché sono **credenziali di prova**: servono a esistere. Il seeder pretende
una password e non ne inventa una; quale sia non interessa a nessuno. Da qui due conseguenze pratiche:
non c'è niente da ricordare, e in CI — dove un terminale non c'è — funziona identico.

Se una variabile è già nell'ambiente, quella vince e non viene scritta nel file:

```sh
SEED_ADMIN_PASSWORD='…' ./scripts/run-test-backend.sh
```

**La preparazione ha un comando suo**, e serve a chi i test li lancia a mano:

```sh
./scripts/setup-env-for-test-backend.sh
```

Da `.env.test.backend.example` produce `.env.test.backend`, generando **solo** le variabili dichiarate
senza valore e **solo** se non le trova già — nell'ambiente o nel file. Non costruisce niente e non
esegue niente: prepara, e si ferma. È la ragione per cui gli script sono due.

### A mano, se serve capire cosa fanno gli script

Prima si prepara l'ambiente — `./scripts/setup-env-for-test-backend.sh`, oppure a mano copiando il
modello e riempiendo le variabili vuote — e poi:

```sh
docker build -f Dockerfile.test.backend -t idp-test-backend .
docker run --rm -v "$PWD":/var/www -e SEED_ADMIN_PASSWORD='…' idp-test-backend
docker run --rm -v "$PWD":/var/www -e SEED_ADMIN_PASSWORD='…' idp-test-backend php artisan test --filter=AuditList
```

Il `-e` non è un dettaglio: **prima va preparato `.env.test.backend`** — si copia
[.env.test.backend.example](../.env.test.backend.example) e si riempiono le variabili dichiarate
**senza valore**, che sono le credenziali. Senza di esse `DatabaseSeederTest` fallisce con un messaggio
che dice quale variabile manca; gli altri test girano ugualmente.

**Perché `-e` e non `--env-file`**: un `--env-file` passerebbe tutto il file locale, compreso
`TEST_ALLOWED_DATABASES` — il guardiano che impedisce alla suite di cancellare `idp_develop`. Un file
locale sbagliato lo disarmerebbe in silenzio. Con `-e` si passano solo le credenziali, e tutto il resto
resta quello dell'immagine.

Questi test non toccano **nessun** database: né `idp_develop` né `idp_test`. Un file compose qui sarebbe
di troppo — un comando basta, ed è la ragione per cui non c'è.

Configurazione: `Dockerfile.test.backend` + [.env.test.backend.example](../.env.test.backend.example),
copiato **dentro l'immagine** e mai sopra il `.env` dell'host. Nel modello le credenziali sono dichiarate
senza valore: i valori arrivano con `-e`, non da quel `COPY`.

---

## Test E2E — MariaDB, con compose

```sh
TEST_UID=$(id -u) TEST_GID=$(id -g) docker compose -f docker-compose.test.yml run --rm --build e2e
```

**Le due variabili servono**, e senza di loro il compose si ferma dicendo cosa scrivere: danno al
container l'utente dell'host, così i file che scrive nell'albero montato sono tuoi e non di root. Non
hanno un valore predefinito di proposito — `1000:1000` sarebbe giusto su una macchina e sbagliato sulla
successiva. I test backend non chiedono niente: lo script lo fa da sé.

Il database `idp_test` lo crea l'entrypoint a ogni avvio (`CREATE DATABASE IF NOT EXISTS`): non c'è
un passo da ricordare. Configurazione: `Dockerfile.test.e2e` +
[.env.test.e2e.example](../.env.test.e2e.example) + `docker-compose.test.yml`.

**Perché MariaDB e non sqlite**, oltre al vincolo di cui sopra: `LIKE '%MARIÒ%'` su `Mariò` trova
**0 righe su sqlite e 1 su MariaDB** con `utf8mb4_unicode_ci` — misurato — e la ricerca di questa
applicazione è fatta di `LIKE`, su nomi che in italiano hanno gli accenti. Un test di ricerca su
sqlite passerebbe senza dire niente su ciò che accade in produzione.

### Finché Cypress non è nell'ambiente: si lancia da fuori

```sh
npm install                                                 # una volta sola
docker compose up -d                                        # l'applicazione deve rispondere
docker exec idp_app_2 ./scripts/prepare-e2e-credentials.sh  # utenti + cypress.env.json

npm run cy:run
npm run cy:run -- --spec cypress/e2e/auth/login.cy.js
npm run cy:open
```

Serve Node sulla macchina locale: Cypress 15 richiede `^20.1.0 || ^22.0.0 || >=24.0.0`
(`node_modules/cypress/package.json`, campo `engines`). `baseUrl` è `http://localhost:8001`, cioè la
porta pubblicata dal `docker-compose.yml`: **da fuori funziona, da dentro no**.

Se il login fallisce, quasi sempre è lo script delle credenziali: `cypress.env.json` contiene le
credenziali di utenti che devono **già esistere**, e lo script crea entrambi.

---

## Il guardiano che impedisce di cancellare il database sbagliato

`tests/TestCase.php` legge la configurazione **risolta a runtime** e **aborta** se il database in uso
non è fra quelli di `TEST_ALLOWED_DATABASES` (`:memory:` e `idp_test`).

Sta in `setUpTraits()` e non in `setUp()`, e la differenza è tutto: `RefreshDatabase` agisce dentro
`setUpTraits()`, quindi un controllo dopo `parent::setUp()` parlerebbe **a database già ricreato**.
Provato nei due versi — con un database non consentito la suite si ferma **senza** che nessuna
tabella venga creata; con `idp_test` passa.

Serve anche ora che gli ambienti sono separati: protegge chi lancerà la suite nel modo sbagliato
comunque.

> ⚠️ **Non lanciare `php artisan test` dentro `idp_app_2`.** Con una config cache presente ignora
> `phpunit.xml` e punterebbe al database dell'applicazione. Non fa più danno — il guardiano ferma
> prima — ma la strada giusta sono i due ambienti qui sopra.
> Difetto [`VDF11`](task/vulnerability/vulnerability.md).

---

## Perché Cypress non è ancora nell'ambiente E2E

Non è una dimenticanza: all'immagine dell'applicazione manca tutto ciò che gli serve, e conviene
saperlo prima di provarci.

| Cosa serve a Cypress | Stato nell'immagine |
|---|---|
| Node ≥ 20.1 | **c'è** — `php:8.2-fpm` è basata su Debian trixie, il cui pacchetto `nodejs` è 20.19 |
| il pacchetto npm `cypress` | **c'è** — il `Dockerfile` esegue `npm install`, che installa anche le dipendenze di sviluppo |
| `libnss3`, `libgbm1`, `libxss1`, `libasound2`, `libxtst6`, `libgtk-3-0`, `libnotify4` | **nessuna presente** |
| `xvfb` o `xauth`, per un browser senza schermo | **assenti** |
| l'applicazione su `localhost:8001` | **no**: dentro un container nginx è sulla porta **80**, e la 8001 esiste solo sull'host |

Le prime due righe ingannano — sembra che ci sia quasi tutto. Ma senza le librerie grafiche il
binario non parte, e senza `xvfb` non avrebbe dove disegnare. Verificato con `dpkg -s`
sull'immagine base: nessuno dei sette pacchetti è installato.

**La soluzione non è aggiungerle**: finirebbero anche nell'immagine di produzione, per una cosa che
in produzione non serve mai. L'immagine ufficiale `cypress/included:15.15.0` — esattamente la
versione dichiarata da `package.json` — porta già browser, librerie e binario. Il lavoro è
[e2e-test-container](task/todo/20260812-e2e-test-container/action-plan.md); quando sarà chiuso, la
sezione «si lancia da fuori» qui sopra diventerà un'alternativa e non un ripiego (`TEC07`).

I test **nella pipeline** sono un'altra cosa ancora, e sono fermi per decisione del developer:
[pipeline-tests](task/backlog/20260812-pipeline-tests/action-plan.md).

> **Nota su cosa c'è nell'immagine di produzione.** `npm install` nel `Dockerfile` non distingue fra
> dipendenze di sviluppo e di produzione, quindi scarica anche il **binario di Cypress** — circa
> 1,5 GB di cache, misurati con `du -sh ~/.cache/Cypress` — dentro un'immagine dove non può
> funzionare. È [`BDB22`](task/backlog/backlog.md).
