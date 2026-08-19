# Piano — literali di rotta duplicati

Sigla dichiarata dall'analisi: `TRL` — qui non si ridichiara.

Stato: **da approvare** · Data: 2026-08-19 · Analisi: [analysis.md](./analysis.md), che questo piano
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
| TRL04 | da approvare | **`"Provider user role id"` × 3** (`F5`): `OA_DESC_PROVIDER_USER_ROLE_ID` in `Controller.php`, **accanto alle tre gemelle che ci sono già** (`F6`) — `OA_DESC_PROVIDER_ID`, `OA_DESC_ROLE_ID`, `OA_DESC_USER_ID`, messe da `findings-v3`. Il posto è già deciso: è la quarta di una serie, non una scelta nuova | `Controller.php`, `ProviderUserRoleController.php` | basso | auto | `openapi-spec-diff.sh confronta` vuoto. Chiude 1 rilievo |
| TRL07 | da approvare | **`ProviderUserRoleController`, il quarto controller che l'elenco non nomina.** Ha le stesse 3 occorrenze di `self::OA_PATH . "/{id}"` (righe 197, 235, 319) che `TRL03` ha chiuso negli altri tre, e la stessa forma di `OA_PATH` a riga 19. Non compare fra i rilievi che il developer ha portato: o il report era parziale, o la regola lì non è scattata — in ogni caso la duplicazione c'è, e lasciarla sarebbe tenere tre file su quattro allineati. Punto **suo** e non estensione di `TRL03`, perché quello era approvato su tre file e un punto che cresce dopo l'approvazione non è più quello approvato. Si accompagna bene a `TRL04`, che tocca lo stesso file | `ProviderUserRoleController.php` | basso | auto | `grep -rn 'OA_PATH \. "/{id}"' app/Http/Controllers/` → **nessun risultato**, e `./scripts/openapi-spec-diff.sh confronta` → «Specifico OpenAPI IDENTICO» |
| TRL05 | **fatto** (2026-08-19) — **dal developer** | `private const ROUTE_ADMIN_V1_PUR` al posto delle 5 occorrenze di `"/admin/v1/provider-user-roles/"` (`F7`). **Controllato**: il literale compare una volta sola, tutte e cinque le chiamate usano la costante — comprese le due del test sulla lingua, che era il punto dove si dimentica — e nel file non resta nessun altro percorso scritto a mano. Nota di stile, non un difetto: le altre costanti del file si chiamano `CHIAVE` e `ID_INESISTENTE`, mentre `PUR` è un'abbreviazione da decifrare | `tests/Feature/ProviderUserRoleNotFoundTest.php` | basso | auto | i 5 test del file passano; `grep '"/admin\|"/api'` trova **solo** la riga della costante. Chiude 1 rilievo |

## Onda 2 — i percorsi, con la strada scelta (`D1`(a))

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TRL02 | da approvare | **`D1`(a)** — una costante per percorso in una classe nuova `App\Support\RoutePaths`: `PROVIDERS_ID`, `ROLES_ID`, `USERS_ID`, `PROVIDER_USER_ROLES_ID`, `PARAMETERS_ID`, usate dai due file di rotta al posto dei literali (`F3`). **Gli URL non si toccano**: cambia solo da dove viene la stringa, quindi non c'è modo di spostare un indirizzo per sbaglio — è la ragione della scelta. `parameters/{id}` vive solo in `web.php`, la sua costante serve lo stesso. Chiude 9 rilievi | `app/Support/RoutePaths.php` (nuovo), `routes/api.php`, `routes/web.php` | medio | auto | `diff /tmp/route-list.prima <(php artisan route:list)` **vuoto**, ed è il controllo che vale anche qui: una costante sbagliata cambia l'indirizzo senza rompere niente |
| TRL06 | **scartato** (2026-08-19) | Verificare se il vincolo per rotta regga su un `Route::pattern("id", "[0-9]+")` globale. **`D3`: niente pattern globale**, quindi la verifica non ha più un consumatore — e una verifica che nessuno userà è rumore, non lavoro. **Resta scritto il perché**: `{id}` compare 46 volte nei file di rotta e non è sempre un numero — `sessions/{id}` è un **uuid**, col commento `// id is uuid` nel codice (`F8`). Chi un giorno proponesse il pattern globale parta da lì | — | — | — | — |

## Cosa questo piano non copre

- **Il resto del quality gate** (`D4`, **risposto**): i tredici sono i rilievi di **questa** regola, e
  il confine è quello — **chiusi i punti, il task va in `done/`**. Se il gate resta rosso per un'altra
  regola, quella avrà il suo task: un piano risponde dei propri punti, non dello stato complessivo
  dello strumento.
- **Il workflow della pipeline**: non si tocca (decisione del 2026-08-12).
- **Le rotte `client/v1/…`**: non compaiono nei rilievi e non si riorganizzano per simmetria. Un
  lavoro non chiesto è un rischio non chiesto.
