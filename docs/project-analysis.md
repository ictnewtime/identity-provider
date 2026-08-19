# Analisi del progetto

Il sistema **com'è**, non come sarebbe potuto essere. Si riempie un po' alla volta: ogni voce nasce da
una decisione presa lavorando, e sta qui perché **dal codice non si ricostruisce**.

Cosa ci va: le scelte di struttura, i confini fra i componenti, le cose che si sono decise di **non**
fare. Cosa non ci va: come si prepara l'ambiente ([SETUP.md](SETUP.md), [setup.db.md](setup.db.md)),
come si eseguono i test ([TEST.md](TEST.md)), le convenzioni di codice
([doc-code-guide-line.md](doc-code-guide-line.md)) e il lavoro in corso ([task/](task/index.md)).

---

## Autorizzazione: non esistono tenant

**In questo sistema i tenant non si gestiscono.** Un amministratore vede **tutti** i dati, senza
filtri per provider né per organizzazione.

È una **scelta**, non una funzione mancante, e va scritta perché nel codice si manifesta come
un'**assenza** — e un'assenza si legge come una dimenticanza. La lista degli audit
(`AuditController::all()`) non ha nessun `where` di scope, e chi la legge senza questa pagina si
chiede se qualcuno se ne sia dimenticato.

**Dove si vede**: `routes/web.php`, gruppo `admin` con `authenticated` + `role:admin`; la protezione è
sul **ruolo**, non sull'appartenenza dei dati.

**Cosa comporta, se un giorno servissero**: ogni query dell'area amministrativa andrebbe rivista, non
solo quelle nuove. Il costo di introdurli cresce con i dati, non con il codice.

**Traccia**: `D3` e punto `TCC09` in
[static-analysis-findings-v2](task/done/20260812-static-analysis-findings-v2/analysis.md).

---

## Paginazione: il tetto è del server, non del client

Le liste dell'area amministrativa accettano `per_page` dal client, ma il **massimo lo decide il
server**. `AuditController` usa `PER_PAGE_DEFAULT = 25` e `PER_PAGE_MAX = 200`.

La ragione non è di stile: la tabella `audits` è quella che cresce più in fretta di tutte, e senza
tetto `?per_page=1000000` caricherebbe in memoria un milione di righe. Non serve un attacco — basta un
frontend che sbaglia un parametro.

**Un valore assurdo non significa «nessun limite»**: `0` e i negativi vengono riportati a `1`, non
interpretati come «tutte». È l'errore classico di un tetto messo solo verso l'alto, e c'è un test che
lo copre.

**Traccia**: difetto `VDF03`, punto `TCC04`.

---

## Audit: l'attore è una relazione polimorfa

La colonna `audits.user_type` esiste e va usata: l'attore di un audit può essere un **utente**
(`App\Models\User`) o un **client Passport** (`Laravel\Passport\Client`). La chiave è la **coppia**
`(user_id, user_type)`, che ha anche un indice dedicato.

Unire sul solo `user_id` attacca l'audit di un client all'utente con lo stesso `id` numerico: è
successo, ed è costato un difetto (`VDF02`). E la join dev'essere **esterna**, perché `user_id` è
nullable: gli audit di sistema non hanno attore, e una join interna li farebbe sparire dalla lista.

**Conseguenza aperta**: un client Passport cancellato lascia i suoi audit senza attore, perché
`Passport\Client` non ha soft delete. La regola è che **un client si revoca, non si cancella** —
la colonna `revoked` esiste già. Difetto `VDF09`.
