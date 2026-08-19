# Analisi — cinque rilievi `high`: literali nei file di configurazione e costruttori vuoti

**Identificatori**: `TSH` = task sonar-high-findings

Stato: **chiuso** (2026-08-19) · Data: 2026-08-19

## 1. Obiettivo

Chiudere cinque rilievi `high` di SonarQube di due regole diverse:

- **tre duplicazioni di literali** in `config/database.php`, `config/logging.php` e in un file di test;
- **due costruttori vuoti** in `app/Listeners/`.

Perché adesso: sono `high`, e il gate della pipeline aspetta l'esito. Ed è il seguito naturale di
[route-literals](../../done/20260819-route-literals/action-plan.md), chiuso oggi sulla stessa regola in
altri file.

Il precedente conta più della regola: per il costruttore vuoto **questo repository ha già deciso** come
si fa, e per i file di configurazione c'è un vincolo che il rilievo non conosce (§ 3).

## 2. Situazione attuale

| # | Fatto | Prova |
|---|---|---|
| F1 | `'127.0.0.1'` compare **5 volte** in `config/database.php`, ma in **due gruppi diversi**: 3 volte come default di `DB_HOST` (righe 49, 69, 89 — mysql, pgsql, sqlsrv) e 2 come default di `REDIS_HOST` (157, 170) | `grep -n "127.0.0.1" config/database.php` |
| F2 | `'logs/laravel.log'` compare **3 volte** in `config/logging.php`, sempre dentro `storage_path()`: righe 63, 70, 127 | `grep -n "logs/laravel.log" config/logging.php` |
| F3 | `"10.0.0.1"` compare **3 volte** in `tests/Feature/Auth/SessionRevocationTest.php`: due come **valore di default** di parametri (righe 56, 61) e una passata a mano (139) | `grep -n "10.0.0.1" …` |
| F4 | I due costruttori segnalati sono **davvero vuoti e senza parametri**: `LogLoginListener` e `RegistrationListener`, entrambi `implements ShouldQueue`, hanno `public function __construct(){ }` e nient'altro | `app/Listeners/LogLoginListener.php`, `app/Listeners/RegistrationListener.php` |
| F5 | **Il precedente esiste, ed è dello stesso repository**: `TPU02` (2026-08-13) ha chiuso un rilievo identico su `ProviderUserRoleController` **cancellando** il costruttore, non commentandolo — *«un costruttore senza parametri né corpo non fa niente che PHP non faccia da sé, e il commento che SonarQube chiedeva sarebbe stato un commento su una riga che non serve»* | [findings-v4](../../done/20260812-static-analysis-findings-v4/action-plan.md), punto `TPU02` |
| F6 | Nei due listener **l'altra forma è già in uso, nel file accanto al costruttore**: il metodo `failed()` è vuoto ma **porta un commento** — `// Quando si verifica un problema con la coda e non viene processato`. Quel metodo fa parte del contratto di `ShouldQueue` e non si può cancellare; il costruttore no | stessi due file |

### Dipendenze e breaking change

- **`config/database.php` e `config/logging.php` non sono file nostri**: arrivano dallo scheletro di
  Laravel. Toccarli crea divergenza dal fornitore, e ogni aggiornamento del framework la fa pesare.
  È il vincolo che il rilievo non conosce, e decide la **forma** della correzione (§ 3).
- **La configurazione si mette in cache**: con `bootstrap/cache/config.php` presente, i file di
  `config/` **non vengono più valutati**. Qualunque cosa si scriva lì dentro deve funzionare anche
  quando quel file non viene eseguito — che è la ragione per cui una `const` a livello di file è la
  scelta peggiore delle due possibili (§ 3).
- **`SessionRevocationTest.php` è in bilico**: oggi ha **5 test rossi**, di cui 3 con errore fatale
  `Unknown named parameter $canCreate`, perché il revert del 2026-08-19 ha riportato `SessionService`
  a una versione che quel parametro non ha. Il file è candidato alla cancellazione — non esisteva prima
  di `bd7fc0c`. Se viene cancellato, il rilievo `F3` sparisce con lui.
- **Nessun cambiamento di comportamento è previsto**: tutto il rischio sta nel cambiare per sbaglio un
  valore di configurazione, e si copre confrontando i valori risolti prima e dopo.

## 3. Analisi

### I due file di configurazione: variabile, non costante

Il rilievo chiede «una costante». Dentro un file di `config/` è la forma **peggiore** delle due:

