# Guida all'integrazione con l'IDP NewTimeGroup

## 1. Obiettivo

Ottenere **username e ruoli** dell'utente autenticato per gestire l'accesso ai componenti dell'applicazione. Il sistema IDP centralizza l'autenticazione e distribuisce i ruoli specifici di ogni applicazione tramite token JWT.

---

## 2. Concetti fondamentali

| Entità               | Descrizione                                                                                                                |
| -------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| **Provider**         | L'applicazione esterna registrata sull'IDP. Riceve un `ID` univoco e una `secret_key` a 32 caratteri.                      |
| **Ruolo**            | Permesso specifico per un Provider (es. `admin`, `editor`). I ruoli di un Provider non hanno effetto sugli altri.          |
| **ProviderUserRole** | Relazione che assegna un Ruolo a un Utente per un Provider specifico.                                                      |
| **Master Token**     | Token JWT RS256 emesso al login dall'IDP centrale. Contiene i dati anagrafici dell'utente. Condiviso tra tutte le app SSO. |
| **App Token**        | Token JWT HS256 specifico per ogni Provider. Contiene `user.id` e i ruoli dell'utente per quella specifica applicazione.   |

---

## 3. Schema del flusso SSO

### Flusso web (browser)

```
Utente → App-X (rotta protetta)
              │
       [IdpAuth middleware]
              │
              ├─ A) Nessun token presente
              │       └─ redirect IDP login
              │            ├─ IDP autentica l'utente
              │            ├─ genera master token (RS256)
              │            ├─ genera app token (HS256)
              │            └─ redirect → App-X
              │
              ├─ B) Token in URL (?token=…)
              │       └─ salva come cookie
              │            └─ redirect pulito
              │
              ├─ C) App token valido
              │       └─ accesso garantito
              │
              └─ D) App token scaduto / assente
                      ├─ 1. valida master token (JWKS)
                      ├─ 2. POST api/v1/token/exchange
                      ├─ 3. riceve nuovo app token
                      └─ 4. accesso garantito
```

### Flusso API (Bearer token)

Per le chiamate alle rotte `api/*` dell'app, idp-extension cerca il master token nel parametro `?token=`, poi nel cookie `nt-idp-mt-<ambiente>`, infine nell'header `x-master-token`; l'app token nel cookie `nt-idp-at-<id>-<ambiente>` o nell'header `x-app-token`. L'header `Authorization` **non** viene letto: resta libero per l'autenticazione Passport dell'app. In caso di errore viene restituito un JSON con `error` e `redirect_url` anziché un redirect HTTP.

---

## 4. Struttura dei token JWT

### Master Token (RS256)

Firmato con chiave privata RSA dell'IDP. Verificabile tramite `GET /.well-known/jwks.json`.

**Esempio decodificato:**

```json
{
    "iss": "https://idp-staging.newtimegroup.it",
    "iat": 1780912263,
    "exp": 1780941063,
    "sub": "8",
    "payload": {
        "user": {
            "id": 8,
            "username": "mario.rossi",
            "email": "mario.rossi@newtimegroup.it",
            "name": "Mario",
            "surname": "Rossi"
        }
    }
}
```

| Campo          | Descrizione                          |
| -------------- | ------------------------------------ |
| `iss`          | URL dell'IDP che ha emesso il token  |
| `iat`          | Timestamp di emissione (Unix)        |
| `exp`          | Timestamp di scadenza (Unix)         |
| `sub`          | ID dell'utente (stringa)             |
| `payload.user` | Dati anagrafici completi dell'utente |

### App Token (HS256)

Firmato con la `secret_key` del Provider (`IDP_SIGNATURE_KEY`). Contiene i ruoli specifici di quella applicazione.

**Esempio decodificato:**

```json
{
    "iss": "https://idp-staging.newtimegroup.it",
    "iat": 1780912245,
    "exp": 1780914045,
    "nbf": 1780912245,
    "jti": "bc89e1b209d1ad65f538",
    "sub": "8",
    "prv": 2,
    "aud": "app-x.example.com",
    "payload": {
        "user": {
            "id": 8
        },
        "roles": [{ "id": 3, "name": "admin" }]
    }
}
```

