# Piano d'azione — rilievi di analisi statica su frontend e test E2E

Sigla dichiarata dall'analisi: `TSA` — qui non si ridichiara, `check-ids.sh` la leggerebbe come duplicata.

Stato: **da approvare** · Data: 2026-08-12 · Analisi: [analysis.md](./analysis.md)

I fatti e le alternative stanno nell'analisi e **non si ripetono qui**: i riferimenti `F…` e `D…`
puntano lì. Il piano **si ferma al primo punto non approvato**.

Legenda della colonna V — `auto`: lo stabilisce un comando · `man`: lo legge una persona.

## Onda 1 — quello che non aspetta (credenziali)

**Ridimensionata dalle risposte del 2026-08-12** (`D1`, `D2`): le credenziali erano **dummy, per il
locale**, in stage e produzione `cypress.env.json` non si usa, e in pipeline lo genera l'esecuzione
stessa. Non c'è niente da ruotare e niente da ripulire dalla storia. Resta da togliere dal
tracciamento un file che non deve esserci.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TSA01 | **fatto** (2026-08-12) | `cypress.env.example.json` con le sole chiavi e **nessuna password**: i due campi di credenziale sono stringhe vuote, gli username sono quelli dedicati di `D8`. Creato su richiesta esplicita del developer | `cypress.env.example.json` (nuovo) | basso | auto | il file esiste, è JSON valido, e `adminPassword`/`nonAdminPassword` sono vuoti |
| TSA14 | da approvare | **Sganciare `cypress.env.json` dal tracciamento git** — è già in `.gitignore` (ultima riga) ma resta indicizzato, quindi continua a essere distribuito a ogni clone. Il comando è `git rm --cached cypress.env.json` e **lo esegue il developer** (R2: git in sola lettura per l'agente); il file sul disco resta dov'è | `cypress.env.json` (solo l'indice git) | basso, dopo `D1` | auto | `git ls-files cypress.env.json` non stampa niente, e il file esiste ancora sul disco |
| ~~TSA02~~ | **scartato** (2026-08-12) | Rotazione delle credenziali esposte. **Non serve** (`D2`): in locale il file lo prepara chi sviluppa, in pipeline lo genera l'esecuzione, in stage e produzione non viene usato. Non esiste un'identità di lunga durata da ruotare | — | — | — | — |
| TSA03 | da approvare | Spostare la password dei dati di test da letterale a `Cypress.env(...)`, con la chiave dichiarata in `cypress.env.example.json` e il **valore generato** dalla procedura di `TSA10`. **Dipende da TSA01**: prima che il file sia sganciato, spostarla lì peggiora `F6` | `cypress/e2e/user/crud-user.cy.js:11` | basso | auto | `grep -nE 'password:\s*"' cypress/e2e/` non trova più letterali (R6: il valore non si riporta, si nomina il campo); `npm run cy:run -- --spec cypress/e2e/user/crud-user.cy.js` passa |

## Onda 1b — le credenziali si generano, non si custodiscono

Nasce dalla considerazione del § 3: quando i test E2E entrano in pipeline (`F12`) qualcuno deve
**fornire** quelle credenziali al deploy, e l'unica risposta che non crea un segreto da proteggere è
generarle a ogni preparazione dell'ambiente. Le due metà — il file **e** gli utenti nel database —
sono inseparabili: la prima da sola produce un login che fallisce sempre (`F13`).

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TSA10 | da approvare | **Lo script** `scripts/prepare-e2e-credentials.sh`: `umask` stretta, password casuali conformi alla policy (maiuscola, minuscola, cifra, simbolo), scrittura di `cypress.env.json`, invocazione del seeder, controllo che il file non sia tracciato da git, pulizia delle variabili in uscita. Idempotente: rieseguirlo rigenera tutto senza lasciare residui | `scripts/prepare-e2e-credentials.sh` (nuovo) | basso | auto | `bash -n` passa; eseguito su un ambiente pulito produce un `cypress.env.json` valido e nessuna password compare nell'output |
| TSA10a | da approvare | **Una riga** in `docs/SETUP.md`, subito dopo il blocco di `php artisan key:generate`, che invoca lo script e lo dichiara obbligatorio. Solo l'invocazione: la procedura sta nello script, non nel documento — due copie divergono, e il documento è quella che nessuno riesegue | `docs/SETUP.md` | basso | auto | il documento cita `scripts/prepare-e2e-credentials.sh` e **non** contiene comandi di generazione né valori |
| TSA11 | da approvare | `E2EUserSeeder` che crea `e2e.admin` ed `e2e.user` leggendo le password **dalle variabili d'ambiente**, senza valore di ripiego scritto nel codice. Chiude la seconda metà di `TSA10`, che oggi resta dichiarata e non funzionante | `database/seeders/E2EUserSeeder.php` (nuovo) | medio — crea utenti con privilegi di amministratore | auto | eseguito il blocco di `SETUP.md`, `npm run cy:run -- --spec cypress/e2e/auth/login.cy.js` passa; senza le variabili esportate il seeder **fallisce** invece di inventare una password |
| TSA12 | da approvare | Togliere la password scritta in chiaro nel seeder principale (`F14`, difetto `VDF08`): `admin.admin` prende la sua da una variabile d'ambiente, come gli utenti E2E | `database/seeders/DatabaseSeeder.php:39` | medio — cambia la preparazione di ogni ambiente nuovo | auto | `grep -n 'Hash::make("' database/seeders/` non trova più letterali |
| TSA13 | da approvare | Eseguire il blocco di `TSA10` **nella pipeline**, prima dei test E2E. Dipende dal job di test, che oggi non esiste: si aggancia a `TSD07` di [swagger-deploy-tests](../20260812-swagger-deploy-tests/action-plan.md) invece di aprire un secondo job | `.github/workflows/deploy-staging.yml`, `deploy-production.yml` | medio | man | una esecuzione della pipeline in cui i test E2E accedono con credenziali generate in quella stessa esecuzione, e nessun segreto compare nei log |

## Onda 2 — le correzioni di codice

Ogni punto è indipendente dagli altri di questa onda. `D7` non blocca più niente: con `D4` che decide
di **togliere** l'attributo, non c'è più un DOM da ispezionare prima.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TSA04 | da approvare | `parseInt` → `Number.parseInt(…, 10)` **con la radice**, e spostare il valore di ripiego **fuori** dalla chiamata: oggi il `\|\| 1` è dentro e non protegge da `NaN` quando la traduzione manca (§ 3) | `resources/js/ui/LocalizedDatePicker.vue:13` | basso — corregge un difetto latente, non cambia il comportamento a chiavi presenti (`F8`) | auto | test unitario nuovo: chiave assente → il primo giorno vale `1`, non `NaN`. Più `npm run build` senza errori |
| TSA05 | da approvare | **`D4`** — rimuovere `autocomplete="new-password"` dal campo `password_confirmation`. È una delle due sole occorrenze del progetto: gli altri sette `<Password>` (`UserForm.vue:312`, `Login.vue:100`, `ResetPassword.vue:73` e `:144`, `ForcePasswordChange.vue:78`, `:115`, `:184`) non ne hanno, quindi il punto **allinea un'eccezione alla norma**. Il pulsante «mostra password» non è toccato: il `type` commutato dal `pt` (`:461`) e l'`autocomplete` sono attributi indipendenti | `resources/js/components/UserForm.vue:454` | basso — con `D4` non c'è più niente da verificare nel DOM | auto | `grep -n autocomplete resources/js/components/UserForm.vue` non trova niente; il pulsante «mostra password» funziona ancora |
| TSA05b | da approvare | **Punto separato, e con una riserva**: `ProviderForm.vue:376` non è un campo password — è la `secret_key` di un provider, resa con `<Password>` solo per mascherarla. Lì `autocomplete: 'new-password'` serviva probabilmente a **impedire** che il browser ci scrivesse dentro una credenziale salvata, e toglierlo può essere una regressione vera. Va deciso guardando il comportamento del browser su quel form, non per analogia con `TSA05` | `resources/js/components/ProviderForm.vue:376` | medio — se la riserva è fondata, toglierlo peggiora | man | compilare il form provider con un gestore password attivo: il campo `secret_key` **non** deve essere riempito automaticamente |
| TSA06 | da approvare | **Nessuna modifica al codice**: il `<title>` c'è già, due volte (`F7`). Si sopprime il falso positivo **alla fonte** — `D6` dice che lo strumento è **SonarQube**, quindi un `NOSONAR` motivato o, meglio, un'esclusione nel progetto Sonar. Non nel componente Vue | configurazione SonarQube; nota in questo piano | basso | man | il rilievo non ricompare alla passata successiva; `Unauthorized.vue` **non** ha guadagnato un secondo titolo |

## Onda 3 — spostamenti e cancellazioni

Sotto, e separati: mescolati alle decisioni si approva uno spostamento prima di aver deciso dove le
cose vanno.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| ~~TSA07~~ | **scartato** (2026-08-12) | Cancellare `cypress/e2e-bk/`. **`D3`: per ora si tiene.** Di conseguenza `TSA08` si attiva, e i rilievi dentro quella cartella vanno esclusi lato SonarQube invece che eliminati (`BDB17`) | — | — | — | — |
| TSA08 | da approvare | **Attivo perché `TSA07` è scartato**: `parseFloat` → `Number.parseFloat` nell'esempio segnalato | `cypress/e2e-bk/2-advanced-examples/assertions.cy.js:170` | basso | auto | `grep -n 'parseFloat' cypress/` trova solo la forma `Number.parseFloat` |
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
