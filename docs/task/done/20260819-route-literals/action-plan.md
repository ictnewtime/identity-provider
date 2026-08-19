# Piano — literali di rotta duplicati

Sigla dichiarata dall'analisi: `TRL` — qui non si ridichiara.

Stato: **chiuso** (2026-08-19) — 6 punti fatti, 1 scartato · Data: 2026-08-19 · Analisi: [analysis.md](./analysis.md), che questo piano
**cita e non ripete**. `F…` e `D…` puntano lì. V — `auto`: lo stabilisce un comando · `man`: lo legge
una persona.

**Il metodo di verifica è uno solo per tutti i punti**, e va preparato **prima** del primo:
`./scripts/openapi-spec-diff.sh salva` e `php artisan route:list > /tmp/route-list.prima`. Alla fine,
`confronta` e un `diff` sulla lista delle rotte devono essere **vuoti**: è un lavoro a somma zero sul
funzionamento, e tutto il rischio sta nel non accorgersi di averlo cambiato.

## Onda 1 — quello che si toglie senza decidere niente

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TRL01 | **fatto** (2026-08-19) | **`[0-9]+` × 11: la risposta era già dentro lo stesso file.** L'analisi diceva «nel file accanto»; misurando si è visto meglio: `routes/api.php` usava **già** `->whereNumber("id")` **12 volte**, e `->where(["id" => "[0-9]+"])` altre 11. Un file solo, due modi, e il rilievo arrivava sul modo minoritario. Ora è uno solo. Nessuna costante aggiunta: il literale non c'è più | `routes/api.php` (11 righe, −17 +11) | basso | auto | `grep -c '\[0-9\]+' routes/api.php` → **0**; e la prova che il vincolo non è cambiato **non** è `route:list`, che nel suo JSON i vincoli non li espone: è la lettura a runtime di `$route->wheres`, che su tutt'e 11 le rotte dà `{"id":"[0-9]+"}`, identico a prima |
| TRL03 | **fatto** (2026-08-19) | `OA_PATH_ID = self::OA_PATH . "/{id}"` nei tre controller, valutata a compilazione, al posto delle 3+3+3 concatenazioni (`F4`). **Non copre `ProviderUserRoleController`**, che ha le stesse 3 occorrenze ma non compare nell'elenco dei rilievi: aggiungerlo era fuori dal punto approvato, e sta qui perché si sappia — è una riga di lavoro, non una scoperta | `ProviderController.php`, `RoleController.php`, `UserController.php` | basso | auto | `./scripts/openapi-spec-diff.sh confronta` → **«Specifico OpenAPI IDENTICO»**, cioè la documentazione generata non è cambiata di un carattere |
| TRL04 | **fatto** (2026-08-19) | `OA_DESC_PROVIDER_USER_ROLE_ID` in `Controller.php`, **quarta di una serie**: sta sulla riga sotto `OA_DESC_USER_ID`, con la stessa visibilità e la stessa forma delle tre che `findings-v3` aveva messo (`F6`). Le 3 `description: "Provider user role id"` del controller (righe 207, 245, 329) ora la usano | `Controller.php`, `ProviderUserRoleController.php` | basso | auto | `openapi-spec-diff.sh confronta` → «IDENTICO». **Provato nei due versi**: cambiando la costante in «…role ID», lo script stampa il `diff` sulle righe 617 e seguenti — quindi il «IDENTICO» di prima non è un controllo che passa sempre. Chiude 1 rilievo |
| TRL07 | **fatto** (2026-08-19) | `OA_PATH_ID` anche in `ProviderUserRoleController` (righe 197, 235, 319): il quarto controller, che l'elenco dei rilievi non nominava. Ora i quattro sono scritti allo stesso modo, e `grep -rn 'OA_PATH \. "/{id}"' app/Http/Controllers/` non trova **più niente** in tutto il progetto | `ProviderUserRoleController.php` | basso | auto | stessa istantanea di `TRL04` — i due punti aprono lo stesso file e si verificano insieme: specifico OpenAPI identico, e nessun residuo della concatenazione |
| TRL05 | **fatto** (2026-08-19) — **dal developer** | `private const ROUTE_ADMIN_V1_PUR` al posto delle 5 occorrenze di `"/admin/v1/provider-user-roles/"` (`F7`). **Controllato**: il literale compare una volta sola, tutte e cinque le chiamate usano la costante — comprese le due del test sulla lingua, che era il punto dove si dimentica — e nel file non resta nessun altro percorso scritto a mano. Nota di stile, non un difetto: le altre costanti del file si chiamano `CHIAVE` e `ID_INESISTENTE`, mentre `PUR` è un'abbreviazione da decifrare | `tests/Feature/ProviderUserRoleNotFoundTest.php` | basso | auto | i 5 test del file passano; `grep '"/admin\|"/api'` trova **solo** la riga della costante. Chiude 1 rilievo |

## Onda 2 — i percorsi, con la strada scelta (`D1`(a))

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TRL02 | **fatto** (2026-08-19) | **`D1`(a)** — `App\Support\RoutePaths` con le cinque costanti, usate da tutt'e due i file di rotta: **23 literali sostituiti in `api.php` e 15 in `web.php`**, più di quelli segnalati perché il rilievo contava solo le forme esatte. Gli URL non si toccano. La classe **dichiara ciò che la costante nasconde**: i due file stanno sotto prefissi diversi, quindi la stessa costante produce due indirizzi. Le varianti `{id}/restore` e `{id}/roles` restano literali: sono stringhe diverse, e nessuna si ripete abbastanza da essere un problema — dar loro un nome avrebbe battezzato qualcosa che non è duplicato | `app/Support/RoutePaths.php` (nuovo), `routes/api.php`, `routes/web.php` | medio | auto | `route:list` **identico su tutti i campi** — 108 rotte, nessuna sparita, nessuna nuova, nessun campo diverso — e i **vincoli**, che quel JSON non espone, confrontati a parte rotta per rotta leggendo `$route->wheres` con i file di prima e di dopo: **identici**. Chiude 9 rilievi |
| TRL06 | **scartato** (2026-08-19) | Verificare se il vincolo per rotta regga su un `Route::pattern("id", "[0-9]+")` globale. **`D3`: niente pattern globale**, quindi la verifica non ha più un consumatore — e una verifica che nessuno userà è rumore, non lavoro. **Resta scritto il perché**: `{id}` compare 46 volte nei file di rotta e non è sempre un numero — `sessions/{id}` è un **uuid**, col commento `// id is uuid` nel codice (`F8`). Chi un giorno proponesse il pattern globale parta da lì | — | — | — | — |

## Cosa questo piano non copre

- **Il resto del quality gate** (`D4`, **risposto**): i tredici sono i rilievi di **questa** regola, e
  il confine è quello — **chiusi i punti, il task va in `done/`**. Se il gate resta rosso per un'altra
  regola, quella avrà il suo task: un piano risponde dei propri punti, non dello stato complessivo
  dello strumento.
- **Il workflow della pipeline**: non si tocca (decisione del 2026-08-12).
- **Le rotte `client/v1/…`**: non compaiono nei rilievi e non si riorganizzano per simmetria. Un
  lavoro non chiesto è un rischio non chiesto.
