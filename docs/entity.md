# Provider

l'entità Provider serve a gestire i servizi/applicazioni esterne che si integrano con l'Identity Provider. Ecco il suo scopo:

Cos'è un Provider
Un Provider rappresenta un'applicazione o servizio esterno (ad esempio un portale, un'app) che:

Ha un dominio (es. portale.newtimegroup.it)
Richiede credenziali di autenticazione (username e password)
Ha un URL di logout per la sincronizzazione della disconnessione
Come funziona
Dati salvati (model Provider.php):

domain - il dominio dell'applicazione esterna
username - credenziale criptata per accedere al provider
password - credenziale criptata per accedere al provider
logoutUrl - URL per disconnettere l'utente dal provider esterno
Operazioni disponibili
Tramite ProviderController.php:

POST /v1/providers - Solo gli admin possono creare nuovi provider
GET /v1/providers - Solo i client autenticati possono visualizzare l'elenco
Use case tipico
Quando un utente si disconnette dall'Identity Provider, il sistema può:

Recuperare i provider associati
Usare le credenziali (username/password) per autenticarsi
Chiamare l'URL di logout per sincronizzare la disconnessione su tutte le applicazioni esterne
Questo garantisce un logout centralizzato - quando l'utente esce dal sistema principale, viene automaticamente disconnesso anche da tutti i servizi esterni integrati.

---

# Client

L'entità Client serve a estendere i client OAuth con un sistema di ruoli e autorizzazioni. Ecco come funziona:

Relazione tra OauthClient e Client
OauthClient - Modello OAuth (credenziali, secret, callback URL)
Client - Tabella supplementare che aggiunge autorizzazioni e ruoli
Cosa contiene Client
Dalla Client.php, il Client ha:
oauth_client_id - riferimento al client OAuth
scopes - ambiti di accesso
roles - array JSON dei ruoli (es. ["admin"], ["manager"], ["admin", "manager"])
Come funziona il controllo dei ruoli
Middleware CheckClientRole - CheckClientRole.php

Valida la richiesta OAuth
Estrae l'ID del client
Recupera i ruoli dal Client
Verifica che il client abbia il ruolo richiesto

Esempio di uso nelle rotte - idp.php:

```php
Route::middleware(['client', 'checkclientrole:admin'])->group(function () {
    Route::post('providers', [ProviderController::class, 'create']);
    Route::post('roles', [RoleController::class, 'create']);
});
```

Solo i client con ruolo admin possono creare provider e ruoli.

Gestione attraverso l'admin
OauthClientsController.php - Permette di aggiornare i ruoli di un client
OauthClientRow.vue - UI per assegnare/modificare i ruoli ai client
In sintesi: Client è il bridge tra OAuth e il sistema di autorizzazione basato su ruoli.
