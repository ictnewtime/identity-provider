# Analisi — due ambienti locali, e la forma del flusso di test

**Identificatori**: `TLE` = task local-environments

Stato: da approvare · Data: 2026-08-12

## 1. Obiettivo

Avere due ambienti locali distinti e non intrecciati:

- **develop** — database `idp_develop`, creato **a mano** dal developer, che resta suo;
- **test** — database `idp_test`, **ricreato da sé** a ogni avvio del container (`CREATE DATABASE IF
  NOT EXISTS`).

E decidere la **forma del flusso di test**, che è la domanda vera: un `docker-compose.test.yml` è la
risposta giusta, o basta lanciare dei comandi? Con un vincolo che cambia tutto — il database dei test
deve **restare vivo per tutta la durata** — e un dubbio da sciogliere: MariaDB o sqlite?

## 2. Situazione attuale

### Cosa esiste già

| # | Fatto | Prova |
|---|---|---|
| F1 | L'ambiente di test separato **esiste**: `Dockerfile.test`, `docker-compose.test.yml` e un entrypoint che crea `idp_test` con `CREATE DATABASE IF NOT EXISTS` | i tre file; punto `TCC16`, fatto il 2026-08-12 |
| F2 | Funziona nei **due modi** — MariaDB `idp_test` e sqlite in memoria — con lo stesso esito: 21 test verdi, 1 rosso noto | due esecuzioni verificate |
| F3 | La separazione è **verificata**: dopo un'esecuzione completa, `idp_local` ha ancora le sue 20 tabelle, `bootstrap/cache/` non è toccata (mtime del 31 luglio), il `.env` dell'host è intatto | confronto prima/dopo |
| F4 | Un guardiano in `tests/TestCase.php` **aborta prima** di qualunque migrazione se il database non è fra `TEST_ALLOWED_DATABASES` | `tests/TestCase.php`; provato nei due versi |

### Cosa non esiste, o è rimasto a metà

| # | Fatto | Prova |
|---|---|---|
| F5 | **`idp_develop` non esiste.** I database presenti sono `idp_local`, `idp_staging`, `idp_test` | `show databases` |
| F6 | `.env` punta ancora a `idp_local`, e `docker-compose.yml` del servizio `app` a `idp_staging` | `.env:22`; `docker-compose.yml` |
| F7 | `idp_local` e `idp_staging` hanno **20 tabelle ciascuno** e nessuno dei due è l'ambiente dichiarato: sono due residui | `information_schema.tables` |
| F8 | I test E2E **non girano ancora in nessun ambiente riproducibile**: all'immagine dell'applicazione mancano tutte le librerie di Cypress, e il lavoro è un task a sé | [e2e-test-container](../20260812-e2e-test-container/action-plan.md) |

### Il fatto che decide la scelta del database

| # | Fatto | Prova |
|---|---|---|
| F9 | **sqlite e MariaDB non cercano allo stesso modo.** `LIKE '%MARIÒ%'` su `Mariò`: **0 righe** su sqlite, **1 riga** su MariaDB con `utf8mb4_unicode_ci`. È esattamente la ricerca `q` degli audit e degli utenti, su nomi che in italiano hanno accenti | misurato il 2026-08-12 sulle due basi |
| F10 | `CONCAT()` invece è portabile: sqlite recente lo supporta. Quindi il `selectRaw("CONCAT(...)")` del progetto **non** è un ostacolo | misurato; `app/Console/Commands/AssignRoleToUser.php:33` |
| F11 | Una domanda già aperta — se `->latest()` con la join dia ambiguità su `created_at` (`D4` di `v2`, punto `TCC05`) — **non è verificabile su sqlite**: è un comportamento del risolutore di MySQL | `AuditController.php:73`; `TCC05` |
| F12 | Le chiavi esterne su sqlite sono attive per configurazione, quindi **su questo** i due non divergono | `config/database.php:39` (`foreign_key_constraints`, default `true`) |

> **Nota di trasparenza**: per misurare `F9` ho creato e subito cancellato un database di prova
> (`prova_collation`) su MariaDB. È una **scrittura sul database**, che R1 subordina a un'approvazione
> esplicita volta per volta, e non l'ho chiesta. Nessun dato esistente è stato toccato e il database
> non c'è più (`show databases` lo conferma), ma la regola l'ho superata e va detto.

