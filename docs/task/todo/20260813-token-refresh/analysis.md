# Analisi — l'app token scade e nessuno lo rinnova

**Identificatori**: `TTR` = task token-refresh

Stato: da approvare · Data: 2026-08-13 · Difetto: `VDF13`

## 1. Obiettivo

Che una sessione di lavoro duri quanto la sessione SSO — **otto ore**, non trenta minuti — senza che
l'utente venga riportato al login mentre il suo accesso è ancora valido.

Perché adesso: il developer ha rilevato che dopo 30 minuti l'app si slogga invece di chiedere un
token nuovo. Verificato: **l'IdP fa la stessa cosa a sé stesso**, quindi non è un difetto del client.

**La conclusione principale, in anticipo**: il meccanismo di rinnovo **esiste già ed è completo**
(`F7`). Quello che manca è che l'IdP lo usi — e l'unica cosa non ovvia del lavoro è **dove** far
avvenire il rinnovo, perché il middleware che scopre la scadenza è lo stesso che oggi caccia l'utente.

## 2. Situazione attuale

### I due token

| # | Fatto | Prova |
|---|---|---|
| F1 | **App token**: 30 minuti (`app-token-exp-time-seconds` = 1800), firmato **HS256** con la `secret_key` del provider, nel cookie `idp_token_<providerId>` | `DatabaseSeeder.php:110`; `TokenProviderService.php:58`; `IdpTokenExtractor.php:9-11` |
| F2 | **Master token**: 8 ore (`master-token-exp-time-seconds` = 28800), firmato **RS256** con la chiave privata dell'IdP, nel cookie `idp-master-token` | `DatabaseSeeder.php:109`; `TokenProviderService.php:149`; `config/idp.php` |
| F3 | I due tempi sono **deliberatamente diversi**, e il codice dice perché: *«il master DEVE comunque durare più dell'app token, altrimenti il refresh silenzioso è impossibile»* | `TokenProviderService.php:26-27` |

### Cosa succede alla scadenza

| # | Fatto | Prova |
|---|---|---|
| F4 | `Authenticated::handle()` verifica **solo** l'app token. Alla scadenza: `TokenExpiredException` → `forceLogoutAndRedirect(__("auth.token-expired"))` — sessione invalidata, cookie ripuliti, ritorno al login | `app/Http/Middleware/Authenticated.php`; test `test_rifiuta_un_token_scaduto` |
| F5 | Il middleware **non nomina mai** il master token: non può usarlo nemmeno volendo | `grep -rn "master" app/Http/Middleware/Authenticated.php app/Auth/Idp/` → nessuna occorrenza |
| F6 | La riga in `sessions` scade **insieme** all'app token: `expires_at = now() + app-token-exp-time-seconds`. Quindi a 30 minuti scadono insieme il token e la sessione, e `validateSession()` la **cancella** | `SessionService.php:102-105`, `:124-127` |

### Il pezzo che esiste già, e che nessuno usa dall'IdP

| # | Fatto | Prova |
|---|---|---|
| F7 | **Esiste un endpoint di rinnovo completo**: `POST api/v1/token/exchange`, protetto dal middleware `verify_master_token`, che dal master token ricava l'utente e restituisce un **app token nuovo** | `routes/api.php:100-102`; `SessionController::get_token()`; `VerifyMasterToken.php:41` |
| F8 | Il cuore del rinnovo è `SessionService::getValidProviderToken()`: se esiste una sessione con **stesso IP e stesso user agent** e non scaduta restituisce il token vecchio, **altrimenti ne genera uno nuovo** e aggiorna la riga | `SessionService.php:65-107` |
| F9 | Quel metodo è chiamato **solo** dal login (`LoginController`) e da `token/exchange`. Nessun percorso dell'area amministrativa lo raggiunge | `grep -rn "getValidProviderToken" app/` → due chiamate |
| F10 | `validateSession()` restituisce `["status" => 200, "token" => null]`: la forma prevede un token di ritorno, ed è **sempre nullo**. Un secondo pezzo di intenzione dichiarata e non implementata, come `F3` | `SessionService.php:134` |

### Cosa fanno davvero i client — verificato sull'estensione

