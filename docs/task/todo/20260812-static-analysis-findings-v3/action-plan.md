# Piano d'azione — literali duplicati nelle annotazioni OpenAPI

Sigla dichiarata dall'analisi: `TOA` — qui non si ridichiara.

Stato: **da approvare** · Data: 2026-08-12 · Analisi: [analysis.md](./analysis.md)

Fatti e alternative stanno nell'analisi: `F…` e `D…` puntano lì. V — `auto`: lo stabilisce un
comando · `man`: lo legge una persona.

## Onda 1 — la verifica, prima di toccare le annotazioni

Il rischio di questa tranche non è rompere un test: è rompere la **documentazione generata**, che
nessun test guarda (§ 2). Questo punto decide se il resto del piano è verificabile o creduto.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TOA01 | da approvare | Accertare come si confronta lo specifico OpenAPI prima/dopo (`D3`): il file è `storage/api-docs/api-docs.json` (`config/l5-swagger.php:84,31`), generato da `l5-swagger:generate` — generarlo **ora** e conservarlo come riferimento. **Lo produce il task [swagger-deploy-tests](../20260812-swagger-deploy-tests/action-plan.md)**: se `TSD02` è già fatto, qui non c'è niente da accertare | nessuno (o il file generato, se versionato) | basso | auto | il comando gira nel container e produce un file confrontabile; se non esiste, il punto si chiude `scartato` col perché e tutti i punti sotto restano `man` |

## Onda 2 — le costanti condivise

Prima le condivise: metterle dopo significherebbe scrivere due volte le stesse costanti, prima nei
file e poi nella base.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TOA02 | da approvare | **`F6`–`F8`** — le tre descrizioni di parametro che si ripetono **su più file** (`"Provider id"` 7 volte su 3 file, `"Role id"` 5 su 2, `"User id"` 5 su 2) diventano costanti nel controller base, accanto alle `OA_DESC_MSG_*` che esistono già (`F9`, `D1`) | `app/Http/Controllers/Controller.php:34-45` | basso | auto | `grep -rn -F '"Provider id"' app/` non trova più occorrenze fuori dalla dichiarazione; idem per gli altri due |

## Onda 3 — le costanti locali, un controller per volta

Indipendenti fra loro: ognuno chiude i rilievi del suo file. Ordinati per numero di occorrenze.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TOA03 | da approvare | **`F5`** — il tag `"Provider User Roles"`, 5 volte nello stesso file, diventa una costante di classe | `ProviderUserRoleController.php` | basso | auto | `grep -c -F '"Provider User Roles"'` sul file restituisce 1 |
| TOA04 | da approvare | **`F2`** — il percorso `"/api/v1/provider-user-roles/{id}"` (×3) e la sua collezione: **una** costante per la collezione, più `self::PATH . "/{id}"` dove serve (§ 3) | `ProviderUserRoleController.php` | basso | auto | il literale compare una volta sola nel file |
| TOA05 | da approvare | **`F1`** — stessa forma per `"/api/v1/providers/{id}"` (×3), più `"Provider id"` (×3) che passa alla costante condivisa di `TOA02` | `ProviderController.php` | basso | auto | i due literali compaiono zero volte nel file |
| TOA06 | da approvare | **`F3`** — `"/api/v1/roles/{id}"` (×3), più `"Role id"` (×3) alla costante condivisa | `RoleController.php` | basso | auto | idem |
| TOA07 | da approvare | **`F4`** — `"/api/v1/users/{id}"` (×3), più `"User id"` (×3) alla costante condivisa | `UserController.php` | basso | auto | idem |
| TOA08 | da approvare | Chiusura: lo specifico OpenAPI generato dopo le modifiche è **identico** a quello di `TOA01`. Se `TOA01` è stato scartato, questo punto è una rilettura a mano delle otto annotazioni toccate | nessuno | medio — è l'unica prova che il refactoring non ha cambiato la documentazione | auto se `TOA01` è passato, altrimenti man | `diff` fra il riferimento di `TOA01` e il file rigenerato: nessuna differenza |

## Cosa questo piano non copre

- **La divergenza fra `routes/web.php` e i percorsi annotati** (`F10`, `D2`): è la duplicazione che
  costa di più — rinominare una rotta lascia la documentazione che punta alla vecchia — e questi
  rilievi l'hanno solo sfiorata. Task suo, **da aprire**.
- **`"Provider user role not found"`**: non è un'annotazione ma un messaggio di risposta, sta in
  [v4](../20260812-static-analysis-findings-v4/action-plan.md).
- La **traduzione** delle descrizioni OpenAPI: restano in inglese, ed è corretto (`F11`).
