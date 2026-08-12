# Piano d'azione — complessità cognitiva: lista audit e middleware

Sigla dichiarata dall'analisi: `TCC` — qui non si ridichiara.

Stato: **da approvare** · Data: 2026-08-12 · Analisi: [analysis.md](./analysis.md)

Fatti e alternative stanno nell'analisi: i riferimenti `F…` e `D…` puntano lì. Ci si ferma al primo
punto non approvato. V — `auto`: lo stabilisce un comando · `man`: lo legge una persona.

## Onda 1 — la rete prima del refactoring

Nessun punto dell'onda 2 comincia prima che questa sia chiusa: si sta per riscrivere il middleware
che autentica l'intera area protetta (`D1`).

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TCC01 | da approvare | Test dei **sei rami d'uscita** di `Authenticated::handle()`: token assente · provider mancante o senza `secret_key` · token scaduto · claim `sub` mancante · utente inesistente · sessione eliminata dal database. Più il ramo felice | `tests/Feature/Middleware/AuthenticatedTest.php` (nuovo) | basso, ma è il punto che rende sicuri gli altri | auto | `docker exec idp_app_2 php artisan test --filter=Authenticated` verde, **prima** di qualsiasi modifica al middleware |
| TCC02 | da approvare | Test della lista audit sui tre comportamenti che il refactoring deve preservare: ricerca `q`, ordinamento per ogni colonna consentita, paginazione | `tests/Feature/AuditListTest.php` (nuovo) | basso | auto | `docker exec idp_app_2 php artisan test --filter=AuditList` verde prima delle modifiche |

## Onda 2 — i difetti sotto il rilievo

Vengono prima della scomposizione: sono correzioni di comportamento, e vanno viste come tali nel
diff invece di sparire dentro uno spostamento di righe.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TCC03 | da approvare | **`F6`** — l'ordinamento per `user.username` usa una inner join che ignora il tipo della relazione polimorfa: unisce gli audit dei client Passport a utenti omonimi per `id` e fa **sparire** dalla lista gli audit senza utente. Rimuovere `user.username` da `$allowedSorts` (raccomandazione `D2`) e aprire la correzione vera come task suo | `AuditController.php:49` | **alto** — oggi la lista mostra dati sbagliati e ne nasconde altri | auto | test nuovo: un audit con `user_id` nullo e uno prodotto da un client Passport **restano nella lista** con ogni ordinamento consentito |
| TCC04 | da approvare | **`F7`** — mettere un tetto a `per_page`, validato, con un massimo esplicito: oggi il valore arriva dal client senza limite su una tabella che cresce di continuo (punto 5 della checklist perf/leak) | `AuditController.php:70` | medio | auto | test: `?per_page=1000000` restituisce al più il massimo consentito, non un milione di righe |
| TCC05 | da approvare | **`F9`** — togliere `->latest()`, che dopo un `orderBy` esplicito aggiunge un criterio non richiesto e nel ramo `else` ripete quello già impostato. **Subordinato a `D4`**: prima verificare se con la join produce un errore di ambiguità su `created_at`, perché in quel caso non è pulizia ma un guasto | `AuditController.php:73` | basso | auto | i test di `TCC02` restano verdi e l'ordine predefinito è ancora il più recente per primo |

## Onda 3 — la scomposizione

È il rilievo vero e proprio. Arriva ultima perché senza le onde 1 e 2 sposterebbe righe sopra a un
difetto.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TCC06 | da approvare | `AuditController::all()` 18 → sotto 15: estrarre `applySearch()` e `applySort()` come metodi privati che ricevono e restituiscono la query. I tre blocchi sono già indipendenti e separati da righe vuote (§ 3) | `AuditController.php:19-79` | basso | man | i test di `TCC02` verdi **senza modifiche**; il rilievo non ricompare alla passata dello strumento. È `man` perché la soglia non è misurabile da qui (`D6`) |
| TCC07 | da approvare | `Authenticated::handle()` 16 → sotto 15: scomporre per **fase di validazione**, sfruttando `forceLogoutAndRedirect` come uscita unica, che esiste già (`F3`) | `Authenticated.php:18-100` | **alto** — è il percorso di autenticazione di tutta l'area protetta | man | i test di `TCC01` verdi **senza modifiche**, e login/logout provati a mano nell'ambiente docker |

## Cosa questo piano non copre

- **La API Resource per gli audit** (`F8`, `D5`): la risposta espone i modelli interi, `user`
  compreso. Rompe la tabella Vue, quindi è un task suo — **da aprire**, non da ricordare.
- **La correzione vera dell'ordinamento polimorfo** (`D2`): `TCC03` toglie la colonna, non la ripara.
- **Lo scope per tenant degli audit** (`F4`, `D3`): dipende da una risposta che non ho.
- **La misura della complessità** (`D6`): finché lo strumento non è nel repo, `TCC06` e `TCC07`
  chiudono su una lettura, non su un numero. Dichiararlo `auto` sarebbe dichiarare una verifica che
  nessuno esegue.
