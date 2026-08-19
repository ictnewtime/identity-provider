# Analisi — nessuno crea più la sessione: il rinnovo rifiuta all'infinito

**Identificatori**: `TSB` = task session-bootstrap

Stato: da approvare · Data: 2026-08-19

## 1. Obiettivo

Far sì che un utente possa **entrare in un'applicazione esterna**. Oggi, su staging, non può: la
sessione per quella coppia utente+provider non esiste, il solo meccanismo che poteva crearla ora
rifiuta, e il client riprova in un ciclo che non finisce.

Alla fine devono essere vere due cose che oggi si escludono a vicenda:

1. un primo accesso a un'applicazione **crea** la sessione;
2. una sessione **revocata** da `/admin/sessions` **non** si ricrea da sola (`VDF14`, chiuso il
   2026-08-13 e da non riaprire).

**Perché adesso**: è un blocco in esercizio su staging, non un debito. E perché **l'ho causato io**:
il rifiuto viene da `TTR08`, il punto che ha chiuso `VDF14` mettendo `canCreate: false` sull'exchange.
Quel punto ha chiuso la falla della revoca e, insieme, l'unica porta da cui una sessione poteva
nascere. La suite non l'ha visto: i suoi test aprono **sempre** la sessione prima di scambiare (`F7`).

## 2. Situazione attuale

### Cosa dice il log, letto riga per riga

| # | Fatto | Prova |
|---|---|---|
| F1 | Il messaggio ripetuto è quello di `TTR08`: `session.error.renew_refused.no_session`. Lo emette `getValidProviderToken()` quando **non trova nessuna sessione** e `canCreate` è falso | `lang/it.json:404`, `app/Services/SessionService.php:106-117` |
| F2 | I provider nel log sono **3, 4, 14** — mai `1`. Il provider `1` è l'IdP stesso (`config/idp.php:4`): quindi quelle righe **non** vengono dal rinnovo interno, ma dall'**exchange** chiamato dalle applicazioni esterne | log del 2026-08-19, ore 08:20-08:21 |
| F3 | Si ripete ogni 10-30 secondi per lo stesso utente e provider. Non è un caso isolato: è un **ciclo** — il client ritenta, l'IdP rifiuta, il client ritenta | quattro righe per l'utente 9, tre per l'utente 8 |
| F4 | La seconda riga — «Master Token assente (né in request né in coda)» — è un'**altra** cosa, e viene da un altro file: l'utente ha la sessione Laravel viva sull'IdP ma **non ha il cookie del master token** | `app/Http/Middleware/RedirectIfAuthenticated.php:54` |

### Perché la sessione non c'è, e perché non può più nascere

| # | Fatto | Prova |
|---|---|---|
| F5 | **Il login verso un'applicazione esterna non crea nessuna sessione.** Con `provider_id` valorizzato, `LoginController` genera il master token, eventualmente accoda il cookie e fa `Inertia::location($redirectUrl)`. Nessuna chiamata a `getValidProviderToken`, nessuna riga in `sessions` | `app/Http/Controllers/JwtAuth/LoginController.php:148-151` |
| F6 | **Nemmeno il SSO trasparente la crea**: `RedirectIfAuthenticated` controlla accesso e master token, poi `redirect()->away()`. Anche qui nessuna sessione | `app/Http/Middleware/RedirectIfAuthenticated.php:80-91` |
| F7 | **L'unica porta era l'exchange**, e dal 2026-08-13 è chiusa: `SessionController::get_token()` passa `canCreate: false`. Restano due chiamanti che possono creare — il login **verso l'IdP stesso** e il reset password (`respondWithSsoRedirect`) — nessuno dei due copre il primo accesso a un'app esterna | `SessionController.php:154-160`; `grep -rn getValidProviderToken app/` dà quattro chiamanti |
| F8 | **Le sessioni si cancellano da sole, e nessuno le rifà.** `validateSession()` elimina la riga quando la sessione è scaduta **e** quando cambia lo user agent — un aggiornamento del browser basta. Dopo la cancellazione si ricade in `F7`: il vicolo è cieco | `app/Services/SessionService.php:161-183` |
| F9 | Da `TTR03`, `expires_at` vale quanto il **master token** (28800 s). Quindi la porta si chiude da sé ogni 8 ore anche senza che nessuno revochi niente | `SessionService.php:125`, punto `TTR03` |

