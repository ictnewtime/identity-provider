# Piano — eccezioni dedicate e attese fisse

Sigla `TEW`. L'analisi e' in [analysis.md](./analysis.md) e questo piano **la cita, non la ripete**:
i sei rilievi, il breaking change nei test e le alternative scartate stanno la'.

**Priorita' bassa**: sei rilievi di severita' media, nessuno rompe qualcosa che funziona. Il lotto si
lavora quando non c'e' altro davanti.

**Le quattro decisioni sono chiuse** (2026-08-21, § 4 dell'analisi): eccezioni che estendono
`RuntimeException`, **una classe per modulo**, test stretti alla classe nuova, e i due punti fuori dal
report dentro questo lotto. Nessun punto resta in attesa di una risposta.

**I punti sono in ordine di dipendenza**: prima le classi (`TEW01`), poi chi le usa (`TEW02` `TEW03`),
poi i test che le pretendono (`TEW04`). Il ternario e le attese sono indipendenti da tutto il resto.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TEW01 | da approvare | Le due classi nuove in `app/Exceptions/`, cartella che **oggi non esiste**: `DestructiveDatabaseException` e `SeedingException`, entrambe `extends RuntimeException` (`D1`) e una per **modulo** (`D2`). Solo le classi, senza cambiare chi lancia: cosi' il punto dopo e' un cambio di una parola per riga | nuovi `app/Exceptions/DestructiveDatabaseException.php`, `app/Exceptions/SeedingException.php` | basso | auto | `php -l` su entrambe, e la suite resta **96 passed** perche' nessuno le usa ancora |
| TEW02 | da approvare | I due `throw` della guardia passano a `DestructiveDatabaseException` — `app/Support/DestructiveDatabaseGuard.php:41` e `:53`. Il `@throws` del docblock (`:31`) cambia con loro, altrimenti il commento mente | `app/Support/DestructiveDatabaseGuard.php` | basso | auto | `grep -c "RuntimeException" app/Support/DestructiveDatabaseGuard.php` → 0; suite **96 passed** senza toccare i test, perche' la sottoclasse soddisfa `expectException(RuntimeException::class)` |
| TEW03 | da approvare | I due `throw` del seeder passano a `SeedingException` — `database/seeders/DatabaseSeeder.php:38` e `:60` | `database/seeders/DatabaseSeeder.php` | basso | auto | `grep -c "RuntimeException" database/seeders/DatabaseSeeder.php` → 0; suite **96 passed** |
| TEW04 | da approvare | **`D4` — i test si stringono alla classe nuova**, sennò il punto non ha verifica: `expectException(RuntimeException::class)` passerebbe anche se domani qualcuno rimettesse la generica. Sette punti in due file: `tests/Unit/DestructiveDatabaseGuardTest.php:55,68,85` e `tests/Feature/DatabaseSeederTest.php:134,149,183` | `tests/Unit/DestructiveDatabaseGuardTest.php`, `tests/Feature/DatabaseSeederTest.php` | basso | auto | **prova al contrario**: rimesso `RuntimeException` in un `throw`, la suite deve diventare **rossa**; rimesso a posto, **96 passed** |
| TEW05 | **fatto** (2026-08-21) | Il ternario annidato di `app/Traits/CustomAuditable.php:71-75` è diventato un `if` con due `return`: la condizione esterna è «`deleted_at` è cambiato», e sotto resta un ternario **semplice** fra le due uscite, «deleted» e «restored». Nessun cambio di comportamento. Il commento che ho aggiunto dice la cosa che il codice non poteva dire: è il **valore nuovo** del campo a distinguere i due casi — pieno, il modello è stato cancellato; vuoto, è tornato | `app/Traits/CustomAuditable.php` | basso | auto | `php -l` pulito e suite **96 passed (182 assertions)**. I tre rami toccati hanno un test ciascuno, col nome che lo dice: `test_a_soft_delete_writes_deleted`, `test_a_restore_writes_restored`, `test_a_force_delete_writes_force_deleted` — quindi la verifica non è «la suite è verde», è «le tre uscite sono asserite». Nel file non restano ternari annidati: le tre righe con due `?` sono `??`, non ternari (`:112`, `:150`, `:170`) |
| TEW06 | da approvare | `cypress/e2e/user/search-accented.cy.js:50`: al posto di `cy.wait(500)`, `cy.intercept("GET", "/admin/v1/users*").as("ricercaUtenti")` prima di digitare e `cy.wait("@ricercaUtenti")` dopo. Il debounce di 500 ms resta — è dell'applicazione (`UserTable.vue:90-95`), non del test. **Quei 500 ms non proteggevano dal caricamento della pagina** come si poteva pensare: non c'è SSR, quindi non c'è hydration, le pagine non sono chunk separati e `cy.get("#user-search")` aspetta già il montaggio — misurato, § 4 dell'analisi | `cypress/e2e/user/search-accented.cy.js` | basso | man | il test passa; e **rallentando la risposta** (`cy.intercept` con `delay`) passa ancora, dove con l'attesa fissa fallirebbe. Serve il container Cypress, che non c'è ancora (task `TEC`) |
| TEW07 | da approvare | **`D3`** — gli stessi due difetti fuori dal report: le tre `RuntimeException` di `database/seeders/E2EUserSeeder.php:41,49,88` passano a `SeedingException`, e il `cy.wait(500)` di `cypress/e2e/user/crud-user.cy.js:24` (dentro `searchUser`) diventa un'attesa sull'intercettazione | `database/seeders/E2EUserSeeder.php`, `cypress/e2e/user/crud-user.cy.js` | basso | auto | `grep -rn "RuntimeException\|cy.wait([0-9]" database/seeders/ cypress/` non trova più niente; suite **96 passed** |
| TEW08 | da approvare | La conferma dal report: i sei rilievi non compaiono più, e non ne sono comparsi altri sulle stesse regole (`S112` per le eccezioni, il ternario annidato, l'attesa fissa) | nessuno (verifica) | basso | man | il report non elenca più i sei; se ne restano, il numero e il perché stanno scritti qui |

## Cosa questo piano non copre

- **Le altre `RuntimeException` del progetto**, se ce ne sono fuori da `app/Support`, `database/seeders`
  e dai punti qui sopra: il lotto chiude i rilievi e i due fuori-report della stessa forma, non fa una
  passata su tutto l'albero. Il conto lo dà `grep -rn "new RuntimeException" app/ database/`.
- **Il rendere eseguibili i test Cypress**: `TEW06` e la metà Cypress di `TEW07` si scrivono adesso ma
  si verificano quando esiste il container, che è il task
  [20260812-e2e-test-container](../20260812-e2e-test-container/action-plan.md). Finché non c'è, il loro
  stato può arrivare a `fatto` solo per la parte scritta, e va detto.

## Perf/leak — la dichiarazione della policy per `TEW05`

Policy dell'organizzazione, voce per voce. `TEW05` ha riscritto **un ramo di una funzione pura** —
`resolveAction()` riceve modello, azione e campi cambiati e restituisce una stringa — dentro un trait
che scrive gli audit.

| Voce | Esito | Perché |
|---|---|---|
| Query N+1 | non applicabile | nessuna query aggiunta né spostata: `$model->deleted_at` è un attributo già caricato, `in_array` guarda un array in memoria |
| Data leakage | non applicabile | non passa da nessuna API Resource, e il valore che esce è una delle quattro stringhe d'azione |
| Scope/tenant | non applicabile | la funzione non interroga niente e non filtra niente |
| Memory/streaming | non applicabile | un array di nomi di campo, già in memoria |
| Query non vincolate | non applicabile | nessuna query |

**Le voci che avranno qualcosa da dire sono quelle dei punti ancora aperti**, e sono due. *Data
leakage*: i messaggi delle eccezioni sono lunghi e portano il rimedio, e vanno riletti per essere certi
che non nominino **valori** di credenziali — oggi nominano la variabile `SEED_ADMIN_PASSWORD`, non il suo
contenuto, ed è la forma giusta da conservare. *Query non vincolate*: `assertNotAlreadySeeded()` fa una
query, un `where(...)->exists()` sullo username, che è indicizzato.

Nota generale che indirizza il resto del lavoro: questo lotto **non tocca nessun service e nessuna API** — due
classi di eccezione, quattro `throw`, un `if` e due attese di test.
