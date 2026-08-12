# Piano d'azione — test di Swagger che bloccano la pipeline prima del deploy

Sigla dichiarata dall'analisi: `TSD` — qui non si ridichiara.

Stato: **da approvare** · Data: 2026-08-12 · Analisi: [analysis.md](./analysis.md)

Fatti e alternative stanno nell'analisi: `F…` e `D…` puntano lì. V — `auto`: lo stabilisce un
comando · `man`: lo legge una persona.

## Onda 1 — l'accertamento su cui poggia tutto

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TSD01 | da approvare | **`D2`, `F14`** — verificare che `pdo_sqlite` sia disponibile nell'ambiente in cui gireranno i test: il Dockerfile installa esplicitamente solo `pdo_mysql`, e `phpunit.xml` è configurato su sqlite in memoria. Se manca, o si installa l'estensione o si cambia la configurazione dei test | nessuno (accertamento); eventualmente `Dockerfile:27-34` | basso in sé, **blocca tutto il resto** | auto | `php -m \| grep -i sqlite` nell'ambiente scelto, e i due `ExampleTest` esistenti che passano: `php artisan test` |

## Onda 2 — i test

Dal più debole al più forte, nell'ordine del § 3 dell'analisi. Ognuno vale da solo: si possono
approvare separatamente.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TSD02 | da approvare | Test di **generazione**: eseguire `l5-swagger:generate` e asserire che **esce 0**, che `storage/api-docs/api-docs.json` esiste, e che è **JSON valido**. È la verifica che oggi manca del tutto: l'entrypoint ingoia il codice d'uscita (`F9`) | `tests/Feature/Swagger/GenerationTest.php` (nuovo) | basso | auto | il test fallisce se si rompe di proposito un'annotazione, e passa sul repo pulito — vanno provate **entrambe** le direzioni |
| TSD03 | da approvare | Test di **struttura**: il JSON è un documento OpenAPI — ha `openapi`, `info`, e `paths` **non vuoto**. Coglie il caso in cui le annotazioni non vengono lette affatto e il file esce con la sola intestazione, che è indistinguibile da un successo per i test più deboli | stesso file | basso | auto | il test fallisce se si punta `annotations` a una cartella vuota |
| TSD04 | da approvare | Test di **contenuto**: quattro o cinque percorsi rappresentativi, uno per controller documentato, sono presenti in `paths` (`D4`: elenco scritto a mano e corto). È il test che coglie la regressione realistica — si tocca un `#[OA\Get]` e spariscono tre rotte senza che niente fallisca | stesso file | basso; il costo è la manutenzione dell'elenco | auto | rimuovendo un'annotazione `OA\Get` da un controller, il test nomina il percorso mancante |
| TSD05 | da approvare | Test **della rotta**: `api/documentation` risponde 200 e la rotta del JSON (`docs`) serve un documento con il tipo di contenuto giusto. È la verifica che si fa oggi a mano nel post-deploy (§2.5), qui automatica — **la più debole delle quattro**, e da sola non prova niente | `tests/Feature/Swagger/RouteTest.php` (nuovo) | basso | auto | il test passa; e con il JSON cancellato la rotta dei documenti **non** deve rispondere 200 |

## Onda 3 — spostare il fallimento prima del deploy

Qui sta il valore vero: i test dell'onda 2 senza questa non fermano niente.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TSD06 | da approvare | **Generare Swagger nel Dockerfile**, dopo `composer install` e prima della fine: il JSON entra in un layer, sopravvive alla ricreazione del container (`F12`) e un fallimento **interrompe il build**, così l'immagine rotta non raggiunge il registry. Il Dockerfile fa già i permessi (`F7`) ed esegue già artisan (`F8`): non serve altro | `Dockerfile` (dopo la riga 79) | medio — cambia il contenuto dell'immagine | auto | build dell'immagine con un'annotazione rotta di proposito: **deve fallire**. Con il repo pulito, `docker run … ls storage/api-docs/api-docs.json` trova il file |
| TSD07 | da approvare | **Job di test nella pipeline**, prima di `sonar-scan` (`D5`), che esegue la suite e **blocca il deploy** se fallisce. Nessun accesso a database né a rete: `phpunit.xml` usa sqlite in memoria (`F5`) e i test di Laravel non aprono socket (§ 3) — è la ragione per cui l'isolamento del container di build non è un ostacolo | `.github/workflows/deploy-staging.yml`, `deploy-production.yml` | medio — da qui in poi un test rosso ferma un rilascio, ed è lo scopo | man | una PR con un test volutamente rosso **non arriva** al job di deploy. Va provato una volta, di proposito |
| TSD08 | da approvare | **`D3`, `F9`** — togliere il `\|\| echo "Generazione Swagger fallita, proseguo comunque."` dall'`entrypoint`, così un fallimento non resta invisibile dietro un `/up` che risponde 200 (`F13`). **Solo dopo `TSD06`**: senza la generazione al build, si legherebbe la partenza del container a un comando che oggi a volte non riesce | `entrypoint.sh:35` | **alto** — un container che non parte è un disservizio; è il punto che richiede la tua decisione esplicita | man | avvio con `storage` non scrivibile: il container si ferma con un messaggio che nomina la causa, invece di partire rotto |

## Onda 4 — allineare la documentazione

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TSD09 | da approvare | Aggiornare il post-deploy: la §2.5 (aprire Swagger a mano) e la §3.1 (rigenerare a mano) descrivono presidi che l'onda 3 sposta prima del deploy. Restano come **diagnosi**, non come verifica di routine | `docs/post-deploy.md` | basso | man | la §2 non chiede più di aprire una pagina per sapere se la generazione è riuscita |

## Cosa questo piano non copre

- **Lo smoke test HTTP dopo il deploy**, contro l'ambiente reale. È l'altra metà, ed è **lì** che il
  problema che descrivevi esiste davvero: il container di build non raggiunge la macchina finale, che
  ha un IP e non un nome di dominio, e le regole di rete sono diverse. Questi test girano prima e
  altrove, quindi non lo risolvono. Aperto in `BDB18`.
- **La persistenza di `storage`** (`F12`): finché non c'è un volume, le chiavi Passport e quelle RS256
  del master token si rigenerano a ogni deploy — che è il vero motivo per cui la §1 del post-deploy è
  obbligatoria. `TSD06` mette al riparo solo il JSON di Swagger, perché quello entra nell'immagine.
  Il resto è un lavoro suo (`BDB19`).
- **Test su ciò che le annotazioni dicono**: `TSD04` verifica che un percorso sia documentato, non che
  la documentazione sia **giusta**. Un `#[OA\Get]` che descrive parametri inesistenti passa.
- **La copertura del resto dell'applicazione**: due `ExampleTest` (`F4`) sono tutto ciò che c'è. Questo
  task aggiunge test su Swagger, non una suite.
