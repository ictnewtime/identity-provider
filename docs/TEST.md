# Come si eseguono i test

Due famiglie di test, ognuna con **un comando** e **un container usa-e-getta**. Nessuna delle due
tocca il database di sviluppo (`idp_develop`) o l'applicazione su `:8001`, quindi si possono lanciare
quando si vuole, senza chiedere a nessuno.

| Famiglia | Comando | Database | Cosa prova |
|---|---|---|---|
| **backend** (PHPUnit) | `./scripts/run-test-backend.sh` | sqlite **in memoria**, dentro il processo di PHP | il codice PHP: controller, servizi, regole |
| **E2E** (Playwright) | `./scripts/run-test-e2e.sh` | sqlite **in un file in `/tmp`** del container | l'applicazione intera, usata da un browser vero come farebbe una persona |

In tutti e due i casi il database nasce con il container e sparisce con lui. Non c'è niente da
preparare prima, e niente da pulire dopo.

---

## Test di backend

```sh
./scripts/run-test-backend.sh                    # tutta la suite
./scripts/run-test-backend.sh --filter=Audit     # solo i test il cui nome contiene "Audit"
```

Lo script costruisce l'immagine (`Dockerfile.test.backend`) ed esegue `php artisan test` passando gli
argomenti che riceve. Il codice viene **montato** nel container, quindi una modifica si prova subito,
senza ricostruire niente.

**Le credenziali le crea il test stesso.** Un test che ha bisogno di una password, per esempio
`DatabaseSeederTest` che crea un amministratore, se la genera da solo: nessuna variabile da
impostare, nessun file da tenere segreto.

**Il container gira come te e non sporca l'albero.** `--user "$(id -u):$(id -g)"` fa sì che i file
creati dal container siano tuoi e non di root. Quello che la suite scriverebbe (log, cache dei
risultati, cache della configurazione e delle rotte) finisce in `/tmp` del container.

**Se hai cambiato una rotta** e la provi sull'applicazione di sviluppo, svuota prima la cache delle
rotte: con `bootstrap/cache/routes-v7.php` presente, Laravel ignora i file in `routes/`.

```sh
docker exec idp_app_2 php artisan route:clear
```

I test di backend questo problema non ce l'hanno: usano una cache delle rotte loro, in `/tmp`.

Configurazione: `Dockerfile.test.backend` + [.env.test.backend.example](../.env.test.backend.example),
copiato **dentro l'immagine** e mai sopra il `.env` dell'host.

---

## Test E2E

```sh
./scripts/run-test-e2e.sh                                  # tutti
./scripts/run-test-e2e.sh tests/e2e/auth                   # una cartella o un file
./scripts/run-test-e2e.sh --grep "logout"                  # solo i test con "logout" nel nome
```

Lo script costruisce l'immagine (`Dockerfile.test.e2e`) e avvia **un solo container**, che contiene
tutto:

1. **le credenziali**: `APP_KEY`, `JWT_SECRET`, le chiavi che firmano i token e le password degli
   utenti di prova vengono generate all'avvio (`docker/test/e2e-entrypoint.sh`) e valgono solo per
   quel container;
2. **il database**: un file sqlite in `/tmp`, creato da zero con migrazioni e dati di partenza
   (`docker/test/e2e-reset-db.sh`). Gli utenti di prova li crea `E2EUserSeeder`: `e2e.admin`, con il
   ruolo admin, ed `e2e.user`, senza ruoli;
3. **l'applicazione**: `php artisan serve` su `http://localhost:8000`, con gli asset già compilati;
4. **Chromium e Playwright**, che eseguono i test in `tests/e2e/`.

Alla fine il container viene eliminato (`--rm`) e con lui il database e le credenziali. Lo script
esce con lo stesso esito dei test: 0 se passano, diverso da 0 se no.

