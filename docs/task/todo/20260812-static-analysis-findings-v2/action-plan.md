# Piano d'azione — complessità cognitiva: lista audit e middleware

Sigla dichiarata dall'analisi: `TCC` — qui non si ridichiara.

Stato: **da approvare** · Data: 2026-08-12 · Analisi: [analysis.md](./analysis.md)

Fatti e alternative stanno nell'analisi: i riferimenti `F…` e `D…` puntano lì. Ci si ferma al primo
punto non approvato. V — `auto`: lo stabilisce un comando · `man`: lo legge una persona.

> **Riscritto il 2026-08-12 dopo le risposte del § 4.** Tre cambiamenti che valgono più della
> riorganizzazione delle righe: `D2` **capovolge** `TCC03` — la join si corregge, non si toglie la
> colonna; `D5` **allarga lo scope** al frontend con la API Resource; e la scomposizione deve produrre
> **classi provabili da sole**, non metodi privati. Quest'ultimo requisito cambia la forma dell'onda 3.

## Onda 1 — la rete prima del refactoring

Nessun punto dell'onda 3 comincia prima che questa sia chiusa (`D1`, confermato): si sta per
riscrivere il middleware che autentica l'intera area protetta.

> **Esito, 2026-08-12: 17 test scritti, 15 verdi e 2 rossi — ed e' il risultato giusto.** I due rossi
> non sono test da aggiustare: uno e' la regressione di `VDF02` e diventera' verde con `TCC03`;
> l'altro ha **scoperto un difetto che nessuno aveva visto**, `VDF10`, ed e' ora `TCC13`. E' la
> ragione per cui `D1` chiedeva i test prima: quel ramo d'errore non era mai stato percorso.
>
> **Due ostacoli d'ambiente incontrati eseguendoli**, entrambi registrati: la config cache rende i
> test ciechi a `phpunit.xml` e li fa puntare a MariaDB (`BDB26`), e `UserFactory` non valorizza
> `enabled`, che e' NOT NULL (`BDB27`).

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TCC01 | **fatto** (2026-08-12) — 8 test, 7 verdi e 1 rosso che ha scoperto `VDF10` | Test dei **sei rami d'uscita** di `Authenticated::handle()`: token assente · provider mancante o senza `secret_key` · token scaduto · claim `sub` mancante · utente inesistente · sessione eliminata dal database. Più il ramo felice | `tests/Feature/Middleware/AuthenticatedTest.php` (nuovo) | basso, ma è il punto che rende sicuri gli altri | auto | `composer test -- --filter=Authenticated` verde prima di qualsiasi modifica al middleware. **Non** `docker exec … php artisan test`: quel comando cancella il database reale — difetto `VDF11` |
| TCC02 | **fatto** (2026-08-12) — 9 test, 8 verdi e 1 rosso che e' la regressione di `VDF02` | Test della lista audit sui comportamenti che il refactoring deve preservare: ricerca `q`, ordinamento per ogni colonna consentita, paginazione | `tests/Feature/AuditListTest.php` (nuovo) | basso | auto | `composer test -- --filter=AuditList` verde prima delle modifiche (mai `php artisan test` nudo nel container: `VDF11`) |

## Onda 2 — i difetti sotto il rilievo

