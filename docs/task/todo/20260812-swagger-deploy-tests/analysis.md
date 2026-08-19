# Analisi — test di Swagger che bloccano la pipeline prima del deploy

**Identificatori**: `TSD` = task swagger-deploy-tests

Stato: da approvare · Data: 2026-08-12

## 1. Obiettivo

Avere test che verificano che la documentazione OpenAPI sia **davvero generata e valida** — non che
la pagina risponda — e che **fermino la pipeline** prima del deploy quando non lo è.

Perché adesso: oggi la generazione di Swagger può fallire senza che niente se ne accorga (`F9`), e
l'unico presidio è una procedura manuale post-deploy (`docs/post-deploy.md` §3.1) eseguita da una
persona che deve ricordarsene. Un test che gira prima del deploy sostituisce il ricordo.

**La conclusione principale, in anticipo**: i due ostacoli che temevi — il container di build ha un
IP e non un nome di dominio, ed è isolato dal database — **non si applicano a questi test**, perché
il test giusto non passa dalla rete né dal database (§ 3). Quello che invece blocca davvero è un
terzo, che non era nella lista: nell'immagine finale **PHPUnit non c'è** (`F3`).

## 2. Situazione attuale

### La pipeline

| # | Fatto | Prova |
|---|---|---|
| F1 | La pipeline ha **due job e nessun test**: `sonar-scan` e `ansible-deploy` | `.github/workflows/deploy-staging.yml`, `deploy-production.yml` |
| F2 | Il quality gate SonarQube gira con `-Dsonar.qualitygate.wait=true`: un cancello che **blocca già oggi** esiste, ed è quello | `deploy-staging.yml:38` |
| F3 | L'immagine finale è costruita con `composer install --no-dev`, e PHPUnit è in `require-dev`: **nell'immagine PHPUnit non è installato** | `Dockerfile:43`; `composer.json:29-38` |
| F4 | Il progetto ha **due test**, entrambi `ExampleTest` generati dallo scaffolding | `tests/Unit/ExampleTest.php`, `tests/Feature/ExampleTest.php` |
| F5 | La configurazione dei test usa **sqlite in memoria**, non MariaDB | `phpunit.xml:21-22` (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) |
| F6 | Il build gira su `[builder] localhost` (il runner), spinge l'immagine sul registry, e la macchina di staging la scarica: sono **due host diversi**, il secondo raggiunto per IP | `deploy-staging.yml` (inventory), `ansible/deploy.playbook.yml`, `ansible/builder/tasks/identity-provider.yml` |

### Swagger e lo storage

| # | Fatto | Prova |
|---|---|---|
| F7 | Lo `storage` è **già reso scrivibile durante il build**, non a mano: il Dockerfile fa `chown -R www-data:www-data storage && chmod -R 775 storage bootstrap/cache`, e l'`entrypoint.sh` lo rifà a ogni avvio | `Dockerfile:78`; `entrypoint.sh:5-6` |
| F8 | Il build **esegue già `artisan`**: `RUN php artisan storage:link`. Quindi artisan gira nel container di build, senza database | `Dockerfile:79` |
| F9 | `l5-swagger:generate` **non è nel Dockerfile**: gira solo nell'`entrypoint`, a runtime, e il suo fallimento è **ingoiato** — `\|\| echo "Generazione Swagger fallita, proseguo comunque."` | `entrypoint.sh:35` |
| F10 | `generate_always` è `false`: in esercizio il JSON **deve esistere su disco**, non viene rigenerato a ogni richiesta | `config/l5-swagger.php:240` |
| F11 | Il JSON sta in `storage_path('api-docs')/api-docs.json`; l'interfaccia è su `api/documentation` | `config/l5-swagger.php:84`, `:31`, `:15` |
| F12 | Lo `storage` **non è persistito** (nessun volume) e il container è ricreato a ogni deploy: è la ragione per cui esiste la §1 manuale del post-deploy | `docs/post-deploy.md` §1; `ansible/project-deploy/tasks/identity-provider-deploy.yml` (nessun `volumes:`) |
| F13 | L'health check è `/up` e **non sa niente di Swagger**: risponde 200 con la documentazione rotta | `bootstrap/app.php:30` |
| F14 | `pdo_sqlite` **non è fra le estensioni installate esplicitamente** dal Dockerfile (`pdo_mysql`, `mbstring`, `zip`, `exif`, `pcntl`, `gd`, `opcache`). L'immagine `php:8.2-fpm` potrebbe includerlo di suo: **non l'ho verificato** | `Dockerfile:27-34` |

> **Non ho eseguito niente**: i container `idp_*` sono fermi da 11 giorni (`docker ps -a`). Tutti i
> fatti qui sopra vengono dalla lettura dei file, e `F14` resta una domanda proprio per questo.

### Dipendenze e breaking change

