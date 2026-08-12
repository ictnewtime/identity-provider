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

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TCC01 | da approvare | Test dei **sei rami d'uscita** di `Authenticated::handle()`: token assente · provider mancante o senza `secret_key` · token scaduto · claim `sub` mancante · utente inesistente · sessione eliminata dal database. Più il ramo felice | `tests/Feature/Middleware/AuthenticatedTest.php` (nuovo) | basso, ma è il punto che rende sicuri gli altri | auto | `docker exec idp_app_2 php artisan test --filter=Authenticated` verde, **prima** di qualsiasi modifica al middleware |
| TCC02 | da approvare | Test della lista audit sui comportamenti che il refactoring deve preservare: ricerca `q`, ordinamento per ogni colonna consentita, paginazione | `tests/Feature/AuditListTest.php` (nuovo) | basso | auto | `docker exec idp_app_2 php artisan test --filter=AuditList` verde prima delle modifiche |

## Onda 2 — i difetti sotto il rilievo

Correzioni di comportamento: vanno viste come tali nel diff, invece di sparire dentro uno spostamento
di righe.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TCC03 | da approvare | **`D2` — correggere la join, non togliere la colonna.** Due modifiche che vanno **insieme**: (a) aggiungere la condizione su `user_type`, che esiste in tabella con tanto di indice `(user_id, user_type)` e oggi la query ignora — senza, un audit di un client Passport con `id` 7 si attacca all'**utente** `id` 7; (b) passare a `leftJoin`, perché `user_id` è nullable e la inner join fa **sparire** dalla lista gli audit di sistema. Solo (a) lascerebbe fuori i `NULL`, solo (b) continuerebbe ad attaccare la riga sbagliata | `AuditController.php:49-56` | **alto** — oggi la lista mostra dati sbagliati e ne nasconde altri | auto | test: un audit con `user_id` nullo e uno prodotto da un client Passport **restano nella lista** con ogni ordinamento, e il secondo mostra il **suo** attore, non un utente omonimo per `id` |
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

Ultima perché **cambia il contratto col frontend**: va fatta quando il resto è stabile, e insieme al
consumatore.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TCC11 | da approvare | `AuditResource` che sceglie **i campi necessari e basta**: oggi la risposta restituisce il modello `Audit` intero con dentro l'`user` intero (`F8`). È il punto 2 della checklist perf/leak — la forma che rende possibile un leak al primo ruolo nuovo | `app/Http/Resources/AuditResource.php` (nuovo), `AuditController.php:71-79` | medio | auto | la risposta non contiene nessun campo oltre a quelli dichiarati; test che lo asserisce |
| TCC12 | da approvare | Adeguare la tabella Vue alla nuova forma, compreso lo `username` che oggi viene **iniettato al volo** sui client Passport (`AuditController.php:74-78`): con la Resource quella rimappatura si fa lì, una volta | `resources/js/` (la pagina degli audit) | medio — è la parte che si vede | man | la tabella mostra le stesse colonne di prima, ricerca e ordinamento compresi |

## Cosa questo piano non copre

- **La revoca vera dei client Passport** (`TCC08` fissa la regola, non implementa un flusso): quando
  servirà cancellare un client, il lavoro è suo.
- **Lo scope per tenant** (`F4`): non esiste in questo sistema (`D3`). `TCC09` lo scrive, non lo
  introduce.
- **La misura della complessità**: senza SonarQube in locale (`BDB02`) i punti dell'onda 3 chiudono su
  una rilettura del report, non su un comando. Dichiararlo `auto` sarebbe dichiarare una verifica che
  nessuno esegue.