Correzioni di comportamento: vanno viste come tali nel diff, invece di sparire dentro uno spostamento
di righe.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TCC03 | **fatto** (2026-08-12) | **`D2` — correggere la join, non togliere la colonna.** Due modifiche che vanno **insieme**: (a) aggiungere la condizione su `user_type`, che esiste in tabella con tanto di indice `(user_id, user_type)` e oggi la query ignora — senza, un audit di un client Passport con `id` 7 si attacca all'**utente** `id` 7; (b) passare a `leftJoin`, perché `user_id` è nullable e la inner join fa **sparire** dalla lista gli audit di sistema. Solo (a) lascerebbe fuori i `NULL`, solo (b) continuerebbe ad attaccare la riga sbagliata | `AuditController.php:49-56` | **alto** — oggi la lista mostra dati sbagliati e ne nasconde altri | auto | test: un audit con `user_id` nullo e uno prodotto da un client Passport **restano nella lista** con ogni ordinamento, e il secondo mostra il **suo** attore, non un utente omonimo per `id` |
| TCC13 | da approvare | **`VDF10`, scoperto da `TCC01`** — `forceLogoutAndRedirect()` legge `$provider->domain` senza controllare che il provider esista (`Authenticated.php:108`), e viene chiamato **anche** dal ramo che gestisce «provider non trovato»: la gestione dell'errore fallisce sul caso per cui e' scritta, e l'utente prende un 500 invece di un 401 — senza che sessione e cookie vengano puliti | `Authenticated.php:102-127` | medio — e' il percorso d'errore di tutta l'area protetta | auto | `test_rifiuta_quando_il_provider_idp_non_esiste` passa da rosso a verde, e gli altri sette restano verdi |
| TCC16 | **fatto** (2026-08-12) | **Un ambiente di test separato, non condiviso con quello di sviluppo.** Oggi la suite gira nel container di sviluppo, e per farlo `composer test` esegue `config:clear`: cancella una cache dell'**applicazione viva**, e la configurazione dei test convive con quella del lavoro. La proposta del developer: `docker-compose.test.yml` con un `Dockerfile.test` che prepara il proprio `.env` **dentro l'immagine**, senza sovrascrivere quello dell'host. Non e' una difesa in piu' — e' togliere la condivisione che rende necessarie le difese | `docker-compose.test.yml`, `Dockerfile.test` (nuovi), `docs/TEST.md` | medio — un ambiente nuovo da mantenere, che pero' non tocca quello di sviluppo | man | con l'ambiente di sviluppo **acceso**, una esecuzione completa della suite lascia invariati `idp_develop`, `.env` e `bootstrap/cache/` dell'host |
| ~~TCC15~~ | **spostato** (2026-08-12) | La rinomina del database di sviluppo e la creazione di `idp_develop` sono confluite in [local-environments](../../done/20260812-local-environments/action-plan.md) come `TLE01`, insieme alla decisione su quale database usa la suite. Qui non resta niente da fare | — | — | — | — |
| TCC14 | **fatto** (2026-08-12) | **`VDF11` — la difesa, che vale anche se `TCC15` non c'è.** La config cache rende `env()` inerte, quindi `phpunit.xml` **non protegge niente**: serve un controllo che non dipenda da `env()`. Un `TestCase::setUp()` che **aborta** se il database in uso non è quello di test — sqlite in memoria oppure `idp_test` — con un messaggio che dice cosa fare. Vale per ogni test futuro, compresi quelli che nessuno ha ancora scritto | `tests/TestCase.php`, `docs/TEST.md` | **alto** — è il difetto che ha già fatto danno | auto | con la config cache presente e `DB_DATABASE=idp_develop`, la suite **si ferma** con un messaggio invece di migrare il database di sviluppo |
| TCC04 | da approvare | **`F7`** — tetto a `per_page`, validato, con massimo esplicito: oggi il valore arriva dal client senza limite su una tabella che cresce di continuo (punto 5 della checklist perf/leak) | `AuditController.php:70` | medio | auto | test: `?per_page=1000000` restituisce al più il massimo consentito |
| TCC05 | da approvare | **`F9`, `D4`** — **prima un test unitario** che stabilisca cosa fa `->latest()` dopo un `orderBy` esplicito e con la join attiva: ambiguità su `created_at` o risoluzione sulla lista di output? **Poi** togliere la riga. Il test resta: è la regressione che documenta il perché | `tests/Unit/AuditOrderingTest.php` (nuovo), poi `AuditController.php:73` | basso se è pulizia, **alto** se il test scopre un errore su una colonna ordinabile | auto | il test dichiara il comportamento osservato; dopo la rimozione resta verde e l'ordine predefinito è ancora il più recente per primo |
| TCC08 | da approvare | **`F6c`, `F6d`** — fissare la regola **finché nessuno ha ancora scritto quel codice**: un client Passport **si revoca, non si cancella**. `Passport\Client` non ha soft delete e una cancellazione lascerebbe gli audit orfani del loro attore. La colonna `revoked` esiste già; oggi l'applicazione non cancella client, quindi il costo è zero e il momento è adesso | `docs/doc-code-guide-line.md`; eventuale guardia dove nascerà la cancellazione | basso oggi, **alto quando qualcuno scriverà il metodo** senza saperlo | man | la regola è scritta dove chi implementerà la cancellazione la incontra |
| TCC09 | da approvare | **`D3`** — scrivere che l'assenza di uno scope per tenant è una **scelta**: in questo sistema i tenant non si gestiscono e un amministratore vede tutti gli audit. Un commento dove sta la query, così chi la legge non lo prende per una dimenticanza | `AuditController.php:21` | nullo | man | il commento c'è e dice *scelta*, non *da fare* |

## Onda 3 — la scomposizione, in classi provabili

**`D6`**: la soglia la misura **SonarQube** e il numero si riporta a mano, iterando — si modifica, si
rilancia la scansione, si legge. Per questo la verifica del numero è `man` in tutti i punti: dichiararla
`auto` sarebbe dichiarare un comando che non esiste.

**Requisito di forma del developer**: non metodi privati, ma **2-3 classi**, ognuna con i **suoi unit
test**, più **unit test di composizione** che provino l'intero meccanismo su flussi e condizioni
diversi. Una classe che non si può provare da sola non ha risolto il problema che il numero segnalava.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TCC06 | da approvare | `AuditController::all()` 18 → sotto 15, estraendo la ricerca e l'ordinamento in **classi proprie** (i tre blocchi sono già indipendenti e separati da righe vuote). Il controller resta di poche righe e non parla più direttamente con l'ORM | `AuditController.php:19-79`; 2 classi nuove | basso | man | i test di `TCC02` verdi **senza modifiche**; ogni classe nuova ha i suoi unit test; il numero riletto in SonarQube |
| TCC07 | da approvare | `Authenticated::handle()` 16 → sotto 15, scomposto per **fase di validazione** — estrazione del token, risoluzione del provider, scadenza, risoluzione dell'utente, verifica della sessione — in classi con un'uscita esplicita. `forceLogoutAndRedirect` esiste già ed è il punto di uscita unico (`F3`) | `Authenticated.php:18-100`; 2-3 classi nuove | **alto** — percorso di autenticazione di tutta l'area protetta | man | i test di `TCC01` verdi **senza modifiche**; unit test per ogni classe; login e logout provati a mano; numero riletto in SonarQube |
| TCC10 | da approvare | **Unit test di composizione**: non i pezzi ma il meccanismo intero, su flussi e condizioni diversi — token valido con sessione revocata, token scaduto, utente disabilitato, ricerca combinata con ordinamento e paginazione. È il test che i test per classe non sostituiscono, perché il difetto sta negli attraversamenti | `tests/Feature/` | medio | auto | ogni flusso ha il suo caso, e i casi falliscono se si rompe un passaggio fra due classi |