- **Aggiungere un job di test alla pipeline non rompe niente**, ma da quel momento **può fermare un
  deploy**: è lo scopo, e va detto prima che accada la prima volta.
- **Generare Swagger nel Dockerfile** (§ 3) cambia il contenuto dell'immagine: il JSON entra in un
  layer e non dipende più dal runtime. È un miglioramento, ma sposta il momento del fallimento dal
  primo avvio al build — cioè lo anticipa, che è quello che vuoi.
- **Togliere il `|| echo` dall'entrypoint** significa che un container con Swagger rotto **non parte
  più**. Va deciso, non fatto di slancio (`D3`): oggi parte e funziona in tutto il resto.

## 3. Analisi

### Il test giusto non passa dalla rete, e questo scioglie due dei tre ostacoli

La richiesta parla di verificare `http[s]://<host>/api/documentation`. Ma un test di Laravel non
chiama un URL: `$this->get("/api/documentation")` fa passare la richiesta per l'intero stack HTTP
**dentro il processo di PHPUnit**, senza socket, senza porta, senza DNS. Quindi il container di build
non ha bisogno di un nome di dominio, non ha bisogno del proprio IP, e non deve raggiungere niente.
L'ostacolo che descrivevi è reale per uno smoke test *dopo* il deploy — ed è il motivo per cui quello
resta dov'è, nel post-deploy — ma non esiste per un test *prima*.

Lo stesso vale per il database: `phpunit.xml` è già configurato su **sqlite in memoria** (`F5`).
Nessuna connessione a MariaDB, nessuna regola di rete da concedere al container di build. E `F8` è la
prova che il pezzo più incerto funziona già: il Dockerfile esegue `php artisan storage:link` durante
il build, quindi il framework si avvia in quel contesto senza database.

Resta un ostacolo vero, e non era nella lista: **PHPUnit non è nell'immagine finale** (`F3`),
perché `composer install --no-dev` esclude `require-dev`. I test quindi **non possono girare
nell'immagine che si spedisce** — e non devono: un'immagine di produzione con dentro PHPUnit e Faker
è più grossa e più esposta. Le due strade sono un **job di CI prima del build** (dipendenze complete,
nessuna modifica al Dockerfile) o un **build multi-stage** in cui uno stage installa anche le
dipendenze di sviluppo, esegue i test e non finisce nell'immagine. La raccomandazione è nel § 5.

### «Non solo che la pagina risponda» — cosa vuol dire in pratica

Che la pagina risponda 200 è la verifica che oggi sta nel post-deploy, e non prova quasi niente:
l'interfaccia Swagger UI si carica anche quando il JSON che dovrebbe mostrare è assente o corrotto,
perché lo carica il browser in una seconda richiesta. La scala delle verifiche, dalla più debole alla
più forte:

1. la rotta `api/documentation` risponde 200 — quello che si fa oggi, a mano;
2. il file `storage/api-docs/api-docs.json` **esiste** dopo la generazione;
3. è **JSON valido** — coglie una generazione interrotta a metà;
4. è un **documento OpenAPI**: ha `openapi`, `info`, `paths` non vuoto — coglie il caso in cui le
   annotazioni non vengono lette affatto e il file esce con la sola intestazione;
5. **contiene i percorsi che ci si aspetta** — coglie l'annotazione rotta di un singolo controller,
   che è la regressione realistica: si tocca un `#[OA\Get]` e spariscono tre rotte dalla
   documentazione senza che niente fallisca;
6. `l5-swagger:generate` **esce 0** — coglie l'errore di parsing che oggi finisce in `|| echo`.

Il valore sta dal punto 4 in su, e il 5 è quello che protegge dal caso vero. Il 6 è la verifica che
la generazione **è avvenuta**, e serve perché tutte le altre passerebbero su un file vecchio.

Alternativa scartata: uno **smoke test HTTP contro l'ambiente di staging dopo il deploy**. È utile e
andrebbe aggiunto comunque, ma non è quello che hai chiesto — non ferma la pipeline, la constata a
cose fatte. Nota che è *quello* il caso in cui l'ostacolo dell'IP senza nome di dominio esiste
davvero, ed è un problema diverso da risolvere (`BDB18`).

### Il difetto che questo task porta a galla

`F9` è la cosa peggiore di tutto il quadro: `php artisan l5-swagger:generate || echo "Generazione
Swagger fallita, proseguo comunque."`. Il container parte, `/up` risponde 200 (`F13`), Ansible
dichiara il deploy riuscito, e la documentazione è rotta. Nessuno lo sa finché qualcuno non apre la
pagina — e allora esegue la §3.1 del post-deploy, a mano, in produzione. **Un test prima del deploy
non toglie questo problema**: toglie la regressione che lo causa, ma se il fallimento avviene
comunque a runtime resta invisibile. Perciò il task ha due metà, e la seconda è il motivo per cui la
prima serve davvero.

