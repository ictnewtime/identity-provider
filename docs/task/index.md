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

L'elenco completo, con cosa è stato scartato e perché, sta in [done/index.md](./done/index.md).
