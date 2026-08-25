# Eccezioni dedicate e attese fisse — sei rilievi di SonarQube

**Identificatori**: `TEW` = task exceptions-and-waits

Stato: da approvare · Data: 2026-08-21

Nato il **2026-08-21** da un elenco di SonarQube passato dal developer. Tutti di severità **medium**:
nessuno rompe qualcosa che funziona, e per questo il lotto e' **priorita' bassa**.

## 1. Obiettivo

Alla fine devono essere vere tre cose.

**`RuntimeException` non compare piu' come eccezione lanciata** nei quattro punti segnalati: al suo
posto stanno eccezioni con un nome che dice **cosa** e' andato storto. Oggi chi vuole intercettare
«il seeder e' gia' stato eseguito» deve prendere `RuntimeException`, che e' la classe di **qualunque**
guasto a runtime: non si puo' distinguere quel caso da un errore di programmazione capitato per caso
nello stesso blocco.

**Il ternario annidato di `CustomAuditable` diventa leggibile**, perche' quello e' il codice che
decide se un audit dira' «deleted» o «restored» — e si legge tre volte prima di capirlo.

**Le due attese fisse dei test Cypress diventano attese su una condizione**: `cy.wait(500)` e' un
numero scelto a occhio che coincide col debounce della ricerca. Non e' un dettaglio di stile: e' un
test che passa o fallisce a seconda di come e' carica la macchina.

**Perche' adesso**: sono sei rilievi che vengono dalla stessa passata, tre di loro toccano codice che
il progetto ha scritto **oggi** per proteggere il database e i dati di partenza, e le due attese
stanno negli unici due test end-to-end che esistono.

## 2. Situazione attuale

| Rilievo | Dove | Cosa c'e' |
|---|---|---|
| eccezione generica | `app/Support/DestructiveDatabaseGuard.php:41` e `:53` | due `throw new RuntimeException`: elenco dei database consentiti assente, e database non consentito |
| eccezione generica | `database/seeders/DatabaseSeeder.php:38` e `:60` | `SEED_ADMIN_PASSWORD` mancante, e database gia' seminato |
| ternario annidato | `app/Traits/CustomAuditable.php:71-75` | `in_array(...) ? (is_null(...) ? "restored" : "deleted") : $originalAction` |
| attesa fissa | `cypress/e2e/user/search-accented.cy.js:50` | `cy.wait(500)` dopo aver digitato nella ricerca |

**Il breaking change c'e', e sta nei test.** Due file di test asseriscono **esattamente**
`RuntimeException`:

- `tests/Unit/DestructiveDatabaseGuardTest.php:55,68,85` — `expectException(RuntimeException::class)`
  e un `catch (RuntimeException $e)`;
- `tests/Feature/DatabaseSeederTest.php:134,149,183` — le stesse due forme.

Se le eccezioni nuove **estendono** `RuntimeException`, quei test restano verdi senza toccarli: una
sottoclasse soddisfa `expectException` e viene presa dal `catch`. Se invece estendono `Exception`,
sette asserzioni diventano rosse e il comportamento cambia anche fuori dai test — chiunque scriva
`catch (RuntimeException)` attorno a un `db:seed` non prenderebbe piu' niente.

**Due punti hanno la stessa forma e non sono nel report**, e vanno decisi assieme agli altri, perche'
correggerne uno e lasciare l'altro e' il modo di ritrovarsi la stessa riga fra un mese:

- `database/seeders/E2EUserSeeder.php:41,49,88` — **tre** `throw new RuntimeException`;
- `cypress/e2e/user/crud-user.cy.js:24` — un secondo `cy.wait(500)`, dentro la funzione `searchUser`.

**Cosa sincronizza davvero la ricerca**, misurato: `UserTable.vue:90-95` fa `clearTimeout` +
`setTimeout(..., 500)` e poi `loadUsers()`, che chiama `GET /admin/v1/users` con `q=`
(`UserTable.vue:51-59`). Quindi il `cy.wait(500)` **coincide con il debounce** e non aspetta la
risposta: sulla macchina lenta la tabella non e' ancora aggiornata quando parte l'asserzione. La
condizione osservabile esiste ed e' quella richiesta: `cy.intercept` sulla rotta, poi
`cy.wait("@alias")`.