## Onda 4 — la API Resource (scope allargato da `D5`)

Era pianificata ultima perché **cambia il contratto col frontend**; il developer l'ha approvata per
prima. Nessun conflitto emerso: la Resource semplifica il controller invece di intralciare la
scomposizione dell'onda 3, e i difetti dell'onda 2 sono nella query, non nella forma della risposta.

> **Esito, 2026-08-12.** Il contratto **è cambiato davvero**: `AuditResource::collection()` avvolge la
> paginazione in `meta` (`data` + `meta` + `links`), mentre prima `total` e `per_page` stavano al primo
> livello. Il componente Vue è stato adeguato in un punto solo (`TCC12`), e **due asserzioni di
> `TCC02` sono state riscritte** — non per far passare un rosso, ma perché descrivevano una busta che
> un punto approvato ha sostituito. Le altre sette sono rimaste intatte, ed è quello che dice che il
> comportamento non è cambiato.
>
> **Controllo perf/leak dichiarato voce per voce** nella tabella sotto: due voci sono ora coperte da
> un test, due restano aperte con il loro punto.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TCC11 | **fatto** (2026-08-12) | `AuditResource` che sceglie **i campi necessari e basta**: oggi la risposta restituisce il modello `Audit` intero con dentro l'`user` intero (`F8`). È il punto 2 della checklist perf/leak — la forma che rende possibile un leak al primo ruolo nuovo | `app/Http/Resources/AuditResource.php` (nuovo), `AuditController.php:71-79` | medio | auto | la risposta non contiene nessun campo oltre a quelli dichiarati; test che lo asserisce |
| TCC12 | **fatto** (2026-08-12) | Adeguare la tabella Vue alla nuova forma, compreso lo `username` che oggi viene **iniettato al volo** sui client Passport (`AuditController.php:74-78`): con la Resource quella rimappatura si fa lì, una volta | `resources/js/` (la pagina degli audit) | medio — è la parte che si vede | man | la tabella mostra le stesse colonne di prima, ricerca e ordinamento compresi |

### Controllo perf/leak su `TCC11` — voce per voce

| # | Voce | Esito |
|---|---|---|
| 1 | **Query N+1** | **coperto da un test**: `test_il_numero_di_query_non_cresce_con_le_righe` confronta il numero di query con 1 e con 10 audit. `Audit::with("user")` carica in eager la relazione polimorfa e la Resource non interroga niente |
| 2 | **Data leakage** | **coperto da un test**: `test_la_risposta_espone_solo_i_campi_dichiarati` fissa gli 11 campi, e dell'attore esce il **solo** `username`. Prima usciva il modello `User` intero — email, stato dell'account, scadenza password — e per un client Passport `redirect` e `revoked` |
| 3 | **Scope / tenant** | **non applicabile per decisione**, non per assenza: in questo sistema i tenant non si gestiscono e un amministratore vede tutti gli audit (`D3`). Resta da **scriverlo** dove sta la query: `TCC09` |
| 4 | **Memoria e streaming** | **aperto**: la risposta è paginata, ma `per_page` arriva dal client senza tetto — `TCC04`, difetto `VDF03`. La Resource non peggiora né migliora questa voce |
| 5 | **Query non vincolate** | **parzialmente aperto**: la paginazione c'è; gli **indici** no su tre delle cinque colonne ordinabili (`event`, `auditable_type`, `ip_address`). La migrazione indicizza `(auditable_type, auditable_id)` e `(user_id, user_type)`. Non è un punto di questo piano e va aperto quando la tabella sarà grande abbastanza da farlo vedere |

**Trasversale — timeout sulle chiamate di rete**: non applicabile, `TCC11` non ne fa nessuna.

## Cosa questo piano non copre

- **La revoca vera dei client Passport** (`TCC08` fissa la regola, non implementa un flusso): quando
  servirà cancellare un client, il lavoro è suo.
- **Lo scope per tenant** (`F4`): non esiste in questo sistema (`D3`). `TCC09` lo scrive, non lo
  introduce.
- **La misura della complessità**: senza SonarQube in locale (`BDB02`) i punti dell'onda 3 chiudono su
  una rilettura del report, non su un comando. Dichiararlo `auto` sarebbe dichiarare una verifica che
  nessuno esegue.
