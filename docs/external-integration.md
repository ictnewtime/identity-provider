# Guida all'integrazione con l'IDP NewTimeGroup — sessione, token e ruoli

Questo documento serve a chi deve **far entrare gli utenti in un'applicazione** collegata all'IDP e
leggerne **username e ruoli**. Spiega i due token, come nasce e finisce una sessione, lo scambio dei
token, i cookie, e come integrarsi con o senza il pacchetto idp-extension.

La creazione dell'applicazione sull'IDP (Provider, ruoli, assegnazioni), le API di gestione e Swagger
sono in [api-reference-external.md](api-reference-external.md).

| Ambiente   | Indirizzo dell'IDP                     |
| ---------- | -------------------------------------- |
| Staging    | `https://idp-staging.newtimegroup.it`  |
| Produzione | `https://idp.newtimegroup.it`          |

---

## 1. Concetti

| Termine              | Significato                                                                                                                       |
| -------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| **Provider**         | L'applicazione collegata all'IDP. Ha un `ID` (`IDP_CLIENT_ID`) e una `secret_key` di 32 caratteri (`IDP_SIGNATURE_KEY`).           |
| **Ruolo**            | Un permesso valido solo per un Provider (es. `admin`, `editor`).                                                                  |
| **ProviderUserRole** | L'assegnazione di un ruolo a un utente, su un Provider. Senza almeno un ruolo, l'utente non entra in quell'applicazione.           |
| **Master token**     | Dice **chi è** l'utente. JWT RS256 emesso al login, dura 8 ore, vale per tutte le applicazioni.                                     |
| **App token**        | Dice **cosa può fare** l'utente in **una** applicazione: i suoi ruoli. JWT HS256 firmato con la `secret_key` del Provider, dura 30 minuti. |
| **Scambio**          | La chiamata con cui l'applicazione dà il master token all'IDP e riceve l'app token (§4).                                          |

---

## 2. I due token

### Master token (RS256)

Firmato con la chiave privata dell'IDP. Le chiavi pubbliche per verificarlo sono su
`GET /.well-known/jwks.json` (`kid`: `idp-master-key`).

```json
{
    "iss": "https://idp-staging.newtimegroup.it",
    "iat": 1780912263,
    "exp": 1780941063,
    "sub": "8",
    "payload": {
        "user": { "id": 8, "username": "mario.rossi", "email": "mario.rossi@newtimegroup.it", "name": "Mario", "surname": "Rossi" }
    }
}
```

| Campo          | Significato                                   |
| -------------- | --------------------------------------------- |
| `iss`          | indirizzo dell'IDP che l'ha emesso            |
| `iat`, `exp`   | emissione e scadenza, in secondi Unix (8 ore) |
| `sub`          | id dell'utente sull'IDP                       |
| `payload.user` | dati anagrafici dell'utente                   |

### App token (HS256)

Firmato con la `secret_key` del Provider (`IDP_SIGNATURE_KEY`). Contiene i ruoli dell'utente **per
quell'applicazione soltanto**.

```json
{
    "iss": "https://app-x.example.com",
    "iat": 1780912245,
    "exp": 1780914045,
    "nbf": 1780912245,
    "jti": "bc89e1b209d1ad65f538",
    "sub": "8",
    "prv": 5,
    "aud": "app-x.example.com",
    "payload": {
        "user": { "id": 8 },
        "roles": [{ "id": 3, "name": "admin" }]
    }
}
```

| Campo             | Significato                                                    |
| ----------------- | -------------------------------------------------------------- |
| `iat`, `nbf`, `exp` | emissione, inizio validità, scadenza (30 minuti)             |
| `prv`             | id del Provider (`IDP_CLIENT_ID`)                              |
| `aud`             | dominio del Provider                                           |
| `payload.user.id` | id dell'utente sull'IDP                                        |
| `payload.roles`   | ruoli dell'utente su questo Provider                           |

`payload.user` dell'app token contiene solo `id`: username, email e nome si leggono dal master token.

---

## 3. La sessione: come nasce, si rinnova e finisce

### 3.1 Login

1. L'applicazione manda l'utente all'IDP:
   `GET {IDP}?provider_id=<IDP_CLIENT_ID>&redirect_to=<URL dell'app>`.
2. L'utente inserisce le credenziali (o accede con Google). L'IDP controlla che sia abilitato, che la
   password non sia scaduta e che abbia **almeno un ruolo** sul Provider; se no lo ferma sulla pagina
   «non autorizzato».
3. L'IDP emette il **master token** e apre la **sessione**: una riga sul suo database che rappresenta
   «l'utente è entrato», e che scade insieme al master token.
