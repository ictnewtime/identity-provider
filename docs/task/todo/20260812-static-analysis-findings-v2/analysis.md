# Analisi — complessità cognitiva: lista audit e middleware di autenticazione

**Identificatori**: `TCC` = task cognitive-complexity

Stato: da approvare · Data: 2026-08-12 · Tranche **v2** di 4 —
[v1](../20260812-static-analysis-findings-v1/analysis.md) · [v3](../20260812-static-analysis-findings-v3/analysis.md) · [v4](../20260812-static-analysis-findings-v4/analysis.md)

## 1. Obiettivo

Portare sotto la soglia di 15 la complessità cognitiva di due funzioni — `AuditController::all()`
(18) e `Authenticated::handle()` (16) — **senza limitarsi a spostare righe per far scendere il
numero**.

Perché adesso, e perché non è solo cosmesi: leggendo le due funzioni per capire dove tagliare sono
emersi **difetti di correttezza che il rilievo non nomina** (§ 3, `F6`–`F9`). Il numero alto era il
sintomo; questi sono la malattia. Se si chiude il rilievo senza guardarli, restano — e nessuno li
riguarderà, perché il rilievo sarà verde.

## 2. Situazione attuale

| # | Fatto | Prova |
|---|---|---|
| F1 | `AuditController::all()` — 61 righe, un solo metodo che fa ricerca, ordinamento, paginazione e rimappatura | `app/Http/Controllers/Manage/AuditController.php:19-79` |
| F2 | `Authenticated::handle()` — 83 righe, con un `try` che avvolge quasi tutto il corpo | `app/Http/Middleware/Authenticated.php:18-100` |
| F3 | Il middleware ha **già** un metodo di uscita dedicato, usato in tutti i rami d'errore | `app/Http/Middleware/Authenticated.php:102` (`forceLogoutAndRedirect`) |
| F4 | La rotta della lista audit è protetta ma **non filtrata per tenant**: nessun vincolo su provider o organizzazione | `routes/web.php:144` |
| F5 | Il progetto **non ha ancora** né `app/Http/Resources` popolato per gli audit né uno strato di query dedicato: il controller parla direttamente con l'ORM | `app/Http/Controllers/Manage/AuditController.php:21` |
| F6 | La `join("users", "audits.user_id", "=", "users.id")` è una **inner join** e ignora `auditable`/`user_type`: la relazione `user` è **polimorfa** (`User` o `PassportClient`), come dimostra l'`orWhereHasMorph` dieci righe sopra | `AuditController.php:52-56` vs `:30-42` |
| F7 | `$perPage = $request->input("per_page", 25)` non ha **né tetto né validazione**: il valore arriva dal client | `AuditController.php:70` |
| F8 | La risposta restituisce il **modello completo** `Audit` con dentro il modello completo `user`, senza una API Resource che scelga i campi | `AuditController.php:71-79` |
| F9 | `->latest()` viene applicato **dopo** l'`orderBy` esplicito, e nel ramo `else` duplica un ordinamento già impostato | `AuditController.php:73` vs `:65` |
| F10 | Il middleware scrive in log l'esito di ogni tentativo, incluso `ip_client` e `user_agent` | `Authenticated.php:29-34` |

### Dipendenze e breaking change

- **`AuditController::all()` alimenta una tabella Vue** che si aspetta la forma attuale della
  risposta, compreso lo `username` iniettato al volo sui client Passport (`AuditController.php:74-78`).
  Introdurre una API Resource (§ 3) **è un breaking change per il frontend** e va fatto insieme al
  consumatore, non prima.
- **Il middleware è sul percorso di autenticazione di tutta l'area protetta**: una regressione qui non
  degrada, esclude. Ogni modifica va coperta da test prima di essere fatta, non dopo.