## 3. Analisi

**Le eccezioni.** Servono classi nuove, e la scelta non e' quante righe scrivere ma **quante classi**.
Due strade:

- **una per modulo**: `DestructiveDatabaseException` per la guardia, `SeedingException` per i seeder.
  Due classi, e il `catch` distingue «guasto della guardia» da «guasto del seeding».
- **una per causa**: `AllowedDatabasesNotConfigured`, `DatabaseNotAllowedForTests`,
  `AdminPasswordMissing`, `DatabaseAlreadySeeded`. Quattro classi, e il `catch` distingue il singolo
  caso senza leggere il messaggio.

La seconda e' piu' precisa e la prima e' piu' corta. Il criterio che le separa non e' il gusto: e' se
**qualcuno reagira' in modo diverso** a due cause dello stesso modulo. Oggi nessuno le cattura in
produzione — l'unico consumatore sono i test, e li' basta il modulo.

Dove metterle: `app/Exceptions/`, che **oggi non esiste** (verificato: `ls app/Exceptions` non trova
niente, e nessuna classe del progetto estende `Exception`). E' la posizione canonica di Laravel e non
serve registrare nulla.

**Il ternario.** Non va spezzato in due variabili, va **girato**: la condizione esterna e' «il campo
`deleted_at` e' cambiato», e le due uscite sono «cancellato» o «ripristinato». Un `if` con due `return`
dice la stessa cosa in quattro righe che si leggono una volta. Fuori dal metodo non cambia niente, e
i dodici test di `CustomAuditableTest` lo coprono gia'.

**Le attese.** `cy.intercept("GET", "/admin/v1/users*").as("ricercaUtenti")` prima di digitare, e
`cy.wait("@ricercaUtenti")` dopo. Il debounce resta 500 ms — quello e' comportamento
dell'applicazione, non del test — ma l'attesa diventa «finche' la risposta non e' arrivata», col
timeout di Cypress come rete di sicurezza. **Alternativa scartata**: alzare il numero a 1500 ms.
Rende il test piu' lento e piu' fragile insieme, perche' un numero piu' grande e' comunque un numero.

**Cancellazioni**: nessuna. Non c'e' codice morto in questo lotto.

## 4. Da decidere

**Tutte e quattro risposte dal developer il 2026-08-21**, e tutte e quattro come il § 5 consigliava.
Restano scritte perche' la domanda spiega la risposta: fra sei mesi «due classi e non quattro» senza il
perche' e' una scelta che si rimette in discussione da capo.

### Vincoli

- ~~**`D1`**~~ — **le nuove eccezioni estendono `RuntimeException`.** Da qui discende che i sette
  `expectException`/`catch` esistenti restano verdi **senza toccarli**, e che chiunque catturi
  `RuntimeException` fuori continua a funzionare. Con `Exception` sarebbe il contrario: sette asserzioni
  da riscrivere in questo stesso lotto, per un rilievo di leggibilita'.

### Conflitti

- ~~**`D2`**~~ — **una classe per modulo, due in tutto.** La domanda era se qualcuno reagira' mai in
  modo diverso a «password mancante» e «database gia' seminato»: risposta del developer, «in teoria no».
  Quindi il dettaglio resta nel **messaggio**, che in questi quattro punti e' lungo e porta il rimedio.
  Se un giorno servisse distinguere, si aggiunge una sottoclasse senza toccare i `catch` esistenti.
- ~~**`D4`**~~ — **i test si stringono alla classe nuova.** Senza questo il punto non avrebbe verifica:
  con una sottoclasse `expectException(RuntimeException::class)` passa comunque, e passerebbe anche se
  un domani qualcuno rimettesse la generica. E' la ragione per cui il punto delle eccezioni e quello
  dei test sono **due** punti e non uno.

