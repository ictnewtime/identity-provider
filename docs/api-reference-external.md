# API di gestione dell'IDP — client Passport, Swagger, rotte

Questo documento serve a chi **amministra** l'IDP via API: crea applicazioni (Provider), ruoli,
utenti e assegnazioni. Lo usano il team IDP, gli script automatici e i partner che gestiscono i
propri utenti.

Come si fa **entrare un utente in un'applicazione** (master token, app token, ruoli, sessione) è
spiegato in [external-integration.md](external-integration.md).

| Ambiente   | Indirizzo dell'IDP                     |
| ---------- | -------------------------------------- |
| Staging    | `https://idp-staging.newtimegroup.it`  |
| Produzione | `https://idp.newtimegroup.it`          |

Gli esempi usano staging.

---

## 1. Il client Passport

Le rotte di gestione si chiamano con un **client Passport**, una coppia di credenziali per sistemi e
non per persone. Lo crea il team IDP:

```bash
php artisan passport:client --password --name="nome-client"
```

- Il `--name` **non** deve coincidere con uno `username` della tabella `users`.
- Il comando restituisce `client_id` e `client_secret`. Vanno consegnati a chi userà le API attraverso
  un canale sicuro, e conservati come password: chi li ha può modificare utenti e ruoli.

---

## 2. Ottenere l'access token

```bash
curl -X POST https://idp-staging.newtimegroup.it/oauth/token \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{
    "grant_type": "client_credentials",
    "client_id": "<client_id>",
    "client_secret": "<client_secret>"
  }'
```

Risposta:

```json
{
    "token_type": "Bearer",
    "expires_in": 86400,
    "access_token": "eyJ0eXAiOiJKV1Qi..."
}
```

`access_token` si mette nell'header di ogni chiamata:

```
Authorization: Bearer eyJ0eXAiOiJKV1Qi...
```

Quando scade, cioè quando una chiamata risponde **401**, se ne chiede un altro nello stesso modo.

---

## 3. Swagger

Interfaccia per provare le API dal browser:
**https://idp-staging.newtimegroup.it/api/documentation**

1. Cliccare **Authorize**, in alto a destra.
2. Inserire `client_id` e `client_secret` del client Passport.
3. Cliccare **Authorize**: Swagger ottiene l'access token e lo aggiunge da solo a ogni richiesta.

Su Swagger ci sono il dettaglio di parametri, filtri e paginazione di ogni rotta.

---

## 4. Rotte di gestione

Tutte richiedono l'access token del §2. Prefisso: `/api/v1`.

### Providers (le applicazioni collegate)

| Metodo   | Path                     | Descrizione                            |
| -------- | ------------------------ | -------------------------------------- |
| `GET`    | `/api/v1/providers`      | Lista provider (paginata, ricercabile) |
| `POST`   | `/api/v1/providers`      | Crea un provider                       |
| `GET`    | `/api/v1/providers/{id}` | Dettaglio provider                     |
| `PUT`    | `/api/v1/providers/{id}` | Aggiorna provider                      |
| `DELETE` | `/api/v1/providers/{id}` | Elimina provider (soft delete)         |

Campi per la creazione:

| Campo           | Tipo    | Obbligatorio | Note                                                                                                          |
| --------------- | ------- | ------------ | ------------------------------------------------------------------------------------------------------------- |
| `name`          | string  | Sì           | Nome visualizzato                                                                                             |
| `url`           | string  | Sì           | URL base dell'applicazione: è dove l'IDP rimanda dopo il login, se `redirect_to` non è ammesso                |
| `domain`        | string  | Sì           | Dominio, es. `app-x.example.com`. I `redirect_to` sono accettati solo su questo dominio (o su host locali)    |
| `protocol`      | string  | No           | `http` o `https`                                                                                              |
| `logoutUrl`     | string  | Sì           | URL di logout dell'applicazione                                                                               |
| `secret_key`    | string  | Sì           | 32 caratteri: firma l'app token (HS256). Va consegnata a chi sviluppa l'app come `IDP_SIGNATURE_KEY`          |
| `has_token_url` | boolean | No           | Se vero, l'IDP mette sempre il master token nell'URL di ritorno (`?token=`), anche sullo stesso dominio       |