4. L'IDP rimanda a `redirect_to` consegnando il master token (§5): nel cookie per le app su
   `*.newtimegroup.it`, nell'URL (`?token=`) per le altre.

`redirect_to` è accettato solo se il suo host appartiene al `domain` del Provider, oppure è un host
locale (`localhost`, `127.0.0.1`, `::1`, `192.168.*`). Altrimenti l'IDP usa la `url` registrata.

### 3.2 Accesso a un'altra applicazione (SSO)

Se l'utente è già entrato nell'IDP e apre un'altra applicazione, l'IDP non chiede di nuovo le
credenziali: lo rimanda subito indietro col master token. Prima di consegnarlo lo controlla: se è
scaduto, appartiene a un altro utente o viene dall'altro ambiente, ne emette uno nuovo.

### 3.3 Dal master token ai ruoli: lo scambio

L'applicazione, col master token, chiede all'IDP l'**app token** per sé (§4). È l'app token a portare
i ruoli. Ogni 30 minuti l'app token scade e lo scambio si ripete, **senza** far rifare il login.

### 3.4 Rotazione

Se il master token presentato allo scambio ha **più di un'ora**, l'IDP ne emette uno nuovo e lo
restituisce nell'header `x-master-token`. L'applicazione lo salva al posto del precedente. Quello
vecchio resta comunque valido fino alla sua scadenza: un'applicazione che non legge l'header continua
a funzionare, e dopo 8 ore l'utente rifà il login.

### 3.5 Fine della sessione

- **Logout**: l'applicazione manda l'utente a `{IDP}/sso/logout?provider_id=<IDP_CLIENT_ID>`. L'IDP
  cancella le sue sessioni e i cookie, poi rimanda all'applicazione.
- **Revoca**: un amministratore può chiudere la sessione dal pannello dell'IDP. Da quel momento lo
  scambio risponde **403** anche se il master token è ancora valido: la revoca vale subito, al primo
  rinnovo.
- **Scadenza**: dopo 8 ore il master token scade e lo scambio risponde **401**: si rifà il login.

---

## 4. Lo scambio: due rotte

| | `POST /api/v1/token/exchange` | `POST /api/v2/token/exchange` |
|---|---|---|
| Per chi | **compatibilità**: pacchetti idp-extension v1, client scritti a mano | pacchetti idp-extension v2 (è il loro predefinito) |
| Master token in ingresso | `Authorization: Bearer <master>` **oppure** `x-master-token: <master>` | uguale |
| Corpo della richiesta | `provider_id` (obbligatorio), `ip_address`, `user_agent` (facoltativi) | uguale |
| Risposta 200, corpo | **`{"token": "<app_token>"}`** | vuoto |
| Risposta 200, header | `x-app-token: <app_token>`, `x-master-token: <master>` | uguale |
| Rotazione dopo un'ora | sì | sì |

Le due rotte fanno lo stesso scambio; cambia solo il corpo della risposta. La `v1` risponde in
entrambi i modi perché la usano le applicazioni che leggono il corpo (idp-extension v1 e client
scritti prima di ottobre 2026), ma funziona anche con chi legge gli header: un'app con idp-extension
v2 configurata ancora su `v1` non si rompe.

Esempio:

```bash
curl -i -X POST https://idp-staging.newtimegroup.it/api/v1/token/exchange \
  -H "x-master-token: <master_token>" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"provider_id":"5","ip_address":"<ip utente>","user_agent":"<user agent utente>"}'
```

| Codice | Significato                                                     | Cosa fare                          |
| ------ | --------------------------------------------------------------- | ---------------------------------- |
| 200    | scambio riuscito                                                | salvare app token e master token   |
| 401    | master token assente, scaduto o con firma non valida            | rifare il login                    |
| 403    | sessione revocata, oppure utente senza ruoli su questo Provider | l'utente non può entrare nell'app  |
| 404    | `provider_id` inesistente                                       | correggere `IDP_CLIENT_ID`         |
| 500    | app token non generato: configurazione del Provider sull'IDP    | contattare il team IDP             |

---

## 5. I cookie

| Token        | Nome del cookie                        | Esempio                  |
| ------------ | -------------------------------------- | ------------------------ |
| Master token | `nt-idp-mt-<ambiente>`                 | `nt-idp-mt-production`   |
| App token    | `nt-idp-at-<IDP_CLIENT_ID>-<ambiente>` | `nt-idp-at-5-staging`    |