| Campo             | Descrizione                                             |
| ----------------- | ------------------------------------------------------- |
| `prv`             | ID del Provider (corrisponde a `IDP_CLIENT_ID`)         |
| `aud`             | Dominio del Provider                                    |
| `payload.user.id` | ID dell'utente sull'IDP                                 |
| `payload.roles`   | Array di ruoli assegnati all'utente per questo Provider |

> **Nota**: `payload.user` nell'app token contiene solo `id`. Per ottenere nome, email e username occorre decodificare il master token (`nt-idp-mt-<ambiente>`).

---

## 5. Cookie e trasporto del token

| Ambiente               | Meccanismo                                                                                                       | Nome cookie                 |
| ---------------------- | ---------------------------------------------------------------------------------------------------------------- | --------------------------- |
| **Produzione**         | Cookie HTTP-Only, SameSite=Lax, Secure se HTTPS                                                                  | `nt-idp-at-{IDP_CLIENT_ID}-{ambiente}` |
| **Localhost/sviluppo** | Master token accodato come `?token=…` nella URL di redirect; idp-extension lo converte automaticamente in cookie | `nt-idp-at-{IDP_CLIENT_ID}-{ambiente}` |

Il nome del cookie è `nt-idp-at-`, l'ID del Provider (`IDP_CLIENT_ID`) e l'ambiente dell'IdP (`staging` o `production`, che l'app ricava dal proprio `APP_ENV`: `production` → `production`, altrimenti `staging`). Esempio: Provider con ID `5` sull'IdP di staging, il cookie si chiama `nt-idp-at-5-staging`.

> **Valore di `?token=`**: è il **master token JWT** completo (RS256, verificabile via JWKS), non un identificativo o un nome simbolico. L'app deve trattarlo come master token — validarlo via JWKS e usarlo per il token exchange — esattamente come il valore del cookie.

---

## 6. Integrazione con idp-extension

Il pacchetto **idp-extension** gestisce internamente l'intero ciclo SSO (redirect, token exchange, cookie). È disponibile per:

- **PHP (Laravel)** — pacchetto Composer
- **Node.js** — pacchetto npm

Per altri linguaggi o framework, contattare il team IDP per valutare la disponibilità di un'estensione o di una libreria dedicata.

### 6.1 Configurazione `.env` (Laravel e Node.js)

```env
# Obbligatorie
IDP_CLIENT_ID=<ID del Provider assegnato dall'IDP>
IDP_URL_WEB="https://idp-staging.newtimegroup.it"
IDP_SIGNATURE_KEY=<stringa 32 caratteri alfanumerici, condivisa con il team IDP>
IDP_URL_TOKEN_EXCHANGE=api/v1/token/exchange

# Opzionali
IDP_URL_JWKS=.well-known/jwks.json
IDP_REQUEST_TIMEOUT_SEC=5
# Solo se app e IDP girano in Docker su reti isolate:
IDP_URL_M2M="http://host.docker.internal:8001"
```

> **`IDP_CLIENT_ID`**: è l'ID del record Provider creato sull'IDP. Il team IDP lo comunica al momento della registrazione.
>
> **`IDP_SIGNATURE_KEY`**: è la `secret_key` configurata sul Provider nell'IDP, usata per firmare e verificare l'app token. Va concordata tra i due team prima dell'integrazione e comunicata fuori banda (non è esposta dalle API).

### 6.2 Utilizzo del middleware (Laravel)

```php
// routes/web.php
Route::middleware(["idp.auth"])->group(function () {
    Route::get("/dashboard", [DashboardController::class, "index"]);
});
```

### 6.3 Leggere username e ruoli

Dal token già verificato dal middleware è possibile accedere a:

```php
// Username e dati anagrafici → dal master token (cookie nt-idp-mt-<ambiente>)
$user = $request->idp_user; // { id, username, email, name, surname }

// Ruoli → dall'app token (cookie nt-idp-at-{IDP_CLIENT_ID}-{ambiente})
$roles = $request->idp_roles; // [{ id, name }, ...]

// Verificare un ruolo specifico
$isAdmin = collect($roles)->contains("name", "admin");
```

