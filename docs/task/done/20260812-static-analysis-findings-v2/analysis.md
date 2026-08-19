# Analisi — complessità cognitiva: lista audit e middleware di autenticazione

**Identificatori**: `TCC` = task cognitive-complexity

Stato: da approvare · Data: 2026-08-12 · Tranche **v2** di 4 —
[v1](../../done/20260812-static-analysis-findings-v1/analysis.md) · [v3](../../done/20260812-static-analysis-findings-v3/analysis.md) · [v4](../../done/20260812-static-analysis-findings-v4/analysis.md)

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
| F6 | La `join("users", "audits.user_id", "=", "users.id")` **non filtra su `user_type`**, che pure esiste in tabella con tanto di indice `(user_id, user_type)` | `AuditController.php:52-56`; `database/migrations/2026_03_11_105918_create_audits_table.php:20,35` |
| F6a | **`User` usa `SoftDeletes`**: un utente cancellato lascia la sua riga, quindi la inner join continua a trovarlo | `app/Models/User.php:19` |
| F6b | `audits.user_id` è **nullable**: gli audit senza utente esistono, e la inner join li esclude | migrazione `:21` |
| F6c | **`Laravel\Passport\Client` NON ha SoftDeletes**: ha un booleano `revoked`. Una cancellazione è definitiva e lascia gli audit orfani | `vendor/laravel/passport/src/Client.php:10,48`; `database/migrations/2016_06_01_000004_create_oauth_clients_table.php:23` |
| F6d | Oggi **l'applicazione non cancella client Passport**: `OauthClientsController` non ha un metodo di cancellazione e le sue rotte sono commentate | `app/Http/Controllers/Manage/OauthClientsController.php`; `routes/web.php:11` |
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

**`F6` — cosa è davvero rotto nella join, dopo le correzioni del developer.** La mia prima analisi
diceva due cose e **una era sbagliata**: che gli audit di utenti cancellati sparissero dalla lista.
`F6a` la smentisce — `User` usa `SoftDeletes`, la riga resta, la inner join la trova. Correzione
registrata qui e non in coda.

Restano due problemi veri, e sono diversi fra loro:

1. **La join non guarda `user_type`** (`F6`). La colonna esiste, ha perfino un indice dedicato, e la
   query la ignora: un audit prodotto da un client Passport con `id` 7 viene unito all'**utente** con
   `id` 7. Non è un dubbio sulla forma della relazione — che il developer ha confermato voluta — è la
   join che non usa metà della chiave.
2. **La join è interna, e `user_id` è nullable** (`F6b`). Gli audit senza utente — quelli di sistema —
   spariscono dalla lista appena si ordina per username. Nessun soft delete c'entra: sono righe che
   non hanno mai avuto un utente.

Quindi la correzione **non è togliere la colonna ordinabile** (era la mia raccomandazione, superata):
è aggiungere la condizione su `user_type` e passare a `leftJoin`. Le due modifiche vanno insieme —
solo il tipo lascerebbe fuori i `NULL`, solo la left join continuerebbe ad attaccare la riga sbagliata.

**Il problema che apre `F6c`, e che non era nella lista.** `Passport\Client` non ha soft delete: ha
`revoked`. Se un client viene cancellato, la riga sparisce e i suoi audit restano con un `user_id` che
non punta più a niente — e su un registro di audit questo è il danno peggiore, perché l'informazione
persa è **chi ha fatto cosa**. `F6d` dice che oggi il rischio non si realizza: l'applicazione non
cancella client, il controller non ha il metodo e le rotte sono commentate. Ma il meccanismo giusto
esiste già nello schema — la colonna `revoked` — quindi la regola da fissare adesso, finché nessuno ha
ancora scritto quel codice, è: **un client Passport si revoca, non si cancella**.

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