- `<ambiente>` è `staging` o `production`. Nell'applicazione si ricava dal suo `APP_ENV`:
  `production` → `production`, qualunque altro valore → `staging`. Il nome contiene l'ambiente perché
  staging e produzione scrivono sullo stesso dominio `.newtimegroup.it`: con lo stesso nome si
  sovrascrivevano a vicenda.
- **Transizione**: l'IDP scrive il master token anche nel cookie col nome di prima,
  `idp-master-token`, che le applicazioni con idp-extension v1 leggono. Quel cookie è di nuovo comune
  a staging e produzione; verrà tolto quando nessuna applicazione userà più la v1.
- I cookie sono HTTP-Only, SameSite=Lax, Secure su HTTPS, e **non cifrati** da Laravel: devono restare
  leggibili dalle applicazioni.
- Il cookie del master token lo scrive l'IDP sul **proprio** dominio (`.newtimegroup.it`): lo leggono
  le applicazioni sotto quel dominio. Se l'applicazione riceve il token nell'URL (`?token=`), è
  l'applicazione a scriverlo sul suo dominio; il valore è il master token completo.
- La scadenza si imposta con `Max-Age` (oltre a `Expires`): così un dispositivo con l'ora sbagliata
  non vede il cookie già scaduto.

---

## 6. Integrazione con idp-extension (consigliata)

Il pacchetto **idp-extension** fa tutto da solo: redirect al login, verifica del master token, scambio,
rinnovo, cookie, pagina d'errore. Esiste per **PHP (Laravel)** e **Node.js**.

### 6.1 Configurazione `.env`

```env
# Obbligatorie
APP_ENV=production                     # production -> IDP di produzione, altro -> staging
IDP_CLIENT_ID=<ID del Provider>
IDP_URL_WEB=https://idp.newtimegroup.it
IDP_SIGNATURE_KEY=<secret_key del Provider, 32 caratteri>

# Facoltative
# Predefinito dei pacchetti v2: api/v2/token/exchange (i pacchetti v1 usano api/v1/token/exchange).
# Con i pacchetti v2 si può omettere; api/v1/token/exchange funziona comunque.
# IDP_URL_TOKEN_EXCHANGE=api/v2/token/exchange
IDP_URL_JWKS=.well-known/jwks.json
IDP_REQUEST_TIMEOUT_SEC=5
# Solo se app e IDP girano in Docker su reti isolate:
IDP_URL_M2M=http://host.docker.internal:8001
```

`IDP_SIGNATURE_KEY` è la `secret_key` del Provider: la consegna il team IDP, fuori dalle API.

### 6.2 Proteggere le rotte (Laravel)

```php
// routes/web.php
Route::middleware(["idp.auth"])->group(function () {
    Route::get("/dashboard", [DashboardController::class, "index"]);
});
```

In Node.js/Express: `app.get("/dashboard", idpAuth, handler)`.

### 6.3 Leggere utente e ruoli

```php
use NewTimeGroup\IdpClient\IdpService;

$idp = app(IdpService::class);
$user = $idp->getUser($request);              // ['id', 'username', 'email', 'name', 'surname']
$roles = $idp->getRoles($request);            // ['admin', 'editor', ...]
$isAdmin = $idp->hasRole($request, "admin");
```

In Node.js gli stessi metodi su `IdpService`: `getUser(req)`, `getRoles(req)`, `hasRole(req, "admin")`,
`hasAnyRole(req, [...])`.

### 6.4 Comportamenti utili da conoscere

- **Tolleranza sugli orari**: fino a 60 secondi di scarto fra gli orologi di IDP e applicazione.
- **Pagina d'errore**: se il problema è di configurazione (chiave sbagliata, IDP irraggiungibile,
  orologi troppo diversi), l'utente arriva sulla pagina «Accesso non completato» dell'IDP con un
  codice da riferire, invece di girare fra applicazione e login.
- **Protezione anti-loop**: dopo 3 giri verso il login in 2 minuti, il pacchetto si ferma sulla pagina
  d'errore (`too_many_redirects`).

---

## 7. Integrazione senza idp-extension (client scritto a mano)

Per applicazioni non in PHP o Node.js, o che non usano il pacchetto.

1. **Login.** Se l'utente non è autenticato nell'applicazione, mandarlo a
   `{IDP}?provider_id=<IDP_CLIENT_ID>&redirect_to=<URL dell'app, codificato>`.
2. **Ritorno.** Il master token arriva in `?token=` oppure, per le app su `*.newtimegroup.it`, nel
   cookie `nt-idp-mt-<ambiente>` (§5).
3. **Verificare il master token.** Scaricare le chiavi da `/.well-known/jwks.json`, scegliere quella col
   `kid` del token, verificare firma RS256 ed `exp` con **60 secondi di tolleranza** sugli orari.
