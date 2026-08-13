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
| TOA01 | **fatto** (2026-08-13) | **`D3`** — il confronto dello specifico generato esiste ed è ripetibile: `./scripts/openapi-spec-diff.sh salva` prima di toccare le annotazioni, `confronta` dopo. **Il file NON si versiona**: `storage/api-docs/.gitignore` contiene `*`, e `api-docs.json` è un artefatto generato — versionarlo darebbe un conflitto a ogni annotazione toccata da due persone. L'istantanea fuori dall'albero costa meno e serve allo stesso scopo | `scripts/openapi-spec-diff.sh` (nuovo) | basso | auto | **provato nei due versi**: con le annotazioni intatte dice «identico»; cambiando una `description` di una riga, la mostra e esce con errore |
| TOA09 | **fatto** (2026-08-13) | **`D2`** — `OpenApiRoutesTest`: ogni percorso **documentato** deve avere una rotta registrata. In una direzione sola (`F12`, `F14`). **Riscritto durante l'implementazione**: leggeva i `path:` dal sorgente con una regex, e ha smesso di funzionare appena quei literali sono diventati costanti — sarebbe diventato «zero percorsi, tutto a posto». Ora legge lo **specifico generato**, che porta i percorsi risolti ed è quello che i client leggono | `tests/Feature/OpenApiRoutesTest.php` (nuovo) | basso | auto | **provato nei due versi**: cambiando `OA_PATH` in un percorso inesistente, il test lo nomina e fallisce |

## Onda 2 — le costanti condivise

Prima le condivise: metterle dopo significherebbe scrivere due volte le stesse costanti, prima nei
file e poi nella base.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TOA02 | **fatto** (2026-08-13) | **`F6`–`F8`, `D1`** — `OA_DESC_PROVIDER_ID`, `OA_DESC_ROLE_ID`, `OA_DESC_USER_ID` nel controller base, accanto alle `OA_DESC_MSG_*`. Erano 17 occorrenze su 4 file: una costante per file avrebbe chiuso i rilievi lasciando tre copie della stessa stringa | `app/Http/Controllers/Controller.php`, i quattro controller | basso | auto | `grep -rc 'description: "Provider id"' app/Http/Controllers/` → 0; idem per gli altri due; specifico **identico** |

## Onda 3 — le costanti locali, un controller per volta

Indipendenti fra loro: ognuno chiude i rilievi del suo file. Ordinati per numero di occorrenze.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TOA03 | **fatto** (2026-08-13) | **`F5`** — il tag `"Provider User Roles"` (×5) è ora `self::OA_TAG` | `ProviderUserRoleController.php` | basso | auto | il literale compare una volta sola, nella dichiarazione |
| TOA04 | **fatto** (2026-08-13) | **`F2`** — `OA_PATH = "/api/v1/provider-user-roles"`, e `self::OA_PATH . "/{id}"` per la singola risorsa: **una** costante per due percorsi, così resta visibile che sono la stessa rotta | `ProviderUserRoleController.php` | basso | auto | il literale col `{id}` non compare più |
| TOA05 | **fatto** (2026-08-13) | **`F1`** — stessa forma per `/api/v1/providers`, più le descrizioni passate alla costante condivisa | `ProviderController.php` | basso | auto | idem |
| TOA06 | **fatto** (2026-08-13) | **`F3`** — `/api/v1/roles` | `RoleController.php` | basso | auto | idem |
| TOA07 | **fatto** (2026-08-13) | **`F4`** — `/api/v1/users` | `UserController.php` | basso | auto | idem |
| TOA08 | **fatto** (2026-08-13) | Chiusura: `./scripts/openapi-spec-diff.sh confronta` dice **«Specifico OpenAPI IDENTICO»** dopo tutte le modifiche. È l'unica prova che il refactoring non ha cambiato la documentazione — nessun test la guarda, e il rischio di questa tranche era quello | nessuno | medio | auto | lo script esce `0`; e la suite è a **67 verdi** |

## Cosa questo piano non copre

- **La direzione inversa del controllo** — «ogni rotta è documentata»: **non si fa**, ed è una
  scelta. Gli stessi controller servono anche le rotte interne `admin/v1/…`, e tre rotte `api/v1`
  non sono documentate di proposito (`F14`). Un controllo che segnala il corretto si smette di
  leggere. `TOA09` copre la sola direzione che è un invariante.
- **`"Provider user role not found"`**: non è un'annotazione ma un messaggio di risposta, sta in
  [v4](../20260812-static-analysis-findings-v4/action-plan.md).
- La **traduzione** delle descrizioni OpenAPI: restano in inglese, ed è corretto (`F11`).