**`F4` — lo scope: risposta del developer.** In questo sistema **i tenant non si gestiscono**, e un
amministratore vede tutti gli audit: è corretto così. Smette di essere una domanda e diventa una
riga scritta — l'assenza di un `where` è una **scelta**, non una dimenticanza, ed è questo che vale
la pena lasciare a chi legge la query fra sei mesi.

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

> **Risposte del developer, 2026-08-12. Il § 4 è chiuso**: sei domande su sei. Le risposte stanno
> dove erano le domande, e hanno **allargato lo scope del task** (`D5`) e capovolto la correzione di
> `F6` (`D2`).

### Vincoli

- **D1** — la scomposizione di `Authenticated::handle()` va fatta **solo dopo** i test sui sei rami
  d'uscita? → **CONFERMATO.** I test vengono prima, e restano invariati durante il refactoring: sono
  l'unica prova che il comportamento non è cambiato.
- **D2** — l'ordinamento per `user.username`: correggere la join o rimuovere la colonna? →
  **CORREGGERE LA JOIN.** La relazione polimorfa è voluta; quello che manca è che la query usi
  `user_type` e non escluda le righe senza utente (§ 3). La colonna resta ordinabile.

### Conflitti

- **D3** — gli audit vanno filtrati per provider/organizzazione? → **NO**: i tenant non si gestiscono,
  ed è corretto che un amministratore veda tutto. Da **scrivere** dove sta la query, non da lasciare
  implicito.
- **D5** — la API Resource rompe la tabella Vue: in questo task o in uno suo? → **in questo task,
  allargando lo scope**, frontend compreso.

### Ignoto

- **D4** — `->latest()` con la join dà un errore di ambiguità su `created_at`? → **si verifica con un
  test unitario**, non a occhio. Il test viene prima della rimozione: se l'errore c'è, `F9` non è
  pulizia ma un guasto su una colonna ordinabile e cambia priorità.
- **D6** — quale strumento misura la complessità e con quale soglia? → **SonarQube**, e il risultato si
  **riporta a mano, iterando**: si modifica, si rilancia la scansione, si legge il numero. La verifica
  resta quindi `man`, e nel piano è dichiarata tale invece di fingere un comando che non esiste.

### Come va fatta la scomposizione — indicazione del developer

Non «spostare righe»: **riorganizzare il codice in funzioni e, dove serve, in 2-3 file separati**,
cioè 2-3 classi. Con, per ciascuna:

- **unit test per classe** — ogni pezzo si prova da solo;
- **unit test di composizione** — l'intero meccanismo, con prove su **flussi e condizioni diversi**.

È un requisito sulla forma del risultato, non solo sul numero: una classe che non si può provare da
sola non ha risolto il problema che il numero segnalava.

## 5. Consigli

Le raccomandazioni **precedono** le risposte del § 4. Dove il developer ha deciso diversamente vale la
sua risposta; la riga qui sotto dice cosa avevo consigliato — serve a chi rilegge la decisione fra sei
mesi, non a rimetterla in discussione.

| Domanda | Raccomandazione | Esito |
|---|---|---|
| **D1** | Sì, e non è negoziabile: i test dei sei rami prima, la scomposizione dopo. | **accolta** |
| **D2** | Rimuovere `user.username` dalle colonne ordinabili e aprire la correzione vera a parte. | **non accolta, e meglio così**: si corregge la join. La mia raccomandazione poggiava anche su una premessa sbagliata — gli audit di utenti cancellati che sparivano — smentita da `F6a` |
| **D3** | Serve la tua risposta; se un amministratore è globale per definizione va **scritto**. | **accolta nella parte che conta**: nessun tenant, e lo si scrive |
| **D4** | Verificarlo prima di toccare la riga. | **accolta, con il metodo precisato**: un test unitario |
| **D5** | Task suo: è un cambiamento di contratto col frontend. | **non accolta**: si fa qui, allargando lo scope |
| **D6** | Finché lo strumento non è nel repo la verifica resta `man`. | **confermata**: SonarQube, misura riportata a mano iterando |

Il piano: [action-plan.md](./action-plan.md).