4. **Scambio** (§4) su `/api/v1/token/exchange`. L'app token si può leggere dal corpo (`token`) o
   dall'header `x-app-token`; **salvare sempre** l'header `x-master-token`, che può essere ruotato.
5. **Verificare l'app token.** Firma HS256 con `IDP_SIGNATURE_KEY`, `exp` e `nbf` con la stessa
   tolleranza di 60 secondi. Ruoli in `payload.roles`, id utente in `payload.user.id`.
6. **Rinnovo.** Quando l'app token scade (30 minuti), ripetere lo scambio. Solo con **401** si torna al
   login.
7. **Logout.** Mandare l'utente a `{IDP}/sso/logout?provider_id=<IDP_CLIENT_ID>` e cancellare la
   propria sessione.

Per creare utenti e ruoli via API serve invece un client Passport: vedi
[api-reference-external.md](api-reference-external.md).

### Controllo rapido (dove un client scritto a mano di solito si rompe)

| Se il client…                                     | Allora                                                  |
| ------------------------------------------------- | ------------------------------------------------------- |
| chiama `/api/v2/token/exchange` e legge il corpo  | sulla `v2` il corpo è vuoto: usare la `v1` o gli header |
| ignora l'header `x-master-token` della risposta   | salvarlo: può essere stato ruotato                      |
| verifica gli orari dei token con tolleranza zero  | tollerare 60 secondi                                    |
| cerca i cookie col nome dell'ambiente sbagliato   | `staging` o `production`, come in §5                    |
| usa `GET /api/v1/sessions/check` per la sessione  | non usarla (§8): verificare l'app token e rinnovarlo    |

---

## 8. Riferimento delle rotte

| Metodo e rotta                          | A cosa serve                                                                                  |
| --------------------------------------- | --------------------------------------------------------------------------------------------- |
| `GET {IDP}?provider_id=…&redirect_to=…` | avvio del login / SSO (§3.1)                                                                  |
| `POST /v2/login`                        | invio del form di login (lo usa la pagina dell'IDP)                                           |
| `POST /api/v1/token/exchange`           | scambio, risposta nel corpo e negli header (§4)                                               |
| `POST /api/v2/token/exchange`           | scambio, risposta solo negli header (§4)                                                      |
| `GET /.well-known/jwks.json`            | chiavi pubbliche per verificare il master token                                               |
| `GET /sso/logout?provider_id=…`         | logout (§3.5)                                                                                 |
| `GET /client/v1/auth-error`             | pagina «Accesso non completato», con `reason` e `provider_id`                                 |
| `GET /api/v1/sessions/check`            | **da non usare**: verifica una riga di sessione per applicazione che lo scambio attuale non crea, quindi risponde «non valida». Nessun pacchetto la usa |

---

## 9. Per chi sviluppa l'IDP

Dettagli interni, che a chi integra non servono.

- **Configurazione**: `config/idp.php`. L'ambiente dell'IDP è il suo `APP_ENV` (ammessi `local`,
  `staging`, `production`, `testing`); se manca o non è valido l'IDP non parte.
- **Nomi dei cookie**: `App\Support\IdpCookies` (`masterTokenName()`, `appTokenName()`,
  `legacyMasterTokenName()`, `forgetLegacy()`). Mai nomi scritti a mano.
- **`TokenProviderService`**: emette i token (`generateMasterToken()`, `generateAppToken()`), verifica o
  riemette il master token da consegnare (`masterTokenFor()`), scrive i cookie (`cookieCretion()`,
  durata in minuti).
- **`SessionService`**: la riga del master token (`upsertMasterSession()`, `masterSessionFor()`), con
  scadenza uguale all'`exp` del token (`masterTokenExpiresAt()`); `redactTokenInUrl()` per non scrivere
  token interi nei log.
- **`SessionController::get_token()`**: le due rotte di scambio; `isV2()` le distingue, `exchange(...,
  withBody)` aggiunge il corpo sulla `v1`, `rotateMasterTokenIfNeeded()` ruota dopo un'ora (soglia nel
  parametro `master-token-rotate-after-seconds`).
- **`RedirectIfAuthenticated`**: il redirect SSO (§3.2); in caso di guasto di configurazione manda a
  `sso.auth-error`.
- **Durate**: parametri `master-token-exp-time-seconds` (8 ore) e `app-token-exp-time-seconds` (30
  minuti), con ripiego su `JWT_MASTER_TTL` e `JWT_APP_TTL`.
- **Test**: `./scripts/run-test-backend.sh` (vedi [TEST.md](TEST.md)).
