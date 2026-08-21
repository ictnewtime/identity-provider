# IDP — token, cookie, collegamento di un'applicazione

Obiettivo: ottenere username e ruoli per governare l'accesso ai componenti dell'applicazione.

## I due token

Sono due JWT, in sequenza. Il primo dice **chi sei**, il secondo **cosa puoi fare, e dove**.

| Token            | Chi lo emette                                                                                | Algoritmo                                                                                                    | Contiene                                     |
| ---------------- | -------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ | -------------------------------------------- |
| **master-token** | primo login all'IDP                                                                          | RS256, `kid: idp-master-key` ([TokenProviderService.php:149](../app/Services/TokenProviderService.php#L149)) | l'utente: id, username, email, nome          |
| **app-token**    | `POST /api/v1/token/exchange`, col master-token ([api.php:96-98](../routes/api.php#L96-L98)) | HS256 ([TokenProviderService.php:101](../app/Services/TokenProviderService.php#L101))                        | id utente e **ruoli del provider chiamante** |

I ruoli nell'array sono sempre quelli dell'applicazione con cui sei loggato: su applicazione-x, i ruoli di applicazione-x.

### master-token

```
eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImtpZCI6ImlkcC1tYXN0ZXIta2V5In0.eyJpc3MiOiJodHRwczovL2lkcC1zdGFnaW5nLm5ld3RpbWVncm91cC5pdCIsImlhdCI6MTc4MDkxMjI2MywiZXhwIjoxNzgwOTQxMDYzLCJzdWIiOiI4IiwicGF5bG9hZCI6eyJ1c2VyIjp7ImlkIjo4LCJ1c2VybmFtZSI6ImZyYW5jZXNjby5jb3J0aW5pIiwiZW1haWwiOiJmcmFuY2VzY28uY29ydGluaUBuZXd0aW1lZ3JvdXAuaXQiLCJuYW1lIjoiRnJhbmNlc2NvIiwic3VybmFtZSI6IkNvcnRpbmkifX19.eJkqJ32s4bKvgXPDHrMnDvb4fiYeHFKszL7vg4OlTi_QU_3Xq4z4G7gQQ3etmD9wI6KOky34CNSotp1cYOl949eWtfoDJi3jXsLy8_8YEjLAu5eo7oZcquMeyyszphvujknsxtUigVVKF5OHtgYSftyttqD6lw20AQ6HRBe1fpolvcqe12o_Y7nsxmyB2rdrINVNhediGE1acX9JeupurOfS9CVEJyTKYWMMR98Ik9LjWm1Wtiw4lh3anCLWO6DKAtgleY978D6QsaDva1Y27nLz9fD_3wZBpUUOOSHH9i9VgcFVHY933TS_V-usGo1H_OxxrJlxV0Ohgr6THiv7Tw
```

Decodificato:

```json
{
    "iss": "https://idp-staging.newtimegroup.it",
    "iat": 1780912263,
    "exp": 1780941063,
    "sub": "8",
    "payload": {
        "user": {
            "id": 8,
            "username": "francesco.cortini",
            "email": "francesco.cortini@newtimegroup.it",
            "name": "Francesco",
            "surname": "Cortini"
        }
    }
}
```

### app-token

```
eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbG9jYWxob3N0OjgwMDAiLCJpYXQiOjE3ODA5MTIyNDUsImV4cCI6MTc4MDkxNDA0NSwibmJmIjoxNzgwOTEyMjQ1LCJqdGkiOiJiYzg5ZTFiMjA5ZDFhZDY1ZjUzOCIsInN1YiI6IjgiLCJwcnYiOjIsImF1ZCI6ImxvY2FsaG9zdCIsInBheWxvYWQiOnsidXNlciI6eyJpZCI6OH0sInJvbGVzIjpbeyJpZCI6MywibmFtZSI6ImFkbWluIn1dfX0._AaJGYIF7Y4Iv1Ty2jRTqEB4G_9TP6DjG6kD6TGHcNg
```

Decodificato:

```json
{
    "iss": "http://localhost:8000",
    "iat": 1780912245,
    "exp": 1780914045,
    "nbf": 1780912245,
    "jti": "bc89e1b209d1ad65f538",
    "sub": "8",
    "prv": 2,
    "aud": "localhost",
    "payload": {
        "user": {
            "id": 8
        },
        "roles": [
            {
                "id": 3,
                "name": "admin"
            }
        ]
    }
}
```

## Il cookie

L'app-token viaggia in un cookie per ogni applicazione. Il nome è `idp_token_<id>`, dove `<id>` è
l'identificativo del provider — oggi fisso a `"1"` in [config/idp.php:4](../config/idp.php#L4), non
letto dall'ambiente.

Il cookie **non è cifrato da Laravel**: è nell'elenco delle eccezioni, costruito a runtime da
`Provider::pluck("id")` ([EncryptCookies.php:23-27](../app/Http/Middleware/EncryptCookies.php#L23-L27)).
Deve restare leggibile dalle applicazioni.

Il cookie lo crea l'IDP. **Eccezione**: se applicazione-x è su `localhost`, lo crea l'idp-extension e
l'IDP passa il token firmato nella query string dell'URL.

## Creare un provider

Due strade: i membri interni dall'accesso admin, i collaboratori esterni via Swagger.

Ai collaboratori esterni servono le credenziali Passport e l'URL dello Swagger:
[https://idp-staging.newtimegroup.it/api/documentation](https://idp-staging.newtimegroup.it/api/documentation#/)

Il client Passport si crea con:

```
php artisan passport:client --password --name="nome utente"
```

Il `--name` **non** deve coincidere con uno `username` esistente nella tabella `users`.

## Procedura: collegare e provare un'applicazione

Dopo l'Autorizzazione nello Swagger (credenziali Passport), nell'ordine:

1. `POST /api/v1/providers` — il record **Provider** dell'applicazione.
2. `POST /api/v1/roles` — i **ruoli**, legati a quel provider.
3. `POST /api/v1/provider-user-roles` — la relazione **ProviderUserRole**: quali utenti hanno quali
   ruoli su quel provider.

Verifica: `GET /api/v1/provider-user-roles/has-relation`, e poi un `token/exchange` col master-token —
i ruoli appena assegnati devono comparire nel payload.