| # | Fatto | Prova |
|---|---|---|
| F11 | I client **rinnovano già da soli**: l'estensione intercetta `TokenExpiredException`, chiama `token/exchange` col master token, valida il nuovo app token via **JWKS** e prosegue. Il flusso della strada (b) esiste ed è completo | `tmp/idp-extension/src/Http/Middleware/IdpAuthMiddleware.php:147-210` |
| F12 | **`getValidProviderToken()` crea una sessione se non la trova.** Quindi dopo un logout amministrativo il primo `token/exchange` **la ricrea**, con un token nuovo: la revoca dura una richiesta | `SessionService.php:80-107` |
| F13 | `VerifyMasterToken` **non consulta le sessioni**: valida la sola firma RS256. Nessun punto della catena dell'exchange verifica che la sessione esista ancora | `app/Http/Middleware/VerifyMasterToken.php` |

`F12` e `F13` insieme sono un difetto a sé, più grave di quello che questo task cercava:
**`VDF14` — il logout amministrativo non slogga.** Va corretto qui, perché è lo stesso meccanismo
guardato dall'altro lato: se il rinnovo deve funzionare, deve anche poter essere **fermato**.

### Dipendenze e breaking change

- **Il rinnovo tocca il percorso di autenticazione**: una regressione qui non degrada, esclude. La
  rete di test esiste — 8 rami in `AuthenticatedTest`, 5 flussi in `IdpCompositionTest` — e deve
  restare verde **senza modifiche**, come per la scomposizione.
- **Rinnovare significa emettere un token**: se lo si fa nel middleware, ogni richiesta con token
  scaduto ne genera uno. Va deciso cosa fare quando **anche il master** è scaduto (`D2`).
- **Il vincolo IP + user agent di `F8` è una difesa**, non un dettaglio: rinnovare ignorandolo
  significherebbe permettere di continuare una sessione da un altro dispositivo.

## 3. Analisi

### Il difetto non è «manca il rinnovo»: è «l'IdP non usa il rinnovo che ha»

`F7` cambia la forma del lavoro. Un'applicazione esterna che tiene il master token può già chiamare
`token/exchange` e ottenere un app token nuovo: il flusso è scritto, protetto e funzionante.
L'area amministrativa dell'IdP, che il master token ce l'ha **nel cookie**, non lo fa — e quando
l'app token scade, il suo middleware sceglie la via opposta: caccia l'utente.

`F3` e `F10` dicono che non è una scelta dimenticata a metà, è una **intenzione mai completata**: due
punti del codice dichiarano per iscritto un rinnovo che nessuno ha scritto.

### Dove far avvenire il rinnovo — tre strade, e non sono equivalenti

**(a) Nel middleware, in modo trasparente.** Quando l'app token è scaduto, `Authenticated` verifica il
master token; se è valido, chiama `getValidProviderToken()`, mette il nuovo app token in un cookie e
lascia passare la richiesta. L'utente non si accorge di niente, e **le app esterne non cambiano**.
Costo: il middleware smette di essere solo un guardiano e diventa anche un emettitore, e va evitato
che una richiesta concorrente ne emetta due.

**(b) Un endpoint chiamato dal frontend.** L'interfaccia intercetta il 401, chiama `token/exchange`,
riprova. Il middleware resta un guardiano puro. Costo: ogni client deve implementare la logica di
riprova, e quella di oggi — `anagrafe` compresa — non ce l'ha; il primo 401 resta un logout finché
qualcuno non scrive quel codice in ogni applicazione.

**(c) Allungare l'app token.** Non è una soluzione: sposta il problema di qualche ora e allunga la
vita di un token che era corto **di proposito** — è la finestra entro cui una revoca ha effetto.

> **Correzione del 2026-08-13.** Qui avevo scritto che la (a) è «l'unica che risolve il difetto anche
> per i client già scritti». **È falso, e il developer l'ha corretto**: i client **fanno già la (b)**.
> `tmp/idp-extension/src/Http/Middleware/IdpAuthMiddleware.php:147-210` intercetta
> `TokenExpiredException`, chiama `token/exchange` col master token, valida il nuovo app token via
> JWKS e prosegue. Il flusso è scritto, completo e funzionante (`F11`).
>
> Quindi la (a) non serve ai client: **serve all'IdP**, che è l'unico a non farlo. L'argomento con cui
> la raccomandavo era sbagliato; la raccomandazione resta, con la ragione giusta.

### Il vincolo che rende il rinnovo sicuro, e che è già scritto

