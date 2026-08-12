# Piano d'azione — rilievi di analisi statica su frontend e test E2E

Sigla dichiarata dall'analisi: `TSA` — qui non si ridichiara, `check-ids.sh` la leggerebbe come duplicata.

Stato: **da approvare** · Data: 2026-08-12 · Analisi: [analysis.md](./analysis.md)

I fatti e le alternative stanno nell'analisi e **non si ripetono qui**: i riferimenti `F…` e `D…`
puntano lì. Il piano **si ferma al primo punto non approvato**.

Legenda della colonna V — `auto`: lo stabilisce un comando · `man`: lo legge una persona.

## Onda 1 — le credenziali: si generano, non si custodiscono

**Onda 1b unita qui il 2026-08-12**, su richiesta del developer: erano due onde che descrivevano la
stessa cosa in due momenti — il file di esempio e lo sgancio da git da una parte, la generazione
dall'altra — e separarle faceva approvare due volte una decisione sola.

**Ridimensionata dalle risposte** (`D1`, `D2`): le credenziali erano **dummy, per il locale**, in
stage e produzione `cypress.env.json` non si usa, e in pipeline lo genererà l'esecuzione stessa. Non
c'è niente da ruotare né da ripulire dalla storia.

La sostanza dell'onda: le due metà — il file **e** gli utenti nel database — sono inseparabili, perché
la prima da sola produce un login che fallisce sempre (`F13`).