### Generare al build risolve anche altro

Se `l5-swagger:generate` gira nel **Dockerfile**, il JSON entra in un layer dell'immagine. Da lì:
non dipende più dal runtime, sopravvive alla ricreazione del container (`F12`), e un fallimento
**interrompe il build** — cioè l'immagine rotta non arriva nemmeno al registry. È la correzione più
economica di tutte, e il Dockerfile fa già le due cose che le servono: i permessi (`F7`) e artisan
(`F8`). Non elimina la generazione all'entrypoint, che resta utile se qualcuno monta un volume su
`storage`; la rende però non più l'unica.

### Rapporto con gli altri task

- **Risolve `TOA01`** della tranche [v3](../../done/20260812-static-analysis-findings-v3/action-plan.md):
  quel punto chiedeva un modo di confrontare lo specifico OpenAPI prima/dopo per verificare il
  refactoring dei literali. Questo task lo produce. I due vanno letti insieme.
- **Scioglie `BDB01`**: `F2` dice che lo strumento di analisi statica è **SonarQube**, configurato in
  pipeline, e che il suo quality gate **blocca già oggi** i deploy. Era la domanda a monte delle
  quattro tranche `v1`–`v4`.
- **Non c'entra con la policy perf/leak**: nessun service è toccato.

## 4. Da decidere

### Vincoli

- **D1** — dove girano i test: **job di CI** prima del build (semplice, non tocca il Dockerfile, ma
  gira su un checkout e non sull'immagine che si spedisce) o **build multi-stage** (prova l'artefatto
  vero, costa una riscrittura del Dockerfile)?
- **D2** — `F14`: `pdo_sqlite` è disponibile nell'ambiente in cui gireranno i test? Se non c'è, o si
  installa l'estensione o si cambia la configurazione dei test. **Va accertato per primo**: tutto il
  piano poggia lì.

### Conflitti

- **D3** — l'`entrypoint` deve **fermarsi** se la generazione fallisce, o continuare come fa oggi? Il
  primo trasforma una documentazione rotta in un container che non parte: è più onesto e più
  rumoroso. Se il deploy è automatico e non presidiato, è anche più rischioso.
- **D4** — il test 5 (§ 3) verifica che i percorsi attesi ci siano: l'elenco si scrive a mano — e va
  aggiornato a ogni rotta nuova — o si deriva dalle rotte registrate, accettando che allora non
  provi più niente sulle annotazioni? Un elenco che nessuno aggiorna diventa un test che si commenta.

### Ignoto

- **D5** — il job di test va aggiunto **prima** o **dopo** `sonar-scan`? Sonar aspetta il quality
  gate (`F2`) e può essere lento: mettere i test prima dà un ritorno più rapido, ma cambia l'ordine
  di due cancelli che oggi è deliberato — o casuale, non lo so.
- **D6** — esiste già uno smoke test post-deploy automatizzato da qualche parte (Ansible, cron,
  monitoraggio esterno), o la §2 di `post-deploy.md` è tutto ciò che c'è?

## 5. Consigli

| Domanda | Raccomandazione |
|---|---|
| **D1** | **Job di CI**, e nel Dockerfile solo la generazione (non i test). Il multi-stage prova l'artefatto vero, ma raddoppia il tempo di build e ne complica la manutenzione per un guadagno che qui è piccolo: la differenza fra il checkout e l'immagine, per questi test, è solo `--no-dev`. E `TSD05` — generare Swagger nel Dockerfile — copre già il caso «l'immagine è rotta», perché il build si ferma. |
| **D2** | Accertarlo **prima di scrivere un test**, con `php -m` nell'ambiente scelto. È l'unico punto del piano che, se va male, cambia la forma di tutto il resto. |
| **D3** | **Sì, fermarsi**, ma solo dopo che `TSD05` genera al build: a quel punto l'entrypoint che fallisce segnala una condizione che il build avrebbe già dovuto cogliere, quindi è un fallimento raro e vero. Farlo oggi, con la generazione solo a runtime, renderebbe la partenza del container dipendente da un comando che a volte non riesce. |
| **D4** | Elenco **scritto a mano, corto**: quattro o cinque percorsi rappresentativi, uno per controller documentato. Derivarlo dalle rotte lo rende un test che confronta il codice con sé stesso. Corto perché il costo di aggiornarlo dev'essere minore del fastidio di vederlo fallire. |
| **D5** | Test **prima** di Sonar: falliscono in secondi, il quality gate aspetta minuti. Ma verifica che l'ordine attuale non sia voluto per un motivo che non vedo. |
| **D6** | Se la risposta è «non c'è», è un lavoro suo e va aperto (`BDB18`): è lì che il problema dell'IP senza nome di dominio esiste davvero, e non lo risolve questo task. |

Il piano: [action-plan.md](./action-plan.md).