- Nessuna delle due funzioni è un `service`: la policy perf/leak dell'organizzazione non scatta
  formalmente, ma i controlli 2, 3, 4 e 5 di quella checklist trovano comunque qualcosa (`F4`, `F7`,
  `F8`) — e non guardarli perché il file sta in `Controllers/` invece che in `Services/` sarebbe
  esattamente il «timbrare» che quella pagina vieta.

## 3. Analisi

**Come si abbassa davvero il numero.** La complessità cognitiva sale sui rami annidati, non sulla
lunghezza. In `all()` i tre blocchi — ricerca, ordinamento, rimappatura — sono **indipendenti**:
ognuno diventa un metodo privato che riceve la query e la restituisce (`applySearch`, `applySort`), e
il corpo torna a essere quattro righe che si leggono. È la scomposizione che il codice suggerisce da
sé, perché i blocchi sono già separati da righe vuote. Nel middleware la struttura è diversa: il
`try` avvolge tutto e l'annidamento nasce da lì. La scomposizione naturale è per **fase di
validazione** — estrazione del token, risoluzione del provider, verifica della scadenza, risoluzione
dell'utente, verifica della sessione — ognuna con un'uscita esplicita. `F3` dice che metà del lavoro
è già fatto: `forceLogoutAndRedirect` è il punto di uscita unico, e lo si sfrutta.

Alternativa scartata per entrambe: **spezzare in metodi senza cambiare la struttura**, il minimo per
scendere sotto 15. Chiude il rilievo, lascia i difetti sotto, e la prossima soglia superata riporta
qui qualcuno che dovrà rileggere tutto da capo.

