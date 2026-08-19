# Analisi — literali di rotta duplicati, rilievi `high` di SonarQube

**Identificatori**: `TRL` = task route-literals

Stato: da approvare · Data: 2026-08-19

## 1. Obiettivo

Chiudere i **tredici** rilievi `high` di SonarQube della forma *«Define a constant instead of
duplicating this literal … N times»*. Riguardano tre posti: i file di rotta, tre controller e un file
di test.

Perché adesso: sono `high` e il gate della pipeline aspetta l'esito
(`-Dsonar.qualitygate.wait=true`, [deploy-staging.yml](../../../../.github/workflows/deploy-staging.yml)).

Ma l'obiettivo **non è mettere tredici costanti**. Il rilievo dice «una costante» perché è l'unico
rimedio che uno strumento statico sa nominare; in almeno due casi su tre la duplicazione si **toglie**
invece di battezzarla, e in uno la risposta è già scritta nel file accanto. Una costante che nasconde
undici chiamate identiche fa sparire il rilievo e lascia il lavoro.

## 2. Situazione attuale

### I conteggi, verificati

Ogni riga è stata ricontata sul codice, non presa dal report.

| # | Fatto | Prova |
|---|---|---|
| F1 | `[0-9]+` compare **11 volte** in `routes/api.php` e **zero** in `routes/web.php` | `grep -o '\[0-9\]+' routes/api.php \| wc -l` → `11` |
| F2 | **`->whereNumber("id")` era già in uso, e non solo in `web.php`: dentro `api.php` stesso, 12 volte** — contro le 11 di `[0-9]+`. Un file solo, due modi, e il rilievo arrivava sul modo minoritario. *(Precisato il 2026-08-19 implementando `TRL01`: la prima stesura diceva che il confronto era fra i due file.)* | `git show HEAD:routes/api.php \| grep -c 'whereNumber("id")'` → `12` |
| F3 | I percorsi si ripetono in tutt'e due i file: `providers/{id}` 5 volte in `api.php` e 3 in `web.php`; `roles/{id}` 6 e 3; `users/{id}` 6 e 3; `provider-user-roles/{id}` 6 e 3; `parameters/{id}` 3 in `web.php` | `grep -oF '"…"' routes/*.php \| wc -l`, uno per percorso |
| F4 | Nei tre controller `self::OA_PATH . "/{id}"` compare **3 volte ciascuno** — `RoleController:226,271,353`, `ProviderController:129,275,395`, `UserController:227,272,411` | `grep -n 'OA_PATH \. "/{id}"'` |
| F5 | `"Provider user role id"` compare **3 volte** in `ProviderUserRoleController` | `grep -c` → `3` |
| F6 | Le costanti gemelle **esistono già**: `Controller.php:53-55` ha `OA_DESC_PROVIDER_ID`, `OA_DESC_ROLE_ID`, `OA_DESC_USER_ID`, messe dal task [findings-v3](../../done/20260812-static-analysis-findings-v3/action-plan.md). Manca la quarta | `app/Http/Controllers/Controller.php:53-55` |
| F7 | `"/admin/v1/provider-user-roles/"` compare **5 volte** in `tests/Feature/ProviderUserRoleNotFoundTest.php` | `grep -oF … \| wc -l` → `5` |

### La trappola: non tutti gli `{id}` sono numeri

| # | Fatto | Prova |
|---|---|---|
| F8 | `{id}` compare **46 volte** nei file di rotta, ma **non tutti sono interi**: `sessions/{id}` è un **uuid**, e ha il suo vincolo esplicito — nel codice c'è pure il commento `// id is uuid` | `routes/web.php:136-141` |
| F9 | Quindi un `Route::pattern("id", "[0-9]+")` globale — la scorciatoia che toglierebbe tutte e 11 le occorrenze in un colpo — **tocca anche quella rotta**. Il vincolo locale ha la precedenza sul pattern globale, ma è una precedenza da **verificare**, non da dare per buona: se sbaglia, la cancellazione di una sessione risponde 404 e nessun test lo copre | `routes/web.php:138` |

### Dipendenze e breaking change

- **Le annotazioni OpenAPI generano lo specifico**: toccare `path:` nei controller non deve cambiare
  di una virgola il file generato. Lo strumento per dimostrarlo **esiste già**:
  `./scripts/openapi-spec-diff.sh salva` prima, `confronta` dopo — è nato per lo stesso motivo nel
  task `findings-v3`.
- **I file di rotta definiscono gli URL**: qualunque riscrittura deve lasciare `php artisan route:list`
  **identico**. È l'unica prova che conta, perché un prefisso sbagliato non rompe il codice: cambia
  l'indirizzo, e il test lo scopre solo se quell'indirizzo è testato.
- **Nessun cambiamento di comportamento è previsto**: è un lavoro a somma zero sul funzionamento, e
  tutto il rischio sta nel non accorgersi di averlo cambiato.

