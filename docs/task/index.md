# Task — il lavoro di prodotto

Un task per **obiettivo**. Percorso `backlog/` → `todo/` → `done/`: un task chiuso non si cancella,
si sposta. Ogni cartella datata ha una riga, e ogni riga ha la sua cartella — lo verifica
`./scripts/check-task-index.sh`.

Accanto ai task, due **registri per contesto** — non hanno cartelle datate e restano fuori dal
controllo degli indici:

| Registro | Cosa contiene |
|---|---|
| [backlog/](./backlog/index.md) | dubbi e proposte **non ancora decise**, ognuno col modo di scioglierlo, più i **task inquadrati e non avviati** — oggi [pipeline-tests](./backlog/20260812-pipeline-tests/), fermo perché la pipeline non si tocca |
| [vulnerability/](./vulnerability/index.md) | difetti **accertati** con `file:riga`, livello e mitigazione. Ciò che non rompe niente non entra |

## Aperti

Le tranche `-v2`…`-v4` vengono dalla **stessa passata di analisi statica del 2026-08-12** e si
leggono insieme: sono divise per obiettivo, non per priorità. La `-v1` è **chiusa**, sotto.

| Task | Obiettivo | Sigla | Priorità |
|---|---|---|---|
| [./todo/20260813-token-refresh/](./todo/20260813-token-refresh/) | L'app token scade dopo 30 minuti e **nessuno lo rinnova**, benché il meccanismo esista già (`token/exchange`) e il codice dichiari di volerlo. Difetto `VDF13` | `TTR` | **alta** |
| [./todo/20260819-session-bootstrap/](./todo/20260819-session-bootstrap/) | **Blocco in esercizio su staging**: nessun percorso crea più la sessione di un'applicazione esterna, quindi il primo accesso è impossibile e il client ritenta all'infinito. Regressione di `TTR08`, difetti `VDF16` e `VDF17` | `TSB` | **alta** |
| [./todo/20260819-audit-silent-failure/](./todo/20260819-audit-silent-failure/) | `logAudit()` inghiotte qualunque errore e prosegue: **un audit che non si scrive non lo sa nessuno**. Non è un rilievo di SonarQube — è una decisione di prodotto rimasta implicita, la stessa di `VDF07` sul deploy | `TAS` | media |
| [./todo/20260820-error-messages/](./todo/20260820-error-messages/) | Tre risposte `404` senza messaggio, e — scoperto guardando — **34 blocchi `catch` su 39 che ignorano ciò che il server spiega**. La regola: ogni toast si comporta come `DeleteUserDialog`, che il messaggio del server lo legge | `TER` | bassa |
| [./todo/20260812-swagger-deploy-tests/](./todo/20260812-swagger-deploy-tests/) | Test che verificano che la documentazione OpenAPI sia **generata e valida**, e che **fermano la pipeline** prima del deploy. Oggi la pipeline non ha nessun job di test | `TSD` | **alta** |
| [./todo/20260812-e2e-test-container/](./todo/20260812-e2e-test-container/) | Un container dedicato per Cypress: all'immagine dell'applicazione mancano **tutte** le librerie che gli servono. Sblocca la verifica di quattro punti già chiusi nella tranche v1 | `TEC` | **alta** |

## Chiusi

| Task | Obiettivo | Sigla | Chiuso il |
|---|---|---|---|
| [./done/20260812-static-analysis-findings-v1/](./done/20260812-static-analysis-findings-v1/) | Frontend Vue e test E2E. Obiettivo raggiunto: **nessuna password letterale** resta in `database/seeders/` né in `cypress/e2e/`, e le credenziali E2E ora si generano | `TSA` | 2026-08-12 |
| [./done/20260812-local-environments/](./done/20260812-local-environments/) | Due ambienti locali separati — develop `idp_develop`, test `idp_test` — e due ambienti di test distinti: backend su sqlite senza compose, E2E su MariaDB con compose | `TLE` | 2026-08-12 |
| [./done/20260812-static-analysis-findings-v2/](./done/20260812-static-analysis-findings-v2/) | Complessità cognitiva: lista audit e middleware scomposti in classi provabili. Sotto il rilievo c'erano quattro difetti che il rilievo non nominava | `TCC` | 2026-08-13 |
| [./done/20260812-static-analysis-findings-v3/](./done/20260812-static-analysis-findings-v3/) | Literali duplicati nelle annotazioni OpenAPI: nove rilievi chiusi con lo specifico generato **identico** prima e dopo | `TOA` | 2026-08-13 |
| [./done/20260812-static-analysis-findings-v4/](./done/20260812-static-analysis-findings-v4/) | `ProviderUserRoleController`: messaggio ripetuto e costruttore vuoto. I dodici messaggi 404 passano ora dalle traduzioni — chiavi che **esistevano già** in due lingue | `TPU` | 2026-08-13 |
| [./done/20260813-vulnerability-fixes/](./done/20260813-vulnerability-fixes/) | I difetti che non avevano un punto in nessun piano: tre già corretti e spuntati, la guardia contro le cancellazioni spostata dove si cancella, uno chiuso come comportamento voluto | `TVF` | 2026-08-13 |
| [./done/20260819-cypress-assertions/](./done/20260819-cypress-assertions/) | Rilievi bloccanti dai file Cypress. Chiuso **senza implementare niente**: il developer ha cancellato `cypress/e2e-bk/` e uno spec con zero test, e ha scartato il resto. Quel che resta scoperto è scritto nei punti scartati | `TCY` | 2026-08-19 |
| [./done/20260819-route-literals/](./done/20260819-route-literals/) | Tredici rilievi `high` di SonarQube sui literali duplicati. Chiusi con **una costante sola dove serviva**: per undici di essi la risposta era `whereNumber()`, già in uso nello stesso file | `TRL` | 2026-08-19 |
| [./done/20260819-sonar-high-findings/](./done/20260819-sonar-high-findings/) | Cinque rilievi `high`: costruttori vuoti cancellati, i due file di `config/` lasciati stare (rilievi scartati a mano), e un controllo nuovo sulle traduzioni che ha trovato **due difetti che nessuno cercava** — `VDF20` e `VDF21` | `TSH` | 2026-08-19 |
| [./done/20260819-frontend-complexity/](./done/20260819-frontend-complexity/) | Tre rilievi `high` di complessità cognitiva nel frontend. Chiusi senza esecutore di test (`D1`), ma **verificati eseguendo**: 8270 password confrontate su `strength`, 84 casi sui due `submit`. E leggendoli è uscito `VDF22`, un `ReferenceError` che nascondeva gli errori di validazione all'utente | `TFC` | 2026-08-19 |
| [./done/20260819-translation-coverage/](./done/20260819-translation-coverage/) | Il controllo sulle traduzioni passa da **64 a 393 chiavi**. Ha chiuso `VDF23` (due dialoghi mostravano la chiave) e trovato `VDF24` — una traduzione che vale «0» conta come mancante, e il calendario inglese cominciava dal giorno sbagliato. Più gli 82 nomi di test in inglese | `TTC` | 2026-08-19 |
| [./done/20260819-audit-complexity/](./done/20260819-audit-complexity/) | `logAudit()` da 95 righe a 21, e da **zero test a dodici** — il trait era **inerte in tutta la suite** e nessuno lo sapeva. Più l'ambiente dei test con due script, e l'attore che negli audit M2M mancava | `TAC` | 2026-08-19 |

L'elenco completo, con cosa è stato scartato e perché, sta in [done/index.md](./done/index.md).
