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
| [./todo/20260812-static-analysis-findings-v2/](./todo/20260812-static-analysis-findings-v2/) | Complessità cognitiva di due funzioni: la lista degli audit e il middleware di autenticazione. Sotto il rilievo ci sono difetti di correttezza che il rilievo non nomina | `TCC` | **alta** |
| [./todo/20260812-static-analysis-findings-v3/](./todo/20260812-static-analysis-findings-v3/) | Literali duplicati nelle annotazioni OpenAPI di quattro controller: percorsi, descrizioni dei parametri, tag | `TOA` | media |
| [./todo/20260812-static-analysis-findings-v4/](./todo/20260812-static-analysis-findings-v4/) | `ProviderUserRoleController`: messaggio d'errore ripetuto tre volte e costruttore vuoto | `TPU` | bassa |
| [./todo/20260812-swagger-deploy-tests/](./todo/20260812-swagger-deploy-tests/) | Test che verificano che la documentazione OpenAPI sia **generata e valida**, e che **fermano la pipeline** prima del deploy. Oggi la pipeline non ha nessun job di test | `TSD` | **alta** |
| [./todo/20260812-e2e-test-container/](./todo/20260812-e2e-test-container/) | Un container dedicato per Cypress: all'immagine dell'applicazione mancano **tutte** le librerie che gli servono. Sblocca la verifica di quattro punti già chiusi nella tranche v1 | `TEC` | **alta** |

## Chiusi

| Task | Obiettivo | Sigla | Chiuso il |
|---|---|---|---|
| [./done/20260812-static-analysis-findings-v1/](./done/20260812-static-analysis-findings-v1/) | Frontend Vue e test E2E. Obiettivo raggiunto: **nessuna password letterale** resta in `database/seeders/` né in `cypress/e2e/`, e le credenziali E2E ora si generano | `TSA` | 2026-08-12 |

L'elenco completo, con cosa è stato scartato e perché, sta in [done/index.md](./done/index.md).