## 3. Analisi

I tredici rilievi sono di tre nature diverse, e vanno trattati diversamente.

### (1) `[0-9]+` — la risposta è già nel repository

`routes/web.php` scrive `->whereNumber("id")`; `routes/api.php` scrive
`->where(["id" => "[0-9]+"])`. Fanno la stessa cosa. Allineando `api.php` al file accanto, le 11
occorrenze **spariscono** — non una costante che le nomina: proprio il literale non c'è più. È il caso
in cui il rilievo si chiude scrivendo **meno** codice, e il repository conteneva già la risposta.

### (2) I percorsi `X/{id}` — costante o struttura

Due strade:

| Strada | Cosa comporta | Perché sì / perché no |
|---|---|---|
| **(a) Una costante per percorso** — **SCELTA dal developer il 2026-08-19** | una classe `App\Support\RoutePaths` con `PROVIDERS_ID = "providers/{id}"` e le altre quattro, usata dai due file di rotta | È ciò che il rilievo chiede, ed è la strada con **meno superficie**: nessun URL viene riscritto, quindi non c'è modo di spostare un indirizzo per sbaglio. **Il costo, registrato**: i due file hanno prefissi diversi (`api/v1/…` e `admin/v1/…`) e la costante non lo dice, quindi resta vero che la stessa stringa vive in due alberi — semplicemente, ora ha un nome solo |
| **(b) Un gruppo per risorsa** — **scartata** | `Route::prefix("providers")->group(...)` e dentro `Route::put("{id}", …)` | Toglieva la duplicazione invece di nominarla, ma riscriveva gli URL di tutta l'area amministrativa: rischio alto per un rilievo di stile. Era il mio consiglio; il developer ha scelto il rischio più basso |

### (3) Le annotazioni e il test — qui la costante è giusta

`self::OA_PATH . "/{id}"` e `"Provider user role id"` sono **descrizioni**, non struttura: non c'è
niente da togliere, solo un nome da dare. E per la seconda il posto è già deciso e già usato — accanto
alle tre gemelle in `Controller.php` (`F6`). Lo stesso vale per il test: una `private const` nella
classe, che è dove stanno le costanti di un test.

### Codice cancellato

Le 11 chiamate `->where(["id" => "[0-9]+"])` di `api.php`. Se si sceglie (2b), anche i prefissi
ripetuti dentro ogni definizione di rotta.

## 4. Da decidere

### Vincoli

- ~~**`D1`**~~ — **risposta del 2026-08-19: (a), una costante per percorso.** Gli URL non si toccano.
- ~~**`D2`**~~ — **decade con `D1`**: riguardava solo la riorganizzazione in gruppi, che non si fa.

### Conflitti

- ~~**`D3`**~~ — **risposta del 2026-08-19: niente pattern globale.** Si sta sui vincoli per rotta:
  toglierebbe 11 righe mettendo in gioco una rotta che non c'entra (`F8`), per un guadagno che
  `whereNumber()` dà già senza rischio. Il punto che doveva verificarlo è **scartato**: una verifica
  che nessuno userà non è lavoro, è rumore.

### Ignoto

- ~~**`D4`**~~ — **risposta del 2026-08-19: sono solo quelli di questa regola.** E la conseguenza la
  decide il developer: **il task si chiude quando i suoi punti sono chiusi**, e si sposta in `done/`.
  Che il gate resti rosso per altre regole non è affare di questo task — quelle avranno il loro.

## 5. Consigli

- ~~**`D1` → (2b)**~~ — **il developer ha scelto (a)**, e la scelta pesa il rischio invece dello stile:
  (2b) riscriveva gli URL di tutta l'area amministrativa per chiudere un rilievo di duplicazione. Il
  consiglio resta scritto perché il giorno che quei file si riorganizzino per un altro motivo, i
  gruppi sono la forma da usare — ma non è oggi, e non per questo.
- ~~**`D2`**~~ — decaduta con `D1`.
- **`D3` → niente pattern globale.** Toglierebbe 11 righe e metterebbe in gioco una rotta che non
  c'entra, per un guadagno che `whereNumber()` dà già senza rischio.
- ~~**`D4` → chiedere il report completo**~~ — **il developer ha deciso diversamente, e ha ragione sul
  confine**: un task risponde dei propri punti, non dello stato complessivo del gate. Chiusi i punti,
  va in `done/`. Se il gate resta rosso per un'altra regola, quello è un altro task — e questa volta
  la domanda non resta aperta: **la risposta è che non è una domanda di questo task**.
- **Prima di toccare qualunque cosa**: `./scripts/openapi-spec-diff.sh salva` e
  `php artisan route:list > /tmp/route-list.prima`. Senza le due fotografie, «non è cambiato niente»
  è un'opinione.