### La seconda riga del log, che è un difetto a parte

| # | Fatto | Prova |
|---|---|---|
| F10 | Al login il cookie del master token si accoda **solo se** il provider di destinazione sta nella stessa zona di dominio dell'IdP: `if ($ssoData["isSameDomainZone"])`. Se l'utente entra puntando a un'app cross-domain, **l'IdP non tiene copia del proprio master token** | `LoginController.php:142-146` |
| F11 | Il dominio del cookie è quello dell'**IdP** (`cookieCretion($masterToken, $masterProvider->id, …)`), non quello del provider di destinazione. Quindi la condizione di `F10` sta decidendo in base al **destinatario sbagliato**: se il cookie sia leggibile dall'app è un'altra domanda, e ha già la sua risposta — il token in URL | `TokenProviderService.php:152-177`, `resolveCrossDomainRedirect()` |
| F12 | Il seguito è la riga del log: l'utente torna sull'IdP, la sessione Laravel è viva, il master token non c'è, e `RedirectIfAuthenticated` fa logout forzato | `RedirectIfAuthenticated.php:50-56` |

### Perché i test non l'hanno visto

| # | Fatto | Prova |
|---|---|---|
| F13 | I sei test dell'exchange chiamano **sempre** `apriSessione()` prima di scambiare: provano il rinnovo, la revoca e il dispositivo diverso, mai il **primo accesso**. Il caso «nessuna sessione, e non ce n'è mai stata una» non è coperto da nessun test | `tests/Feature/Auth/TokenExchangeTest.php:98-181` |
| F14 | Nemmeno `SessionRevocationTest` lo copre: parte anch'esso da una sessione aperta | stesso schema |

### Dipendenze e breaking change

- **`VDF14` non si riapre.** Togliere `canCreate: false` risolverebbe il blocco in un minuto e
  rimetterebbe la falla: dopo un logout amministrativo la sessione tornerebbe alla prima richiesta.
  È la strada (a) del § 3, ed è scartata.
- **Il difetto è già in esercizio su staging**: qualunque cosa si decida, oggi gli utenti di quelle
  applicazioni non entrano.
- **`anagrafe` e le altre app non vanno toccate**: il difetto è qui. L'estensione fa la cosa giusta —
  chiede l'exchange col master token — ed è l'IdP a rispondere 403.

## 3. Analisi

### La radice: «revocata» e «mai esistita» oggi si scrivono allo stesso modo

`destroyAllUserSessions()` **cancella la riga**. Dopo la cancellazione il database dice «non c'è
nessuna sessione» — esattamente ciò che dice per un utente che a quell'applicazione non è mai entrato.
`TTR08` ha insegnato al codice a rifiutare in quel caso, e ha ragione **per uno** dei due significati.
Non poteva distinguerli, perché l'informazione che li distingue non esiste da nessuna parte.

### Le quattro strade

| Strada | Cosa comporta | Perché sì / perché no |
|---|---|---|
| **(a) Rimettere `canCreate: true` sull'exchange** | una riga | **Scartata**: riapre `VDF14`. Il logout amministrativo tornerebbe a durare una richiesta |
| **(b) Creare la sessione dove avviene il login** | `LoginController` e `RedirectIfAuthenticated` creano la sessione per il provider di destinazione, come già fanno per l'IdP | È il modello che `TTR08` dichiarava: **il login crea, l'exchange rinnova**. Piccola e coerente. Il punto delicato è `IP` e `user agent`: quelli visti dall'IdP e quelli che l'app manda nell'exchange devono coincidere, o il rinnovo dopo rifiuterà per «altro dispositivo» |
| **(c) Distinguere la revoca dalla mancanza** | una lapide: `deleted_at` sulle sessioni (soft delete) oppure una colonna `revoked_at`; l'exchange crea se non c'è lapide, rifiuta se c'è | È la correzione della **radice**, non del sintomo. Rende di nuovo possibile il primo accesso senza riaprire `VDF14`, e vale anche per le cancellazioni di `F8`. Costa una migrazione e un criterio di scadenza della lapide |
| **(d) Un endpoint di primo accesso separato** | l'app chiede «apri sessione» invece di «rinnova» | Sposta il problema sul client: le app dovrebbero cambiare, e sono più d'una. Contraddice `F7` dell'analisi di `TTR`, dove si è scelto di non far cambiare i client |