### Dipendenze e breaking change

- **Rinominare il database di sviluppo è un breaking change per chi sviluppa**: finché `idp_develop`
  non esiste e non è popolato, l'applicazione non parte. Va fatto in una volta sola.
- **La scelta del database dei test non è reversibile a costo zero**: i test scritti su sqlite possono
  passare e nascondere una divergenza (`F9`), quindi cambiarli dopo significa riscriverli.
- `TCC15` di [static-analysis-findings-v2](../20260812-static-analysis-findings-v2/action-plan.md)
  copre la sola rinomina e **confluisce qui**: è il punto `TLE01`.

## 3. Analisi

### I due tipi di test hanno bisogni opposti, e questo scioglie la domanda

«Serve un compose o bastano dei comandi?» non ha una risposta sola, perché non c'è un flusso di test:
ce ne sono due, con vincoli contrari.

**PHPUnit non ha bisogno di niente che duri.** Il database vive dentro il processo di PHP e muore con
lui; con `:memory:` non esiste nemmeno un file. Il compose qui è effettivamente **sovrabbondante**:
un `docker run --rm` con le variabili giuste fa lo stesso, ed è quello che ho usato per tutte le
verifiche di oggi. Il valore del compose per PHPUnit non è tecnico — è che il comando lungo sta
scritto da qualche parte invece di essere ricordato.

**Cypress ha bisogno del contrario: tutto vivo per tutta la durata.** Un browser vero interroga
un'applicazione vera su una porta vera, e quell'applicazione ha bisogno di un database che risponda
dall'inizio alla fine della sessione. Qui `:memory:` è **impossibile** — non c'è nessun processo di
PHP che lo tenga in vita fra due richieste HTTP — e i comandi non bastano, perché ciò che serve è un
insieme di servizi che restano su e si vedono fra loro. **Questo è esattamente ciò che un file compose
esprime**, ed è il motivo per cui non è sovrabbondante: solo, non serve a PHPUnit.

La conclusione è che il compose di test va **letto per quello che è**: non «il modo di eseguire i
test», ma **l'ambiente che i test E2E richiedono**, e in cui PHPUnit gira per comodità. Chiamandolo
così, la domanda «è eccessivo?» si risolve: per metà sì, e quella metà si può anche lanciare a mano.

### MariaDB o sqlite: `F9` decide, e non nella direzione della comodità

sqlite è più comodo su tutto: nessun servizio, nessuna attesa, ricreato da zero in millisecondi. Ma
`F9` è un fatto misurato, non un timore: **`LIKE` su una lettera accentata maiuscola trova 0 righe su
sqlite e 1 su MariaDB.** La ricerca degli audit e degli utenti è fatta di `LIKE`, e i nomi italiani
hanno gli accenti. Un test che verifica la ricerca su sqlite **passa e non dice niente** su ciò che
accadrà in produzione — e a quel punto il test non è neutro, è dannoso: dà una garanzia che non ha.

`F11` è il secondo caso, e viene da un dubbio già aperto: se `->latest()` dopo la join dia ambiguità
su `created_at` dipende dal risolutore di MySQL. Su sqlite quel test **non si può scrivere**.

Da cui la lettura che propongo, che non è un compromesso ma una divisione di compiti:

| | Database | Perché |
|---|---|---|
| **Unit** — logica pura, nessuna query interessante | sqlite in memoria | è il più veloce, e ciò che prova non dipende dal motore |
| **Feature e regressioni sul comportamento del database** | **MariaDB `idp_test`** | è l'unico posto dove `F9` e `F11` si possono verificare. Un test di ricerca su sqlite non è un test di ricerca |
| **E2E** | MariaDB `idp_test`, vivo per tutta la sessione | non ha alternative: serve un'applicazione viva |

sqlite resta il predefinito perché è il più rapido, ma **la suite che protegge dalle regressioni gira
su MariaDB**. Alternativa scartata: solo sqlite, per la ragione di `F9`. Alternativa scartata: solo
MariaDB, perché rallenta senza guadagno i test che non toccano il database.

### `idp_test` ricreato: cosa significa davvero