**`F6` — la join polimorfa è un difetto di correttezza, non di stile.** Ordinando per
`user.username`, la query unisce `audits.user_id` a `users.id` **senza guardare il tipo**. Ma dieci
righe più su lo stesso codice dichiara che quella relazione può puntare a un `PassportClient`: un
audit prodotto da un client con `id` 7 viene unito all'**utente** con `id` 7, che è una persona
diversa e non c'entra niente. E poiché la join è interna, gli audit senza utente — o con un utente
cancellato — **spariscono dalla lista** appena qualcuno clicca su quella colonna. Un registro di
audit che nasconde righe a seconda di come lo si ordina è il difetto peggiore di questo lotto, ed è
il motivo per cui `TCC03` sta in cima al piano invece che in fondo. Alternative: una `leftJoin` con
la condizione sul tipo (corretta, ma l'ordinamento su una relazione polimorfa resta fragile), oppure
togliere `user.username` dalle colonne ordinabili finché non c'è una soluzione vera. La seconda è
brutta e onesta; la raccomandazione sta nel § 5.

**`F7` — `per_page` senza tetto.** È il punto 5 della checklist dell'organizzazione, alla lettera:
`?per_page=1000000` fa caricare in memoria un milione di righe di audit — la tabella che in questo
sistema cresce più in fretta di tutte. Non serve un attacco: basta un frontend che sbaglia un
parametro. Il tetto va messo, con un massimo esplicito.

**`F8` — cosa esce dalla risposta.** Si restituisce il modello `Audit` intero con dentro l'`user`
intero. Per un `User` questo significa ogni colonna che il modello non nasconde; per un
`PassportClient`, `redirect` e i campi del client. La lista audit la legge un amministratore, quindi
non è un leak verso un ruolo sbagliato, **ma è la forma che lo rende possibile al primo ruolo nuovo**:
una API Resource sceglie i campi una volta, un modello nudo li espone per sempre. Va detto che
introdurla rompe il frontend (§ 2), quindi è un punto suo, subordinato.

**`F4` — lo scope.** Non lo tratto come un difetto perché non so se in questo sistema esista un
concetto di tenant sugli audit: è una domanda (`D3`), non una conclusione.

**`F9` — `latest()` di troppo.** Dopo un `orderBy` esplicito aggiunge `created_at desc` come criterio
secondario; nel ramo `else` è la stessa cosa detta due volte. Non l'ho eseguito, quindi **non affermo
che rompa**: sospetto che con `select("audits.*")` MySQL risolva `created_at` sulla lista di output e
non dia errore di ambiguità, ma è un sospetto e va verificato (`D4`). In ogni caso, riga da togliere.

**Rapporto con le altre tranche.** Nessuna sovrapposizione di rilievi: `v1` è frontend, `v3` e `v4`
sono literali duplicati. La sovrapposizione è **sulla domanda a monte** — quale strumento ha prodotto
questa lista (`D6` di `v1`) — e su `TSA09`, che porta nel repo la configurazione di lint ma **solo per
JavaScript**: i rilievi di questa tranche sono PHP e richiedono un altro strumento. Va risolto una
volta sola, e non qui.

## 4. Da decidere

### Vincoli

- **D1** — la scomposizione di `Authenticated::handle()` tocca il percorso di autenticazione di tutta
  l'area protetta (§ 2). Confermi che va fatta **solo dopo** aver scritto i test che coprono i sei
  rami d'uscita attuali? Senza, è un refactoring alla cieca su un middleware di sicurezza.
- **D2** — `F6`, l'ordinamento per `user.username`: si corregge la join (con il tipo, e in `left`), o
  si **rimuove la colonna** dalle ordinabili finché non c'è una soluzione per le relazioni polimorfe?

### Conflitti

- **D3** — `F4`: gli audit devono essere filtrati per provider/organizzazione, o è corretto che un
  amministratore li veda tutti? Se il secondo, lo si scrive e il punto si chiude come `scartato`.
- **D5** — `F8`: introdurre una API Resource rompe la tabella Vue. Lo si fa **in questo task**,
  insieme al frontend, o diventa un task suo? Tenerlo qui allarga lo scope; rimandarlo lascia la
  risposta com'è.

### Ignoto

- **D4** — `F9`: `->latest()` con la join produce un errore di ambiguità su `created_at`, o MySQL lo
  risolve sulla lista di output? Non l'ho eseguito e non lo affermo. Va provato con una richiesta
  reale ordinata per `user.username`.
- **D6** — qual è la soglia e quale strumento la misura, per il PHP? Senza saperlo non si può
  verificare che 18 sia sceso sotto 15: si può solo *credere* di averlo fatto.

## 5. Consigli

| Domanda | Raccomandazione |
|---|---|
| **D1** | Sì, e non è negoziabile. I test dei sei rami del middleware sono il primo punto del piano (`TCC01`); la scomposizione viene dopo e deve lasciarli verdi senza modificarli — è l'unica prova che il comportamento non è cambiato. |
| **D2** | **Rimuovere `user.username` dalle colonne ordinabili**, subito, e aprire la correzione vera come punto suo. Una `leftJoin` con la condizione sul tipo risolve le righe perse ma lascia l'ordinamento fragile su due tabelle diverse (`users.username` e `oauth_clients.name`), che è il problema che non ho una buona risposta per — e una lista che ordina *quasi* giusto è peggio di una colonna che non si ordina. |
| **D3** | Serve la tua risposta. Se un amministratore è per definizione globale, va **scritto** nell'analisi e nel controllo di autorizzazione, non lasciato implicito nell'assenza di un `where`. |
| **D4** | Verificarlo prima di toccare quella riga: se l'errore c'è, `F9` non è pulizia ma un guasto in produzione su una colonna ordinabile, e cambia priorità. |
| **D5** | Task suo. Qui lo scope è la complessità cognitiva più i difetti che ci stanno dentro; la API Resource è un cambiamento di contratto col frontend e merita il suo piano. Ma va **aperto adesso**, non ricordato. |
| **D6** | Se è SonarQube, la soglia è la sua regola predefinita e si misura solo rieseguendolo. Finché non è nel repo (`TSA09`), la verifica di `TCC02` e `TCC06` resta `man`: la dichiaro tale nel piano invece di far finta che un comando la produca. |

Il piano: [action-plan.md](./action-plan.md).
