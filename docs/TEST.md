# Come si eseguono i test

Due famiglie, due ambienti diversi. **I test PHP girano dentro il container, i test E2E fuori** — e
il perché sta in fondo, perché è il motivo per cui questo documento esiste.

Prerequisito comune: l'ambiente preparato secondo [SETUP.md](SETUP.md), credenziali E2E comprese.

---

## Test PHP (PHPUnit) — nel **suo** container

```sh
docker compose -f docker-compose.test.yml run --rm --build test
docker compose -f docker-compose.test.yml run --rm test php artisan test --filter=AuditList
```

Un ambiente separato da quello di sviluppo (`Dockerfile.test` + `docker-compose.test.yml`), e la
separazione è il punto: **non tocca** il database `idp_develop`, **non** legge come autorevole né
riscrive il `.env` dell'host, **non** tocca `bootstrap/cache/` — la config cache è deviata su
`/tmp/config-test.php`. Il database `idp_test` lo crea l'entrypoint da sé, con
`CREATE DATABASE IF NOT EXISTS`, a ogni avvio.

Il codice si monta, quindi non serve ricostruire l'immagine a ogni modifica; `--build` serve solo
quando cambia `Dockerfile.test`.

### I due modi

| Modo | Database | Quando |
|---|---|---|
| **1 — sqlite in memoria** | `:memory:` | il più veloce, per tutto ciò che non dipende da MariaDB. È il predefinito di `phpunit.xml` |
| **2 — MariaDB** (quello che usa `docker-compose.test.yml`) | `idp_test` | quando un test deve provare qualcosa che sqlite **non riproduce**: collation, indici veri, tipi JSON, comportamento dei lock, `information_schema` |

Il modo si scelgono con le variabili d'ambiente, che **vincono** su `phpunit.xml` — PHPUnit le imposta
prima che Laravel legga i file `.env`, e il caricatore di Laravel non sovrascrive ciò che c'è già
(verificato il 2026-08-12). Per forzare il modo 1 dentro l'ambiente di test:

```sh
docker compose -f docker-compose.test.yml run --rm \
    -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: test
```

Il modello delle variabili è [.env.test.example](../.env.test.example), che `Dockerfile.test` copia
dentro l'immagine — **non** sopra il `.env` dell'host.

### Il guardiano che impedisce di cancellare il database sbagliato

`tests/TestCase.php` legge la configurazione **risolta a runtime** e **aborta** se il database in uso
non è fra quelli di `TEST_ALLOWED_DATABASES` (`:memory:` e `idp_test`).

Sta in `setUpTraits()` e non in `setUp()`, e la differenza è tutto: `RefreshDatabase` agisce dentro
`setUpTraits()`, quindi un controllo dopo `parent::setUp()` parlerebbe **a database già ricreato**.
Provato nei due versi — con un database non consentito la suite si ferma **senza** che nessuna
tabella venga creata; con `idp_test` passa.

Serve anche ora che l'ambiente è separato: protegge chi lancerà la suite nel modo sbagliato comunque,
per esempio dentro `idp_app_2`.

---

## Test E2E (Cypress) — **fuori** dal container

Serve Node sulla macchina locale. Cypress 15 richiede Node `^20.1.0 || ^22.0.0 || >=24.0.0`
(`node_modules/cypress/package.json`, campo `engines`).

```sh
# una volta sola
npm install

# l'applicazione deve rispondere: docker compose up -d

npm run cy:run                                              # tutti i test, senza interfaccia
npm run cy:run -- --spec cypress/e2e/auth/login.cy.js       # una specifica sola
npm run cy:open                                             # interfaccia grafica
```

`baseUrl` è `http://localhost:8001` (`cypress.config.js`), che è la porta pubblicata dal
`docker-compose.yml` verso il container. **Da fuori funziona; da dentro no** — vedi sotto.

### Prima di ogni esecuzione

Le credenziali dei test vengono da `cypress.env.json`, che contiene le credenziali di utenti che
devono **già esistere** nel database. Se il login fallisce, quasi sempre è questo:

```sh
docker exec idp_app_2 ./scripts/prepare-e2e-credentials.sh
```

Genera password nuove, crea `e2e.admin` ed `e2e.user` e riscrive `cypress.env.json`. Il file è
condiviso col disco locale dal volume del `docker-compose.yml`, quindi Cypress lo vede da fuori.

---

## Perché i test E2E non girano dentro il container

Non è una preferenza: **oggi non funzionerebbero**, e conviene sapere cosa manca prima di provarci.

| Cosa serve a Cypress | Stato nell'immagine |
|---|---|
| Node ≥ 20.1 | **c'è** — `php:8.2-fpm` è basata su Debian trixie, il cui pacchetto `nodejs` è 20.19 |
| il pacchetto npm `cypress` | **c'è** — il `Dockerfile` esegue `npm install`, che installa anche le dipendenze di sviluppo |
| `libnss3`, `libgbm1`, `libxss1`, `libasound2`, `libxtst6`, `libgtk-3-0`, `libnotify4` | **nessuna presente** |
| `xvfb` o `xauth`, per un browser senza schermo | **assenti** |
| l'applicazione su `localhost:8001` | **no**: dentro il container nginx è sulla porta **80**. La 8001 esiste solo sull'host |

Le prime due righe ingannano — sembra che ci sia quasi tutto. Ma senza le librerie grafiche il
binario di Cypress non parte, e senza `xvfb` non avrebbe dove disegnare. Verificato con
`dpkg -s` sull'immagine base: nessuno dei sette pacchetti è installato.

### La soluzione non è aggiungerle: è un container suo

Installare quelle librerie più `xvfb` nel `Dockerfile` le farebbe finire **anche nell'immagine di
produzione**, per una cosa che in produzione non serve mai. L'immagine `cypress/included:15.15.0` —
esattamente la versione dichiarata da `package.json` — porta già browser, librerie e binario.

Il lavoro è inquadrato in
[docs/task/todo/20260812-e2e-test-container/](task/todo/20260812-e2e-test-container/): un servizio
dietro un profilo del `docker-compose.yml`, sulla stessa rete, con `CYPRESS_BASE_URL=http://app` —
perché fra container ci si raggiunge per **nome del servizio sulla porta 80**, non su `localhost:8001`.
Quando quel task è chiuso, questa sezione diventa un terzo modo di eseguire i test e questo documento
va aggiornato (`TEC07`).

I test **nella pipeline** sono un'altra cosa ancora, e sono fermi per decisione del developer:
[docs/task/backlog/20260812-pipeline-tests/](task/backlog/20260812-pipeline-tests/).

> **Nota su cosa c'è nell'immagine.** `npm install` nel `Dockerfile` non distingue fra dipendenze di
> sviluppo e di produzione, quindi scarica anche il **binario di Cypress** — circa 1,5 GB di cache,
> misurati con `du -sh ~/.cache/Cypress` — dentro un'immagine dove non può funzionare. È registrato
> come `BDB22` in [backlog.md](task/backlog/backlog.md).