- una `const` a livello di file vive nello spazio dei nomi globale e viene definita **solo se il file
  viene eseguito**; con la configurazione in cache quel file non si esegue, quindi la costante
  semplicemente **non esiste**. Finché nessuno la usa altrove non fa danno, ma è una dichiarazione che
  a volte c'è e a volte no — e su questo repository la configurazione in cache è già stata la causa di
  un difetto (`VDF11`: `env()` inerte con la config in cache);
- una **variabile locale** prima del `return` non ha nessuno di questi problemi: vive quanto la
  valutazione del file, il literale compare una volta, e il rilievo si chiude ugualmente.

Quindi: `$defaultHost = '127.0.0.1';` e `$defaultLogPath = storage_path('logs/laravel.log');`, usati
dentro l'array. Due righe in due file, e nessuna dichiarazione che sopravvive al file.

**Sui due gruppi di `F1`**: `DB_HOST` e `REDIS_HOST` sono default di cose diverse che oggi valgono lo
stesso indirizzo. Una variabile sola li unisce e sarebbe più corta; **due** dicono la verità — che sono
due default indipendenti, e che domani uno può cambiare senza l'altro. Costa una riga in più.

### I costruttori: si cancellano

Il rilievo offre tre uscite — un commento, un'eccezione, l'implementazione. La quarta, che non nomina,
è quella giusta e **questo repository l'ha già scelta** (`F5`): un costruttore senza parametri né corpo
si cancella. PHP ne fornisce uno identico da sé.

I due file mostrano da soli quando serve l'altra forma (`F6`): `failed()` è vuoto **ma resta**, perché
fa parte del contratto di `ShouldQueue`, e lì il commento è la risposta giusta — e c'è già.

### Il test: dipende se il file sopravvive

`F3` si chiude con una `private const IP` nella classe, come si è fatto in
`ProviderUserRoleNotFoundTest` con `TRL05`. Ma il file è rotto e candidato alla cancellazione: mettere
una costante in un file che si sta per cancellare è lavoro che si butta. L'ordine giusto è deciderlo
prima (`D2`).

### Codice cancellato

I due costruttori vuoti. Nient'altro.

## 4. Da decidere

### Vincoli

- ~~**`D1`**~~ — **risposta del 2026-08-19: si lascia stare.** I due file di `config/` non si toccano;
  il developer **scarta i due rilievi a mano** dal server di SonarQube. È la strada (d) che l'analisi di
  [cypress-assertions](../../done/20260819-cypress-assertions/analysis.md) aveva descritto: chiude il
  gate senza toccare file dello scheletro. **La conseguenza, registrata**: quel «won't fix» vive nel
  database di SonarQube, non nel repository — un progetto nuovo, o un reset del server, e i due rilievi
  tornano senza che nulla nel codice lo dica.
- ~~**`D2`**~~ — **risposta del 2026-08-19: `SessionRevocationTest.php` si cancella.** Quindi `F3`
  decade insieme al file, e il punto che la copriva è scartato.

### Conflitti

- ~~**`D3`**~~ — **risposta: due variabili**, una per `DB_HOST` e una per `REDIS_HOST`. Ma **decade con
  `D1`**: i file non si toccano, quindi non ci sono variabili da scrivere. La risposta resta scritta
  perché è la forma da usare il giorno che quei default si tocchino per un motivo vero.

### Ignoto

- Niente: i cinque rilievi sono verificati nel codice, e le forme delle correzioni hanno tutte un
  precedente nel repository.

## 5. Consigli

- ~~**`D1` → toccare**~~ — **il developer lascia stare e scarta i rilievi a mano.** Il mio consiglio
  pesava il rilievo; la sua risposta pesa il **file**: `config/database.php` e `config/logging.php` sono
  dello scheletro di Laravel, e due righe nostre lì dentro sono due righe da riconciliare a ogni
  aggiornamento del framework, per sempre. Lo scarto a mano si paga una volta.
- **`D2` → cancellare il file: confermato dal developer.** Ha 5 test rossi di cui 3 fatali, descrive un
  comportamento che il revert ha rimosso, e non esisteva prima di `bd7fc0c`. Tornerà quando si
  rifaranno `TTR03` e `TTR08`, scritto sul codice che ci sarà — ed è il punto `TSH05`.
- ~~**`D3` → due variabili**~~ — la risposta è la stessa, e non si applica più: decade con `D1`.
- **I costruttori si cancellano, non si commentano** — `F5`, e non è una preferenza mia: è la decisione
  che questo repository ha già preso su un rilievo identico.