**(b) e (c) non si escludono.** (b) rimette in piedi il caso normale; (c) copre anche ciò che (b) non
tocca — la sessione cancellata da `validateSession` per cambio di user agent (`F8`), dove il login non
avviene e quindi non c'è nessun momento in cui ricrearla.

### Il vincolo IP + user agent, se si sceglie (b)

La sessione nascerebbe con l'IP e lo user agent visti **dall'IdP**; l'exchange arriva dall'app, che
manda `ip_address` e `user_agent` presi dalla **sua** richiesta. Stesso browser, quindi stesso user
agent; l'IP coincide se entrambe le applicazioni vedono lo stesso indirizzo. Dietro un proxy che non
propaga `X-Forwarded-For` **non coincide**, e il rinnovo successivo rifiuterebbe per «altro
dispositivo» — un difetto identico a quello di oggi, con un messaggio diverso. Va verificato su
staging prima di considerare (b) sufficiente (`D3`).

### Codice cancellato

Nessuno in (b). In (c) niente si cancella: si aggiunge una colonna e si cambia il criterio di lettura.

### Il secondo difetto, indipendente

`F10`-`F12`: il cookie del master token va accodato **sempre** al login, perché il suo dominio è
quello dell'IdP. La condizione attuale decide guardando il provider di destinazione, che per quel
cookie non c'entra. È una riga, ma tocca l'autenticazione: va con un test.

## 4. Da decidere

### Vincoli

- **`D1`** — quale strada: **(b)**, **(c)**, o **(b) e poi (c)**? Da questa risposta dipende se serve
  una migrazione.
- **`D2`** — se si sceglie (c): la lapide **quanto dura**? Una revoca che vale per sempre impedisce
  all'utente di rientrare anche dopo un nuovo login; una che dura quanto il master token (8 ore) copre
  la finestra che `VDF14` proteggeva e poi lascia rientrare.

### Conflitti

- **`D3`** — su staging, l'IP che vede l'IdP e quello che l'app manda nell'exchange **coincidono**?
  Se no, la strada (b) da sola non basta. Si legge dal log: `ip_address` della riga in `sessions`
  contro quello della richiesta di exchange.
- **`D4`** — la cancellazione per **cambio di user agent** (`F8`) è voluta? Oggi, unita a `F7`,
  chiude fuori l'utente per sempre. Se è voluta, (c) diventa necessaria; se non lo è, va tolta.

### Ignoto

- **`D5`** — gli utenti 8 e 9 del log avevano una sessione **prima** del 13 agosto, cancellata poi da
  scadenza o cambio di user agent, oppure non l'hanno mai avuta? Cambia la stima di quanti utenti sono
  bloccati adesso. Si legge da `sessions` su staging — è una **lettura**, e va chiesta al developer.
- **`D6`** — il ciclo del client (`F3`) ha un tetto di tentativi? Se non ce l'ha, ogni utente bloccato
  è anche un generatore di traffico verso l'IdP.

## 5. Consigli

- **`D1` → (b) subito, (c) dopo.** (b) sblocca l'esercizio con una modifica piccola in un punto che
  già sa creare sessioni; (c) è la correzione vera e merita il suo tempo, perché tocca lo schema. Fare
  solo (c) lascia staging fermo più a lungo; fare solo (b) lascia aperto il vicolo cieco di `F8`.
- **`D2` → quanto il master token.** La revoca deve coprire la finestra in cui il master token
  revocato è ancora spendibile: è esattamente il buco che `VDF14` descriveva. Oltre, l'utente ha
  rifatto il login e non c'è motivo di tenerlo fuori.
- **`D3` → verificarlo prima di dichiarare chiuso il task.** È la differenza fra «risolto» e «lo
  stesso blocco con un altro messaggio».
- **`D4` → toglierla.** Un cambio di user agent non è un furto: i browser si aggiornano da soli. La
  protezione contro il dispositivo diverso c'è già nel rinnovo, e lì non cancella niente.
- **Prima il test rosso.** Il difetto è nato perché un caso non era coperto: il primo test da scrivere
  è quello che oggi fallisce — primo accesso, nessuna sessione, exchange → oggi 403.