> Verificare la documentazione specifica di idp-extension per i nomi esatti degli attributi esposti dal middleware.

---

## 7. Procedura onboarding Provider — operazioni lato IDP

Queste operazioni vengono eseguite dal team IDP prima dell'integrazione.

### Step 1 — Creare il client Passport

```bash
php artisan passport:client --password --name="nome-client-esterno"
```

Il nome non deve corrispondere a un username presente nella tabella `users`. Il comando restituisce `client_id` e `client_secret` da fornire al team esterno per l'accesso allo Swagger.

### Step 2 — Accedere a Swagger

URL: **https://idp-staging.newtimegroup.it/api/documentation**

### Step 3 — Autenticarsi in Swagger

1. Cliccare su **Authorize**
2. Nella sezione OAuth2, inserire `client_id` e `client_secret` ottenuti al passo precedente
3. Cliccare **Authorize** per ottenere il Bearer token

### Step 4 — Creare il Provider

`POST /api/v1/providers`

```json
{
    "name": "Nome applicazione esterna",
    "url": "https://app-x.example.com",
    "domain": "app-x.example.com",
    "protocol": "https",
    "logoutUrl": "https://app-x.example.com/logout",
    "secret_key": "<stringa 32 caratteri alfanumerici>"
}
```

Risposta: `{ "provider": { "id": 5, ... } }` — annotare l'`id`, è il valore di `IDP_CLIENT_ID`.

La `secret_key` deve essere comunicata al team esterno come `IDP_SIGNATURE_KEY` **fuori banda** (non è esposta nelle risposte API).

### Step 5 — Creare i ruoli del Provider

`POST /api/v1/roles`

```json
{
    "name": "admin",
    "provider_id": 5
}
```

Ripetere per ogni ruolo necessario (es. `editor`, `viewer`).

### Step 6 — Assegnare i ruoli agli utenti

`POST /api/v1/provider-user-roles`

```json
{
    "provider_id": 5,
    "user_id": 8,
    "role_id": 3
}
```

Ripetere per ogni coppia utente-ruolo.

### Step 7 — Verifica

Effettuare il login con un utente assegnato e verificare che il cookie `nt-idp-at-5-staging` (`nt-idp-at-5-production` in produzione) sia presente e contenga i ruoli attesi nel payload.

---

## 8. Endpoint di riferimento per l'integrazione

Questi endpoint sono usati direttamente da idp-extension e raramente vanno invocati manualmente. Sono documentati qui per completezza e per chi integra su stack non supportati.

### Avvio del flusso SSO (redirect di login)

Il browser viene rediretto all'IDP con i parametri `provider_id` e `redirect_to` (è ciò che produce `IdpServiceClient.getLoginUrl()`):

```
GET {IDP_URL_WEB}?provider_id=5&redirect_to=https://app-x.example.com/auth/callback
```

| Parametro     | Descrizione                                                          |
| ------------- | -------------------------------------------------------------------- |
| `provider_id` | ID del Provider (`IDP_CLIENT_ID`).                                   |
| `redirect_to` | URL dell'app a cui tornare dopo l'autenticazione (il nome è esatto). |

Comportamento dell'IDP:

- Se l'utente **non è autenticato**, mostra il form di login; dopo un login valido torna a `redirect_to`.
- Se l'utente ha **già una sessione IDP attiva** (SSO seamless), viene rediretto subito a `redirect_to` senza nuovo login.
- In entrambi i casi, alla URL di ritorno viene consegnato il **master token**: come cookie (produzione) o accodato in querystring `?token=<master_token>` (localhost). Vedi §5.

> **Validazione di `redirect_to`**: l'IDP rimanda al valore del `redirect_to` solo se il suo host appartiene al `domain` registrato del Provider **oppure** è un host locale (`localhost`, `127.0.0.1`, `::1`, `192.168.*`). Altrimenti usa la `url` registrata del Provider. Registrare quindi su `url`/`domain` del Provider l'host di callback corretto.

