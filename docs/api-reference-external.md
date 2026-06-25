# API Reference — Accesso esterno tramite Swagger

## Prerequisiti

Per gestire Provider, Ruoli e assegnazioni utente tramite API occorre un **client Passport**. Richiederlo al team IDP, che eseguirà:

```bash
php artisan passport:client --password --name="nome-client"
```

Il team IDP fornirà:

- `client_id`
- `client_secret`

Conservare queste credenziali: sono necessarie per autenticarsi su Swagger e per qualsiasi chiamata API da sistemi automatizzati.

---

## Swagger

L'interfaccia interattiva per esplorare e testare le API è disponibile all'indirizzo:

**https://idp-staging.newtimegroup.it/api/documentation**

### Autenticazione in Swagger

1. Aprire la pagina Swagger
2. Cliccare su **Authorize** (in alto a destra)
3. Nella sezione **OAuth2**, inserire:
    - `client_id` fornito dal team IDP
    - `client_secret` fornito dal team IDP
    - `username` e `password` di un utente IDP con ruolo `admin`
4. Cliccare **Authorize**

Tutte le richieste successive includeranno automaticamente il Bearer token.

### Autenticazione programmatica

Per chiamate da sistemi automatizzati (CI, script, backend):

```bash
curl -X POST https://idp-staging.newtimegroup.it/oauth/token \
  -H "Content-Type: application/json" \
  -d '{
    "grant_type": "password",
    "client_id": "<client_id>",
    "client_secret": "<client_secret>",
    "username": "<username>",
    "password": "<password>",
    "scope": ""
  }'
```

Risposta:

```json
{
    "token_type": "Bearer",
    "expires_in": 86400,
    "access_token": "eyJ0eXAiOiJKV1Qi...",
    "refresh_token": "def50200..."
}
```

Usare `access_token` come Bearer token:

```
Authorization: Bearer eyJ0eXAiOiJKV1Qi...
```

---

## Endpoint disponibili tramite client Passport

I seguenti endpoint sono accessibili con il Bearer token OAuth2. Per dettagli completi su parametri, filtri e paginazione, consultare direttamente Swagger.

### Providers

| Metodo   | Path                     | Descrizione                            |
| -------- | ------------------------ | -------------------------------------- |
| `GET`    | `/api/v1/providers`      | Lista provider (paginata, ricercabile) |
| `POST`   | `/api/v1/providers`      | Crea un nuovo provider                 |
| `PUT`    | `/api/v1/providers/{id}` | Aggiorna provider                      |
| `DELETE` | `/api/v1/providers/{id}` | Elimina provider (soft delete)         |

**Campi per la creazione:**

| Campo        | Tipo   | Obbligatorio | Note                                                                                       |
| ------------ | ------ | ------------ | ------------------------------------------------------------------------------------------ |
| `name`       | string | Sì           | Nome display del provider                                                                  |
| `url`        | string | Sì           | URL base dell'applicazione                                                                 |
| `domain`     | string | Sì           | Dominio (es. `app-x.example.com`)                                                          |
| `protocol`   | string | No           | `http` o `https`                                                                           |
| `logoutUrl`  | string | Sì           | URL di logout (verrà gestito in futuro) dell'applicazione                                  |
| `secret_key` | string | Sì           | Stringa 32 caratteri per firma HS256. Comunicare al team esterno come `IDP_SIGNATURE_KEY`. |

### Roles

| Metodo   | Path                 | Descrizione                                |
| -------- | -------------------- | ------------------------------------------ |
| `GET`    | `/api/v1/roles`      | Lista ruoli (filtrabile per `provider_id`) |
| `POST`   | `/api/v1/roles`      | Crea un ruolo                              |
| `GET`    | `/api/v1/roles/{id}` | Dettaglio ruolo                            |
| `PUT`    | `/api/v1/roles/{id}` | Aggiorna ruolo                             |
| `DELETE` | `/api/v1/roles/{id}` | Elimina ruolo                              |

**Campi per la creazione:**

| Campo         | Tipo    | Obbligatorio |
| ------------- | ------- | ------------ |
| `name`        | string  | Sì           |
| `provider_id` | integer | Sì           |

### Users

| Metodo   | Path                 | Descrizione                                   |
| -------- | -------------------- | --------------------------------------------- |
| `GET`    | `/api/v1/users`      | Lista utenti (ricercabile per username/email) |
| `POST`   | `/api/v1/users`      | Crea un utente                                |
| `GET`    | `/api/v1/users/{id}` | Dettaglio utente                              |
| `PUT`    | `/api/v1/users/{id}` | Aggiorna utente                               |
| `DELETE` | `/api/v1/users/{id}` | Elimina utente                                |

### Provider-User-Roles

| Metodo   | Path                               | Descrizione                    |
| -------- | ---------------------------------- | ------------------------------ |
| `GET`    | `/api/v1/provider-user-roles`      | Lista assegnazioni             |
| `POST`   | `/api/v1/provider-user-roles`      | Crea assegnazione ruolo-utente |
| `GET`    | `/api/v1/provider-user-roles/{id}` | Dettaglio assegnazione         |
| `PUT`    | `/api/v1/provider-user-roles/{id}` | Aggiorna assegnazione          |
| `DELETE` | `/api/v1/provider-user-roles/{id}` | Elimina assegnazione           |

**Campi per la creazione:**

| Campo         | Tipo    | Obbligatorio |
| ------------- | ------- | ------------ |
| `provider_id` | integer | Sì           |
| `user_id`     | integer | Sì           |
| `role_id`     | integer | Sì           |
