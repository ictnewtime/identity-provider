# Piano — eccezioni dedicate e attese fisse

Sigla `TEW`. L'analisi e' in [analysis.md](./analysis.md) e questo piano **la cita, non la ripete**:
i sei rilievi, il breaking change nei test e le alternative scartate stanno la'.

**Priorita' bassa**: sei rilievi di severita' media, nessuno rompe qualcosa che funziona. Il lotto si
lavora quando non c'e' altro davanti.

**Chiuso il 2026-08-21 con una verifica sospesa, e va detto qui perche' e' la prima cosa che si legge**:
`TEW06` e `TEW07` sono **scritti e mai eseguiti**. I due spec Cypress non si possono lanciare senza il
container dei test E2E — task [20260812-e2e-test-container](../../todo/20260812-e2e-test-container/action-plan.md) —
oppure senza il permesso di girare contro l'ambiente di sviluppo, che creerebbe utenti in `idp_develop`.
**Decisione del developer: si provano in futuro**, quando il container esiste. Cio' che si e' potuto
verificare senza eseguire sta nelle due righe dei punti.

**Le quattro decisioni sono chiuse** (2026-08-21, § 4 dell'analisi): eccezioni che estendono
`RuntimeException`, **una classe per modulo**, test stretti alla classe nuova, e i due punti fuori dal
report dentro questo lotto. Nessun punto resta in attesa di una risposta.

**I punti sono in ordine di dipendenza**: prima le classi (`TEW01`), poi chi le usa (`TEW02` `TEW03`),
poi i test che le pretendono (`TEW04`). Il ternario e le attese sono indipendenti da tutto il resto.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TEW01 | **fatto** (2026-08-21) | Le due classi in `app/Exceptions/`, cartella che **non esisteva**: `DestructiveDatabaseException` e `SeedingException`, entrambe `extends RuntimeException` (`D1`), una per **modulo** (`D2`). Il docblock di ognuna porta il perché della scelta e — in `SeedingException` — l'avvertenza che i messaggi nominano la **variabile** `SEED_ADMIN_PASSWORD` e non il suo valore: un'eccezione finisce nei log, e un log non è il posto di una credenziale | nuovi `app/Exceptions/DestructiveDatabaseException.php`, `app/Exceptions/SeedingException.php` | basso | auto | `php -l` pulito su entrambe e suite **96 passed**, perché nessuno le usava ancora |
| TEW02 | **fatto** (2026-08-21) | I due `throw` della guardia passano a `DestructiveDatabaseException`, e con loro il `@throws` del docblock — un commento che nomina la classe sbagliata è peggio di nessun commento | `app/Support/DestructiveDatabaseGuard.php` | basso | auto | `grep -c RuntimeException` → **0**; suite **96 passed** **senza toccare i test**, che è la conseguenza di `D1` messa alla prova: la sottoclasse soddisfa `expectException(RuntimeException::class)` |
| TEW03 | **fatto** (2026-08-21) | I due `throw` di `DatabaseSeeder` passano a `SeedingException` | `database/seeders/DatabaseSeeder.php` | basso | auto | `grep -c RuntimeException` → **0**; suite **96 passed** |
| TEW04 | **fatto** (2026-08-21) | I sei riferimenti dei due file di test stretti alla classe nuova — `expectException` e `catch` — più i due `use`. Senza questo il lotto non avrebbe verifica: con una sottoclasse `expectException(RuntimeException::class)` passa comunque | `tests/Unit/DestructiveDatabaseGuardTest.php`, `tests/Feature/DatabaseSeederTest.php` | basso | auto | **prova al contrario, eseguita**: rimesso `\RuntimeException` in un `throw` della guardia e in uno del seeder, la suite è diventata **rossa** in entrambi i file (`FAIL Tests\Unit\DestructiveDatabaseGuardTest`, `FAIL Tests\Feature\DatabaseSeederTest`); ripristinato, **96 passed (182 assertions)**. Adesso i test verificano la parte nuova, non solo che qualcosa venga lanciato |
| TEW05 | **fatto** (2026-08-21) | Il ternario annidato di `app/Traits/CustomAuditable.php:71-75` è diventato un `if` con due `return`: la condizione esterna è «`deleted_at` è cambiato», e sotto resta un ternario **semplice** fra le due uscite, «deleted» e «restored». Nessun cambio di comportamento. Il commento che ho aggiunto dice la cosa che il codice non poteva dire: è il **valore nuovo** del campo a distinguere i due casi — pieno, il modello è stato cancellato; vuoto, è tornato | `app/Traits/CustomAuditable.php` | basso | auto | `php -l` pulito e suite **96 passed (182 assertions)**. I tre rami toccati hanno un test ciascuno, col nome che lo dice: `test_a_soft_delete_writes_deleted`, `test_a_restore_writes_restored`, `test_a_force_delete_writes_force_deleted` — quindi la verifica non è «la suite è verde», è «le tre uscite sono asserite». Nel file non restano ternari annidati: le tre righe con due `?` sono `??`, non ternari (`:112`, `:150`, `:170`) |
| TEW06 | **fatto, non eseguito** (2026-08-21) | `cypress/e2e/user/search-accented.cy.js`: al posto dell'attesa fissa, `cy.intercept` sulla ricerca e `cy.wait("@ricercaUtenti")` con l'asserzione sul `200`. **L'intercettazione filtra sul parametro `q`**, e non è pignoleria: il salvataggio del form qui sopra ricarica la lista con lo **stesso indirizzo** e `q` vuoto, quindi un filtro solo sull'URL avrebbe chiuso l'attesa su quella richiesta — la stessa fragilità di prima, vestita meglio. Il debounce di 500 ms resta: è dell'applicazione (`UserTable.vue:90-95`), non del test. **Quei 500 ms non proteggevano dal caricamento della pagina**: non c'è SSR quindi non c'è hydration, le pagine non sono chunk separati e `cy.get("#user-search")` aspetta già il montaggio — misurato, § 4 dell'analisi | `cypress/e2e/user/search-accented.cy.js` | basso | man | **quello che ho potuto verificare**: `node --check` pulito; la forma `{ query: { q } }` esiste nel Cypress installato (`net-stubbing.d.ts:385`, versione 15.15.0); l'indirizzo intercettato è quello che l'applicazione chiama (`UserTable.vue:51`); nel file non resta nessuna attesa a numero. **Quello che manca**: eseguire il test. Serve il container Cypress (task `TEC`, non esiste) oppure il permesso di lanciarlo contro l'ambiente di sviluppo — creerebbe un utente in `idp_develop` e richiede `cypress.env.json`, che oggi non c'è. Per questo lo stato dice **non eseguito** |
| TEW07 | **fatto, non eseguito** (2026-08-21) | **`D3`, metà Cypress**: il `cy.wait(500)` di `cypress/e2e/user/crud-user.cy.js:24` sta dentro l'helper `searchUser`, che **sei test** chiamano. Diventa un'intercettazione filtrata su `q` più l'attesa sulla risposta. Due dettagli che il numero nascondeva: il `clear()` fa partire una richiesta con `q` **vuoto** — senza filtro l'attesa si chiuderebbe su quella — e sei chiamate con lo **stesso alias** sarebbero sei rotte indistinguibili, quindi l'alias è numerato da un contatore | `cypress/e2e/user/crud-user.cy.js` | basso | man | `node --check` pulito; in tutto `cypress/` non resta **nessuna** attesa a numero (`grep -rnE "^[^/*]*cy\.wait\([0-9]"` → vuoto). **Non eseguito**, come `TEW06`: serve il container Cypress (`TEC`) o il permesso di lanciarlo sull'ambiente di sviluppo |
| TEW09 | **fatto** (2026-08-21) | Le tre `throw` di `E2EUserSeeder` passano a `SeedingException`. Era bloccato da `TEW01`: chiuso quello, il blocco è caduto e il punto è stato eseguito subito, perché era già approvato | `database/seeders/E2EUserSeeder.php` | basso | auto | `grep -c RuntimeException` → **0**; suite **96 passed** |
| TEW08 | **chiuso dal developer** (2026-08-21) | La conferma dal report. **Il report l'ha guardato il developer, non l'agente**: qui non c'è una misura mia, c'è la sua parola — e va detto, perché è l'unico punto del lotto senza un comando che lo dimostri | nessuno (verifica) | basso | man | il developer ha chiuso il punto dopo aver letto il report |

## Cosa questo piano non copre

- **Le altre `RuntimeException` del progetto**, se ce ne sono fuori da `app/Support`, `database/seeders`
  e dai punti qui sopra: il lotto chiude i rilievi e i due fuori-report della stessa forma, non fa una
  passata su tutto l'albero. Il conto lo dà `grep -rn "new RuntimeException" app/ database/`.
- **Il rendere eseguibili i test Cypress**: `TEW06` e la metà Cypress di `TEW07` si scrivono adesso ma
  si verificano quando esiste il container, che è il task
  [20260812-e2e-test-container](../../todo/20260812-e2e-test-container/action-plan.md). Finché non c'è, il loro
  stato può arrivare a `fatto` solo per la parte scritta, e va detto.

## Perf/leak — la dichiarazione della policy per i punti chiusi

Policy dell'organizzazione, voce per voce. I punti chiusi hanno toccato: **un ramo di una funzione
pura** (`TEW05` — `resolveAction()` riceve modello, azione e campi cambiati e restituisce una stringa),
**due classi di eccezione senza corpo** (`TEW01`), **sette `throw`** (`TEW02` `TEW03` `TEW09`), **sei
righe di test** (`TEW04`) e **due file Cypress** (`TEW06` `TEW07`). Nessun service, nessuna API
Resource, nessuna query nuova.

| Voce | Esito | Perché |
|---|---|---|
| Query N+1 | non applicabile | nessuna query aggiunta né spostata. In `TEW05` `$model->deleted_at` è un attributo già caricato e `in_array` guarda un array in memoria; un `throw` non interroga niente |
| Data leakage | **verificato applicabile, e pulito** | le eccezioni finiscono nei **log**, e i loro messaggi sono lunghi perché portano il rimedio. Riletti tutti e sette: nominano la **variabile** `SEED_ADMIN_PASSWORD` e la costante `TEST_ALLOWED_DATABASES`, il nome del database in uso e quelli consentiti — mai il **valore** di una credenziale. L'avvertenza è scritta nel docblock di `SeedingException`, dove la leggerà chi aggiunge il prossimo messaggio |
| Scope/tenant | non applicabile | niente di ciò che è stato toccato interroga o filtra per utente, provider o organizzazione |
| Memory/streaming | non applicabile | stringhe e un array di nomi di campo, già in memoria |
| Query non vincolate | **verificato** | l'unica query nel perimetro è `assertNotAlreadySeeded()`: `User::where("username", …)->exists()`, che è un `LIMIT 1` su una colonna **unica** — non è cresciuta e non è stata toccata |

Resta fuori solo `TEW08`, che è una lettura del report e non tocca codice.

Nota generale: questo lotto **non tocca nessun service e nessuna API** — due
classi di eccezione, quattro `throw`, un `if` e due attese di test.