> **L'obiettivo principale dell'onda è raggiunto**: nessuna password letterale resta in
> `database/seeders/` né in `cypress/e2e/` — verificato con `grep`. Le credenziali erano dummy, ma la
> forma che le produceva era il difetto, e quella non c'è più.
>
> **Cosa è verificato e cosa no.** I punti chiusi superano i controlli di sintassi (`bash -n`,
> `php -l`, `node --check`) e il confronto delle chiavi fra lo script e `cypress.env.example.json`;
> `TSA10` è stato **verificato a mano dal developer**. La verifica *funzionale* degli altri è
> un'esecuzione di Cypress, che oggi non è ripetibile — all'immagine dell'applicazione mancano tutte
> le librerie che Cypress richiede — ed è **delegata a `TEC04`** di
> [e2e-test-container](../20260812-e2e-test-container/action-plan.md). Sono `fatto` per decisione del
> developer, con la delega scritta: se `TEC04` li smentisce, si torna qui.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TSA01 | **fatto** (2026-08-12) | `cypress.env.example.json` con le sole chiavi e **nessuna password**: i due campi di credenziale sono stringhe vuote, gli username sono quelli dedicati di `D8`. Creato su richiesta esplicita del developer | `cypress.env.example.json` (nuovo) | basso | auto | il file esiste, è JSON valido, e `adminPassword`/`nonAdminPassword` sono vuoti |
| TSA14 | **approvato** (2026-08-12) | **Sganciare `cypress.env.json` dal tracciamento git** — è già in `.gitignore` (ultima riga) ma resta indicizzato, quindi continua a essere distribuito a ogni clone. Il comando è `git rm --cached cypress.env.json` e **lo esegue il developer** (R2: git in sola lettura per l'agente); il file sul disco resta dov'è | `cypress.env.json` (solo l'indice git) | basso, dopo `D1` | auto | `git ls-files cypress.env.json` non stampa niente, e il file esiste ancora sul disco |
| TSA10 | **fatto** (2026-08-12) — verificato a mano dal developer | **Lo script** `scripts/prepare-e2e-credentials.sh`: `umask` stretta, password casuali conformi alla policy (maiuscola, minuscola, cifra, simbolo), scrittura di `cypress.env.json`, invocazione del seeder, controllo che il file non sia tracciato da git, pulizia delle variabili in uscita. Idempotente: rieseguirlo rigenera tutto senza lasciare residui | `scripts/prepare-e2e-credentials.sh` (nuovo) | basso | auto | `bash -n` passa; eseguito su un ambiente pulito produce un `cypress.env.json` valido e nessuna password compare nell'output |
| TSA10a | **fatto** (2026-08-12) — la verifica funzionale e' delegata a `TEC04` di [e2e-test-container](../20260812-e2e-test-container/action-plan.md) | **Una riga** in `docs/SETUP.md`, subito dopo il blocco di `php artisan key:generate`, che invoca lo script e lo dichiara obbligatorio. Solo l'invocazione: la procedura sta nello script, non nel documento — due copie divergono, e il documento è quella che nessuno riesegue | `docs/SETUP.md` | basso | auto | il documento cita `scripts/prepare-e2e-credentials.sh` e **non** contiene comandi di generazione né valori |
| TSA11 | **fatto** (2026-08-12) — la verifica funzionale e' delegata a `TEC04` di [e2e-test-container](../20260812-e2e-test-container/action-plan.md) | `E2EUserSeeder` che crea `e2e.admin` ed `e2e.user` leggendo le password **dalle variabili d'ambiente**, senza valore di ripiego scritto nel codice. Chiude la seconda metà di `TSA10`, che senza di lui resta dichiarata e non funzionante | `database/seeders/E2EUserSeeder.php` (nuovo) | medio — crea utenti con privilegi di amministratore | auto | eseguito lo script, `npm run cy:run -- --spec cypress/e2e/auth/login.cy.js` passa; senza le variabili esportate il seeder **fallisce** invece di inventare una password |
| TSA12 | **fatto** (2026-08-12) — la verifica funzionale e' delegata a `TEC04` di [e2e-test-container](../20260812-e2e-test-container/action-plan.md) | Togliere la password scritta in chiaro nel seeder principale (`F14`, difetto `VDF08`): `admin.admin` prende la sua da una variabile d'ambiente, come gli utenti E2E | `database/seeders/DatabaseSeeder.php:39` | medio — cambia la preparazione di ogni ambiente nuovo | auto | `grep -n 'Hash::make("' database/seeders/` non trova più letterali |
| TSA03 | **fatto** (2026-08-12) — la verifica funzionale e' delegata a `TEC04` di [e2e-test-container](../20260812-e2e-test-container/action-plan.md) | Spostare la password dei dati di test da letterale a `Cypress.env(...)`, con la chiave dichiarata in `cypress.env.example.json` e il **valore generato** da `TSA10`. **Dopo `TSA10`**: prima che lo script esista, la chiave non ha chi la riempia | `cypress/e2e/user/crud-user.cy.js:11` | basso | auto | `grep -nE 'password:\s*"' cypress/e2e/` non trova più letterali (R6: il valore non si riporta, si nomina il campo); `npm run cy:run -- --spec cypress/e2e/user/crud-user.cy.js` passa |
| TSA15 | **implementato** (2026-08-12) | `docs/TEST.md`: come si eseguono le due famiglie di test — PHPUnit **dentro** il container, Cypress **fuori** — e la tabella di cosa manca all'immagine perche' Cypress ci giri dentro. Nasce dalla verifica manuale di `TSA10`: senza un posto dove sta scritto come si lancia Cypress, i punti sotto non si possono chiudere | `docs/TEST.md` (nuovo) | basso | auto | il documento esiste e le sue affermazioni sull'immagine sono verificabili con `dpkg -s` |
| ~~TSA02~~ | **scartato** (2026-08-12) | Rotazione delle credenziali esposte. **Non serve** (`D2`): in locale il file lo prepara chi sviluppa, in pipeline lo genera l'esecuzione, in stage e produzione non viene usato. Non esiste un'identità di lunga durata da ruotare | — | — | — | — |
| ~~TSA13~~ | **spostato** (2026-08-12) | Eseguire lo script **nella pipeline**, prima dei test E2E. Il developer non tocca la pipeline per ora: il punto è migrato in [backlog/20260812-pipeline-tests](../../backlog/20260812-pipeline-tests/action-plan.md) come `BPT03`, insieme a tutto il resto che modifica i workflow. Qui non resta niente da fare | — | — | — | — |

## Onda 2 — le correzioni di codice

Ogni punto è indipendente dagli altri di questa onda. `D7` non blocca più niente: con `D4` che decide
di **togliere** l'attributo, non c'è più un DOM da ispezionare prima.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TSA04 | **approvato** (2026-08-12) | `parseInt` → `Number.parseInt(…, 10)` **con la radice**, e spostare il valore di ripiego **fuori** dalla chiamata: oggi il `\|\| 1` è dentro e non protegge da `NaN` quando la traduzione manca (§ 3) | `resources/js/ui/LocalizedDatePicker.vue:13` | basso — corregge un difetto latente, non cambia il comportamento a chiavi presenti (`F8`) | auto | test unitario nuovo: chiave assente → il primo giorno vale `1`, non `NaN`. Più `npm run build` senza errori |
| ~~TSA05~~ | **scartato** (2026-08-12) | Rimuovere `autocomplete="new-password"` da `password_confirmation`. **Non si fa**: il developer ha rovesciato `D4` dopo la riserva registrata in `TSA05b` — `new-password` distingue i campi della password **nuova** da quello della password **attuale**, ed e' cio' che impedisce al browser di precompilare la credenziale salvata dove va scritta una password diversa. Toglierlo sarebbe una regressione. Il rilievo di SonarQube resta aperto ed e' un falso positivo: la **motivazione da incollare in Sonar** e' pronta in [doc-code-guide-line.md](/docs/doc-code-guide-line.md), da marcare *False Positive* come `TSA06` | — | — | — | — |
| ~~TSA05b~~ | **scartato** (2026-08-12) | Stessa decisione per `ProviderForm.vue:376`, dove il campo non e' nemmeno una password ma la `secret_key` di un provider: la riserva del piano era fondata e il developer l'ha confermata | — | — | — | — |
| TSA06 | **approvato** (2026-08-12) | **Due rilievi, nessuna modifica al codice.** (a) Il `<title>` c'è già, due volte (`F7`); (b) l'`autocomplete` di `UserForm.vue:454`, che `TSA05` ha scartato — motivazione pronta in [doc-code-guide-line.md](/docs/doc-code-guide-line.md). Si sopprime il falso positivo **alla fonte** — `D6` dice che lo strumento è **SonarQube**, quindi un `NOSONAR` motivato o, meglio, un'esclusione nel progetto Sonar. Non nel componente Vue | configurazione SonarQube; nota in questo piano | basso | man | il rilievo non ricompare alla passata successiva; `Unauthorized.vue` **non** ha guadagnato un secondo titolo |

## Onda 3 — spostamenti e cancellazioni

Sotto, e separati: mescolati alle decisioni si approva uno spostamento prima di aver deciso dove le
cose vanno.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| ~~TSA07~~ | **scartato** (2026-08-12) | Cancellare `cypress/e2e-bk/`. **`D3`: per ora si tiene.** Di conseguenza `TSA08` si attiva, e i rilievi dentro quella cartella vanno esclusi lato SonarQube invece che eliminati (`BDB17`) | — | — | — | — |
| TSA08 | **fatto** (2026-08-12) | **Attivo perché `TSA07` è scartato**: `parseFloat` → `Number.parseFloat` nell'esempio segnalato | `cypress/e2e-bk/2-advanced-examples/assertions.cy.js:170` | basso | auto | `grep -n 'parseFloat' cypress/` trova solo la forma `Number.parseFloat` |
| ~~TSA09~~ | **scartato** (2026-08-12) | Portare ESLint nel repo. **`D5`: niente ESLint per ora.** I rilievi restano governati da SonarQube in pipeline; il costo accettato è che in locale non si riproducono — resta aperto in `BDB02` | — | — | — | — |

## Cosa questo piano non copre

- **L'esclusione di `cypress/e2e-bk/` da SonarQube**: `D3` la tiene e `D5` esclude ESLint, quindi
  l'unico modo di non vedere più quei rilievi è una regola lato Sonar. È `BDB17`, e non è un punto qui
  perché la configurazione Sonar non sta in questo repo.
- **La soppressione del falso positivo di `TSA06`**: stessa ragione — si scrive dove vive il progetto
  SonarQube.
- **La riproduzione locale dei rilievi** (`BDB02`): con `D5` non c'è. Chi scrive codice lo scopre dal
  quality gate, cioè al momento del rilascio.
- **Il rifacimento del meccanismo «mostra password»**: `D4` non lo tocca — decide solo
  sull'`autocomplete`. Se un giorno si volesse rivedere, è un task suo.