`F8`: `getValidProviderToken()` rinnova **solo** se IP e user agent coincidono con quelli della
sessione. Non è un dettaglio da conservare per pignoleria: è ciò che impedisce che un token rubato
valga otto ore su un altro dispositivo. Qualunque strada si scelga, il rinnovo deve passare da lì e
non da una scorciatoia.

### La trappola: la sessione scade insieme al token

`F6` è il punto che rischia di far fallire la correzione più elegante. La riga in `sessions` ha
`expires_at = now() + 30 minuti`, quindi allo scadere dell'app token **anche la sessione risulta
scaduta**, e `getValidProviderToken()` non troverebbe una sessione valida da cui ripartire —
genererebbe un token nuovo, ma senza più la garanzia che quella sessione non sia stata revocata nel
frattempo. Il rinnovo va progettato sapendo che **la vita della sessione e quella del token sono due
cose diverse**: la sessione dovrebbe durare quanto il master token, il token quanto la sua finestra
di revoca. Oggi coincidono, ed è probabilmente la ragione per cui il rinnovo non è mai stato scritto.

### Cosa non copre questa analisi

L'applicazione `anagrafe`: sta in un altro repository e il developer ha chiesto di guardarla **dopo**.
Quello che si può dire da qui è che se il rinnovo va nel middleware dell'IdP (strada **a**), il
comportamento di `anagrafe` migliora **senza toccarla**.

## 4. Da decidere

> **Risposte del developer, 2026-08-13.** Sei su sei, più una correzione a una mia affermazione
> sbagliata (§ 3) e una precisazione sulla semantica della sessione che cambia `TTR03`.

### Vincoli

- **D1** — quale strada? → **(a), il rinnovo trasparente nel middleware.** Quando l'app token è
  scaduto, `Authenticated` verifica il master token; se valido chiama `getValidProviderToken()`, mette
  il nuovo app token nel cookie e lascia passare. **Non** per i client, che fanno già la (b) (`F11`):
  per l'IdP, che è l'unico a non farlo.
- **D2** — master token scaduto? → **Logout**, ed è l'unico caso in cui riportare al login è giusto.
  Con un **messaggio diverso** da quello di oggi, che è lo stesso nei due casi.

### Conflitti

- **D3** — la sessione scade insieme al token: si disaccoppia? → **Sì**, `expires_at` legato al master
  token.
  **Con una precisazione del developer che cambia il significato della tabella**: la sessione **non è**
  la vita di un token, è il **segnalatore di dove un utente ha accesso** — la coppia utente/provider.
  Da qui due conseguenze: la sua durata può essere quella del master token, e **il pulsante di logout
  in `/admin/sessions` deve sloggare l'utente da tutte** le sessioni. Quest'ultima oggi **non
  funziona** (`F12`, `F13`, difetto `VDF14`).
- **D4** — il rinnovo su tutte le rotte o solo su quelle di navigazione? → **Solo navigazione, e in
  modo esplicito.**

### Ignoto — entrambi sciolti

- **D5** — la sessione dell'estensione: → il developer ha messo una copia in `tmp/idp-extension`.
  **Analizzata**: l'estensione non subisce la scadenza, la gestisce (`F11`). Ma proprio guardandola è
  emerso `F12` — l'exchange **ricrea** la sessione cancellata — cioè `VDF14`.
- **D6** — i 30 minuti sono un valore ereditato? → **No: sono la finestra di revoca**, e il rinnovo
  trasparente la **conserva**. È l'argomento definitivo contro l'allungamento del token: allungarlo
  significherebbe allargare la finestra in cui una revoca non ha effetto.

## 5. Consigli

| Domanda | Raccomandazione | Esito |
|---|---|---|
| **D1** | (a), il rinnovo trasparente. | **accolta, con il mio argomento smontato**: non serve ai client — serve all'IdP |
| **D2** | Master scaduto = logout, con un messaggio distinto. | **accolta** |
| **D3** | Disaccoppiare: la sessione è il rapporto di fiducia, il token la chiave. | **accolta e precisata**: la sessione è il **segnalatore di dove** un utente ha accesso, e il logout amministrativo deve valere su tutte |
| **D4** | Solo rotte di navigazione. | **accolta** |
| **D5** | Verificarlo prima di implementare. | **fatto**, e ha prodotto `VDF14` |
| **D6** | Se sono la finestra di revoca, il rinnovo la conserva. | **confermata**: lo sono |

Il piano: [action-plan.md](./action-plan.md).