**Il codice viene copiato nell'immagine, non montato.** Così il container non legge il tuo `.env`,
le tue chiavi o la tua cache, e non scrive niente nel tuo albero. Il prezzo: dopo una modifica
l'immagine si ricostruisce, e lo script lo fa da solo. Gli strati delle dipendenze restano in
cache, quindi di solito servono pochi secondi.

**Se un test fallisce**, lo script stampa gli errori dell'applicazione e salva in `e2e-report/`
(ignorata da git) screenshot, traccia e rapporto HTML:

```sh
npx playwright show-trace e2e-report/results/<nome-del-test>/trace.zip
npx playwright show-report e2e-report/html
```

### Come sono scritti i test

| Cartella | Cosa prova |
|---|---|
| `tests/e2e/auth/` | login sbagliato, login e logout dell'amministratore, utente senza ruoli rimandato ad "Accesso Negato" |
| `tests/e2e/user/` | crea, cerca, modifica, elimina e ripristina un utente; ricerca con lettere accentate |
| `tests/e2e/i18n/` | gli stessi percorsi principali con il browser in inglese |
| `tests/e2e/support/` | gli aiuti comuni: `login()`, `searchUser()`, `resetDatabase()`, i testi dell'interfaccia nelle due lingue |

Le regole che tengono i test affidabili:

- **ogni test crea i propri dati.** Nessun test modifica l'amministratore o un dato che un altro test
  si aspetta di trovare;
- **un test che ha bisogno di partire pulito azzera il database** con `resetDatabase()`. Lo fa per
  esempio il CRUD utenti, i cui passi dipendono l'uno dall'altro e girano in ordine. Funziona perché
  Playwright e l'applicazione stanno nello stesso container, e lo script rifiuta qualunque database
  che non sia il sqlite in `/tmp`;
- **si aspetta la risposta, non un tempo fisso.** Dopo una ricerca il test aspetta la risposta di
  `/admin/v1/users` con quel testo, non qualche millisecondo;
- **gli elementi si trovano per `data-testid`** (es. `edit-user-<username>`) o per testo e ruolo, non
  per classi CSS che cambiano con lo stile;
- **la lingua è italiana di partenza** (`Accept-Language: it`). I test in `i18n/` chiedono `en` e
  verificano messaggi del server e testi dell'interfaccia in inglese.

### Il limite di sqlite

Gli E2E girano su sqlite perché è deciso che stiano in un container solo. In produzione però c'è
MariaDB, e i due motori **cercano in modo diverso** sulle lettere accentate:

```
LIKE '%MARIÒ%'  su  'mariò'
  sqlite                      -> 0 righe   (maiuscole e minuscole uguali solo per a-z)
  MariaDB utf8mb4_unicode_ci  -> 1 riga    (misurato il 2026-08-12)
```

Per questo in `tests/e2e/user/search-accented.spec.js` la ricerca in MAIUSCOLO è segnata `fixme`:
su sqlite fallirebbe per colpa del motore, non del codice. Torna un test vero il giorno che gli E2E
girano su MariaDB.

---

## Il guardiano che impedisce di cancellare il database sbagliato

`tests/TestCase.php` legge la configurazione **risolta a runtime** e **si ferma** se il database in
uso non è fra quelli di `TEST_ALLOWED_DATABASES` (`:memory:` e `idp_test`).

Sta in `setUpTraits()` e non in `setUp()`, e la differenza conta: `RefreshDatabase` ricrea il
database dentro `setUpTraits()`, quindi un controllo messo dopo `parent::setUp()` arriverebbe **a
database già cancellato**.

> ⚠️ **Non lanciare `php artisan test` dentro `idp_app_2`.** Con una config cache presente ignora
> `phpunit.xml` e punterebbe al database dell'applicazione. Il guardiano lo ferma prima che faccia
> danni, ma la strada giusta è `./scripts/run-test-backend.sh`.
> Difetto [`VDF11`](task/vulnerability/vulnerability.md).

I test **nella pipeline** sono un'altra cosa, e sono fermi per decisione del developer:
[pipeline-tests](task/backlog/20260812-pipeline-tests/action-plan.md).