### Roles (i ruoli di un'applicazione)

| Metodo   | Path                 | Descrizione                                |
| -------- | -------------------- | ------------------------------------------ |
| `GET`    | `/api/v1/roles`      | Lista ruoli (filtrabile per `provider_id`) |
| `POST`   | `/api/v1/roles`      | Crea un ruolo                              |
| `GET`    | `/api/v1/roles/{id}` | Dettaglio ruolo                            |
| `PUT`    | `/api/v1/roles/{id}` | Aggiorna ruolo                             |
| `DELETE` | `/api/v1/roles/{id}` | Elimina ruolo                              |

Campi per la creazione: `name` (string) e `provider_id` (integer), entrambi obbligatori. Un ruolo vale
solo per il suo provider: `admin` di un'applicazione non ha effetto sulle altre.

### Users

| Metodo   | Path                 | Descrizione                                   |
| -------- | -------------------- | --------------------------------------------- |
| `GET`    | `/api/v1/users`      | Lista utenti (ricercabile per username/email) |
| `POST`   | `/api/v1/users`      | Crea un utente                                |
| `GET`    | `/api/v1/users/{id}` | Dettaglio utente                              |
| `PUT`    | `/api/v1/users/{id}` | Aggiorna utente                               |
| `DELETE` | `/api/v1/users/{id}` | Elimina utente                                |

### Provider-User-Roles (chi ha quale ruolo, su quale applicazione)

| Metodo   | Path                               | Descrizione                    |
| -------- | ---------------------------------- | ------------------------------ |
| `GET`    | `/api/v1/provider-user-roles`      | Lista assegnazioni             |
| `POST`   | `/api/v1/provider-user-roles`      | Crea assegnazione ruolo-utente |
| `GET`    | `/api/v1/provider-user-roles/{id}` | Dettaglio assegnazione         |
| `PUT`    | `/api/v1/provider-user-roles/{id}` | Aggiorna assegnazione          |
| `DELETE` | `/api/v1/provider-user-roles/{id}` | Elimina assegnazione           |

Campi per la creazione: `provider_id`, `user_id`, `role_id` (integer), tutti obbligatori.

Un utente **senza nessun ruolo** su un'applicazione non può entrarci: l'IDP lo ferma sulla pagina
«non autorizzato».

---

## 5. Procedura: collegare una nuova applicazione

I membri del team IDP possono fare gli stessi passi dal pannello di amministrazione; chi è esterno li
fa via API o Swagger, con il client Passport.

1. **Provider** — `POST /api/v1/providers`

    ```json
    {
        "name": "Nome applicazione",
        "url": "https://app-x.example.com",
        "domain": "app-x.example.com",
        "protocol": "https",
        "logoutUrl": "https://app-x.example.com/logout",
        "secret_key": "<32 caratteri alfanumerici>"
    }
    ```

    Risposta: `{ "provider": { "id": 5, ... } }`. L'`id` è il valore di `IDP_CLIENT_ID`
    dell'applicazione. La `secret_key` non torna nelle risposte delle API: va consegnata a chi
    sviluppa l'app, come `IDP_SIGNATURE_KEY`, attraverso un canale sicuro.

2. **Ruoli** — `POST /api/v1/roles` con `{"name": "admin", "provider_id": 5}`; uno per ruolo.

3. **Assegnazioni** — `POST /api/v1/provider-user-roles` con
   `{"provider_id": 5, "user_id": 8, "role_id": 3}`; una per coppia utente-ruolo.

4. **Verifica** — fare login sull'applicazione con un utente assegnato. Dopo l'accesso il browser ha
   il cookie `nt-idp-at-5-staging` (`nt-idp-at-5-production` in produzione), e l'app token che contiene
   ha i ruoli appena assegnati in `payload.roles`. Il resto del collegamento, lato applicazione, è in
   [external-integration.md](external-integration.md).