> **Autorizzazione**: l'utente deve avere **almeno un ruolo** assegnato per il Provider. In caso contrario l'IDP redirige alla pagina "non autorizzato" e il token exchange risponde `403` (vedi sotto).

### Login web

```
POST /v2/login
Content-Type: application/x-www-form-urlencoded

username=mario.rossi&password=secret&provider_id=5
```

Redirect al Provider con i cookie impostati (o token in URL su localhost).

### Token exchange (master → app token)

```
POST /api/v1/token/exchange
x-master-token: <master_token>
Content-Type: application/json

{
  "provider_id": 5,
  "ip_address": "1.2.3.4",
  "user_agent": "Mozilla/5.0 ..."
}
```

Risposta: **corpo vuoto**, i token negli header (dal 2026-09 è l'unica forma; la rotta `v2` non esiste più):

```
x-app-token: <app_token>
x-master-token: <master_token>   # nuovo se quello presentato aveva più di un'ora, altrimenti lo stesso
```

### Verifica sessione (M2M)

```
GET /api/v1/sessions/check
Authorization: Bearer <app_token>
```

Risposta:

```json
{ "valid": true, "message": "...", "token": null }
```

---

## 9. Integrazione senza idp-extension (client scritto a mano)

Se l'applicazione non è in PHP (Laravel) o Node.js, o non usa idp-extension, il client va scritto a
mano seguendo i passaggi qui sotto. Per dubbi resta il team di sviluppo NewTimeGroup.

### 9.1 Perché un client scritto prima di ottobre 2026 può non funzionare più

Fra fine settembre e ottobre 2026 l'IdP ha cambiato tre cose:

1. la rotta `api/v2/token/exchange` non esiste più e risponde **404**;
2. `api/v1/token/exchange` risponde con il **corpo vuoto** e i token negli **header**
   (`x-app-token`, `x-master-token`). Prima restituiva `{"token": "..."}` nel corpo;
3. i cookie si chiamano `nt-idp-mt-<ambiente>` e `nt-idp-at-<id>-<ambiente>` (vedi §5). I nomi
   vecchi `idp-master-token` e `idp_token_<id>` non esistono più.

I flussi sono due, con credenziali diverse.

### 9.2 Flusso A — Gestione di utenti e ruoli (client Passport)

Serve solo per creare o modificare utenti, ruoli e assegnazioni via API. Per far entrare un utente
nell'app non serve: è il flusso B. Le credenziali sono quelle del client Passport (§7, Step 1).

**A1. Chiedere un access token:**

```bash
curl -X POST https://idp-staging.newtimegroup.it/oauth/token \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"grant_type":"client_credentials","client_id":"<client_id>","client_secret":"<client_secret>"}'
```

La risposta contiene `access_token` ed `expires_in` (secondi).

**A2. Usarlo come Bearer** sulle rotte di gestione (`/api/v1/users`, `/api/v1/roles`,
`/api/v1/providers`, `/api/v1/provider-user-roles`; dettaglio dei campi su Swagger):

```bash
curl https://idp-staging.newtimegroup.it/api/v1/users \
  -H "Authorization: Bearer <access_token>" -H "Accept: application/json"
```

**A3.** Quando l'access token scade (risposta 401) se ne chiede un altro come in A1.

### 9.3 Flusso B — Accesso di un utente all'app (SSO)

Credenziali: l'ID del Provider (`IDP_CLIENT_ID`) e la sua chiave di firma (`IDP_SIGNATURE_KEY`).

**B1. Login.** Se l'utente non è autenticato nell'app, mandarlo all'IdP:

```
https://idp-staging.newtimegroup.it?provider_id=<IDP_CLIENT_ID>&redirect_to=<URL dell'app, codificato>
```

**B2. Ritorno.** Dopo il login l'IdP rimanda a `redirect_to`:

- app su un dominio diverso da `newtimegroup.it`, o con il token in URL attivo: il master token
  arriva nel parametro `?token=<master_token>`;
- app su `*.newtimegroup.it`: il master token è già nel cookie `nt-idp-mt-<ambiente>`.

Il master token è un JWT RS256 che dura 8 ore e contiene l'identità dell'utente (`payload.user`).

**B3. Verificare il master token prima di usarlo:**

- scaricare le chiavi pubbliche da `GET /.well-known/jwks.json`;
- scegliere la chiave con il `kid` dell'header del token (oggi `idp-master-key`);
- verificare la firma RS256 e `exp`;
- **tollerare fino a 60 secondi di scarto fra gli orologi.** L'orologio dell'IdP può essere un po'
  avanti: con tolleranza zero un token appena emesso risulta «emesso nel futuro» e viene rifiutato.
  È già successo su staging.

**B4. Scambio: master token → app token** (l'app token contiene i ruoli per questa app):

```bash
curl -i -X POST https://idp-staging.newtimegroup.it/api/v1/token/exchange \
  -H "x-master-token: <master_token>" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"provider_id":"<IDP_CLIENT_ID>","ip_address":"<ip utente>","user_agent":"<user agent utente>"}'
```

- `provider_id` è obbligatorio; `ip_address` e `user_agent` sono facoltativi.
- Al posto di `x-master-token` si può usare anche `Authorization: Bearer <master_token>`.
- Risposta 200: **corpo vuoto**, i token negli header:

| Header           | Contenuto                                                                                                                                                 |
| ---------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `x-app-token`    | l'app token, JWT HS256, dura 30 minuti                                                                                                                    |
| `x-master-token` | il master token da usare d'ora in poi: lo stesso, oppure uno **nuovo** se quello inviato aveva più di un'ora. Va sempre salvato al posto del precedente. |

Il master token precedente resta valido fino alla sua scadenza.

| Codice | Significato                                                     | Cosa fare                        |
| ------ | --------------------------------------------------------------- | -------------------------------- |
| 401    | master token assente, scaduto o con firma non valida            | rifare il login (B1)             |
| 403    | sessione revocata dall'amministratore, o utente senza ruoli     | l'utente non può entrare nell'app |
| 404    | `provider_id` inesistente, oppure chiamata alla vecchia `/api/v2/...` | correggere l'indirizzo o l'ID |
| 500    | app token non generato: configurazione del Provider sull'IdP    | contattare il team IdP           |

**B5. Verificare e usare l'app token:**

- firma HS256 con `IDP_SIGNATURE_KEY`; controllo di `exp` e `nbf` con la **stessa tolleranza di 60
  secondi** del passo B3 (l'app token ha `nbf` = momento di emissione sull'IdP);
- ruoli in `payload.payload.roles`, id utente in `payload.payload.user.id` (vedi §4);
- se i token si salvano in cookie, usare i nomi di §5: `nt-idp-mt-<ambiente>` e
  `nt-idp-at-<IDP_CLIENT_ID>-<ambiente>`, con `<ambiente>` = `staging` o `production`. Conviene
  impostare la scadenza con `Max-Age` (non solo `Expires`), così un dispositivo con l'ora sbagliata
  non vede il cookie già scaduto.

**B6. Rinnovo.** Quando l'app token scade, cioè ogni 30 minuti, si ripete B4 con il master token
salvato, senza far rifare il login. Solo se B4 risponde 401 si torna a B1.

**B7. Logout.** Mandare l'utente a
`https://idp-staging.newtimegroup.it/sso/logout?provider_id=<IDP_CLIENT_ID>` e cancellare i propri
cookie o la propria sessione.

### 9.4 Controllo rapido (dove un client scritto a mano di solito si rompe)

| Se il client…                                        | Allora                                  |
| ---------------------------------------------------- | --------------------------------------- |
| chiama `/api/v2/token/exchange`                      | passare a `/api/v1/token/exchange`      |
| legge l'app token dal corpo della risposta           | leggerlo dall'header `x-app-token`      |
| ignora l'header `x-master-token` della risposta      | salvarlo: può essere stato ruotato      |
| verifica gli orari dei token con tolleranza zero     | tollerare 60 secondi                    |
| cerca i cookie `idp-master-token` o `idp_token_<id>` | usare i nomi di §5                      |