### Ignoto

- ~~**`D3`**~~ — **si', nello stesso lotto, come punti separati e ultimi**: le tre `throw` di
  `E2EUserSeeder.php` e il `cy.wait(500)` di `crud-user.cy.js`. Sono tre righe e una, e lasciarli
  significa ritrovarli fra un mese. **Sono diventati due punti e non uno** (`TEW07` per Cypress, `TEW09`
  per il seeder): la meta' seeder ha bisogno della classe che crea `TEW01`, e un punto che non si puo'
  chiudere non deve stare dentro uno che si chiude — altrimenti «fatto» significa «fatto a meta'».

### La spiegazione del `cy.wait(500)` — l'ipotesi del developer, e cosa dicono i file

Il developer: «il `cy.wait(500)` e' fatto perche' la pagina si carica, ma non tutto il codice JS arriva,
forse perche' usa metodi di chunk e hydration». E' l'ipotesi giusta da fare, e in un'applicazione con
SSR sarebbe anche vera: un `input` renderizzato dal server **esiste nel DOM prima** che Vue gli attacchi
i gestori, quindi `type()` riempirebbe il campo senza far partire niente. Qui non succede, per tre
ragioni misurate:

| Cosa | Misura | Conseguenza |
|---|---|---|
| **SSR non c'e'** | nessun `resources/js/ssr.js`, il blade usa `@inertia` semplice (`app.blade.php:12`) | non c'e' hydration: il DOM e' **vuoto** finche' Vue non monta, quindi un `input` che esiste ha gia' i suoi gestori |
| **le pagine non sono chunk separati** | `app.js:50` → `import.meta.glob("./Pages/**/*.vue", { eager: true })`; il build produce **4** file JS | non c'e' un chunk di pagina che possa arrivare in ritardo |
| **Cypress aspetta gia' il montaggio** | `cy.get("#user-search")` ritenta finche' l'elemento non c'e' | il caricamento della pagina e' **gia'** coperto, e non serve un numero |

Quindi quei 500 ms, che stanno **dopo** `type()`, non proteggono dal caricamento: proteggono dal
debounce di 500 ms di `UserTable.vue:90-95` — lo stesso numero, ed e' il motivo per cui il test regge
per un pelo. La sincronizzazione giusta e' sulla richiesta che il debounce fa partire.

**Un chunk in ritardo pero' c'e', e vale saperlo**: le **traduzioni**. `app.js:72` usa
`import.meta.glob` **senza** `eager` e poi `langs[path]()`, cioe' un import dinamico: al primo render
`$t()` puo' non avere ancora il suo file. Non tocca questo test — l'asserzione e' su uno *username*,
non su testo tradotto — ma un test che si aspettasse un'etichetta tradotta avrebbe quel problema, e la
risposta sarebbe la stessa: sincronizzarsi su cio' che si vede, non su un numero.

## 5. Consigli

- **`D1`: estendere `RuntimeException`.** Non e' pigrizia: sono guasti d'ambiente scoperti a runtime,
  che e' esattamente cio' che `RuntimeException` significa in PHP. E il lotto e' di severita' media —
  spendere sette asserzioni rosse per un rilievo di leggibilita' e' un cambio di rischio che non paga.
- **`D2`: una classe per modulo**, due in tutto. Nessuno oggi reagisce alla singola causa, e il
  messaggio — che in questi quattro punti e' lungo e porta il rimedio — resta il posto giusto per il
  dettaglio. Se un giorno servisse distinguere, si aggiunge una sottoclasse senza toccare i `catch`.
- **`D3`: si', nello stesso lotto**, come punti separati e ultimi. Sono tre `throw` e una riga.
- **`D4`: si', stringere i test alla classe nuova.** Altrimenti il punto non ha verifica: il rilievo
  si chiude in SonarQube e nessuno si accorge se torna. Questa e' la ragione per cui il punto delle
  eccezioni e quello dei test sono **due** punti e non uno.