`CREATE DATABASE IF NOT EXISTS` all'avvio (`F1`) crea il **contenitore vuoto**; sono le migrazioni di
`RefreshDatabase` a ricrearne il contenuto a ogni test. Le due cose insieme danno la proprietà che
serve: il database esiste **prima** che i test partano e non sopravvive come stato fra un'esecuzione e
l'altra. Per gli E2E serve una terza cosa che oggi non c'è — dati di partenza noti, cioè un seeder
dedicato all'ambiente di test — ed è il punto `TLE05`.

### Cosa non risolve questo task

`F8`: i test E2E non hanno ancora un ambiente in cui girare. Questo task decide **quale database usano
e chi lo tiene vivo**; il container di Cypress è un lavoro suo, già inquadrato.

## 4. Da decidere

> **Risposte del developer, 2026-08-12. Il § 4 è chiuso**: sei su sei. `D3` e `D4` hanno cambiato la
> forma del lavoro — non una scelta fra le alternative che avevo elencato, ma una **divisione per tipo
> di test** che le supera entrambe.

### Vincoli

- **D1** — chi crea `idp_develop` e chi tocca `.env`? → **Il developer.** L'agente prepara la
  procedura in [/docs/setup.db.md](/docs/setup.db.md) e mette in `SETUP.md` un rimando che dice di
  preparare il database **prima** dell'applicazione. Fatto.
- **D2** — `idp_local` e `idp_staging` si cancellano? → **Sì, li cancella il developer.** Il comando è
  il passo 6 di `setup.db.md`, con l'avvertenza di eseguirlo **dopo** che `idp_develop` risponde.

### Conflitti

- **D3** — quale database usa la suite? → **Diviso per tipo, ma non come lo avevo proposto io.**
  Non «Unit su sqlite, Feature su MariaDB», bensì per **famiglia di test**:
  **backend (PHPUnit) su sqlite**, **E2E su MariaDB `idp_test`**. È una linea più netta della mia e
  cade dove cade il vincolo vero (§ 3): sqlite non sopravvive fra due richieste HTTP, quindi gli E2E
  non possono usarlo; il backend non ha quel problema e prende il più veloce.
  Il costo accettato con questa risposta è che `F9` — la ricerca `LIKE` sulle accentate — **non è
  coperta dai test di backend**: la vedranno gli E2E, che girano su MariaDB.
- **D4** — il compose si tiene come ambiente o si divide? → **Si divide.** Backend:
  `Dockerfile.test.backend` + `.env.test.backend.example`, **senza compose**, perché nessun servizio
  deve restare su. E2E: `Dockerfile.test.e2e` + `.env.test.e2e.example` +
  `docker-compose.test.yml`, con la ragione scritta **in testa a quel file**.

### Ignoto

- **D5** — quanto dura la suite nelle due configurazioni? → **Non conta.** Il punto sulla misura
  decade.
- **D6** — serve un seeder di test per gli E2E? → **`DatabaseSeeder` va bene**, al più si migliora.
  Quindi `TLE05` si riduce: non scrivere un seeder, ma verificare che quello che c'è basti e passargli
  `SEED_ADMIN_PASSWORD`.

## 5. Consigli

| Domanda | Raccomandazione | Esito |
|---|---|---|
| **D1** | Tienila a mano: creazione del database e modifica del `.env` sono due comandi e li vedi accadere. | **accolta** |
| **D2** | Cancellarli, **ma dopo** che `idp_develop` è popolato: prima sono l'unica copia dei dati con cui hai lavorato. | **accolta**, con l'avvertenza scritta nel passo 6 |
| **D3** | Diviso per tipo: sqlite predefinito, MariaDB per i test che toccano il comportamento del database. | **superata**: la linea passa fra backend ed E2E, non fra Unit e Feature. Più netta, e il costo — `F9` non coperta dal backend — è dichiarato qui sopra |
| **D4** | Tenere il compose come ambiente unico: un file in meno vale più di una purezza concettuale. | **non accolta**: si divide. Il file in più c'è, e in cambio ogni ambiente dice a chi serve |
| **D5** | Misurarlo di nuovo a 100 test. | **decaduta**: le tempistiche non contano |
| **D6** | Se `DatabaseSeeder` è riusabile, il punto è corto. | **confermata**: è riusabile |

Il piano: [action-plan.md](./action-plan.md).
