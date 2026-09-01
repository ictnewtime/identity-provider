# `auth/me` fornita dalle estensioni — analisi

Sigla degli ID: **`TAM`**. Riguarda i due pacchetti client, non l'IdP:
`tmp/idp-extension` (PHP/Laravel) e `tmp/idp-extension-node` (Node/Express).

## 1. Obiettivo

Chi installa l'estensione deve **avere** la rotta `auth/me` senza scriverla. Oggi ogni applicazione
se la copia a mano; alla fine del lavoro il pacchetto la porta con se', come fa Passport con le sue
rotte, e l'applicazione non tocca nessun file di rotte per averla.

Serve adesso perche' le applicazioni integrate stanno crescendo — device/telefoni, l'app di prova
Node, e le prossime — e ogni copia e' un posto in piu' dove la stessa cosa puo' essere scritta in
modo diverso. Con i due token (app token e master token) la risposta di `auth/me` non e' piu' banale:
dice **chi sei** dopo che il middleware ha eventualmente rinnovato o scambiato i token, e se ogni
applicazione la scrive per conto suo, ognuna decide da sola cosa mettere dentro.

## 2. Situazione attuale

**La rotta non esiste nel pacchetto: esiste nella documentazione, come esempio da copiare.**
`tmp/idp-extension/docs/external-integration.md:276` e `:423` la mostrano due volte, e le due copie
**non restituiscono la stessa cosa**: la prima solo `user`, la seconda `user` e `roles`. E' gia' la
prova del problema — due esempi nello stesso documento divergono, figurarsi due applicazioni.

**Il pacchetto PHP e' gia' un pacchetto Laravel a tutti gli effetti, ma non carica rotte.**
`tmp/idp-extension/src/Providers/IdpClientServiceProvider.php` fa tre cose: `mergeConfigFrom`,
il `singleton` di `IdpService`, `publishes` della configurazione, e in `boot()` registra l'alias del
middleware con `aliasMiddleware("idp.auth", IdpAuthMiddleware::class)`. **Non c'e' nessun
`loadRoutesFrom`**, e nella cartella `src/` non c'e' nessun file di rotte (`find` sul pacchetto: solo
`IdpService.php`, `config/`, `Providers/`, `Exceptions/`, `Http/Middleware/`).

Il provider viene registrato da solo: `composer.json` dichiara
`extra.laravel.providers`, quindi l'individuazione automatica dei pacchetti lo carica senza che
l'applicazione scriva niente. **Cio' che manca e' solo il caricamento delle rotte**, non
l'aggancio: l'aggancio c'e' gia' e funziona.

**Il materiale della risposta c'e' gia'.** `IdpService` espone `getUser(Request): ?array`,
`getRoles(Request): array`, `hasRole`, `hasAnyRole` (righe 177-232). Nel pacchetto Node ci sono gli
stessi metodi (`lib/IdpService.js:162` e `:190`). Nessuno dei due va scritto da capo.

**Node non ha un'individuazione automatica.** `tmp/idp-extension-node/index.js` esporta
`IdpService`, `idpAuth`, `IdpServiceClient`, `createMiddleware`: e' Express, e in Express nessun
pacchetto puo' aggiungersi rotte da solo — le rotte esistono se l'applicazione le monta. Il piu'
vicino che si arriva e' **esportare un router gia' pronto** che l'applicazione monta con una riga
sola (`app.use(idp.routes())`). Va detto chiaro nel piano invece di promettere un automatismo che
in Express non esiste.

**Breaking change: c'e', e va guardato in faccia.** Se il pacchetto registra `auth/me` e
un'applicazione ha gia' la **sua** `/me` scritta a mano, le due si sovrappongono. In Laravel vince
la prima registrata: le rotte del pacchetto si caricano in `boot()`, cioe' **prima** di quelle
dell'applicazione, quindi la rotta del pacchetto vincerebbe e l'applicazione si troverebbe una
risposta diversa da quella che si aspettava, **senza nessun errore**. E' il rischio piu' serio del
lavoro, ed e' silenzioso.

## 3. Analisi

**Cosa serve nel pacchetto PHP.** Un file di rotte dentro il pacchetto, caricato in `boot()` con
`loadRoutesFrom`, con la rotta protetta dal middleware `idp.auth` che il provider gia' registra. Il
prefisso e il nome non si inventano: si leggono dalla configurazione (`idp-client.php`), con un
valore di ripiego, cosi' chi ha gia' una `/me` propria puo' spostare quella del pacchetto invece di
subirla — ed e' anche la risposta al breaking change di sopra.

**Cosa risponde.** Una forma sola, decisa qui e uguale nei due pacchetti: `user` e `roles`, cioe' la
seconda delle due copie che girano nella documentazione, che e' la piu' completa. **Non** vanno
messi dentro i token: la rotta dice chi sei, non ti ridà le credenziali. Chi ha bisogno del token lo
ha gia' nel cookie o negli header.

**Cosa serve nel pacchetto Node.** Un router Express esportato da `index.js`, con la stessa rotta e
la stessa forma di risposta, che l'applicazione monta con una riga. Non e' automatico come in
Laravel, e la documentazione deve dirlo senza girarci intorno.

**Cosa si cancella.** I due esempi di `/me` in `docs/external-integration.md` non restano: uno
sparisce, l'altro diventa «la rotta ce l'hai gia', ecco cosa risponde». Lasciarli sarebbe la terza
versione della stessa cosa.

**Alternative viste e scartate.** (a) Mettere `auth/me` **sull'IdP** invece che nelle estensioni:
scartata perche' l'IdP non sa cosa l'applicazione ha appena rinnovato, e ogni chiamata diventerebbe
un giro di rete in piu' per un dato che l'applicazione ha gia' in mano. (b) Un **trait** o una classe
base che l'applicazione usa per scriversi la rotta: e' sempre codice nell'applicazione, cioe' il
problema che si vuole togliere. (c) Copiare `Passport::routes()`, cioe' un metodo statico che
l'applicazione chiama nel suo provider: e' il modo **vecchio** di Passport, abbandonato proprio
perche' obbligava ogni applicazione a una riga di aggancio; il modo nuovo e' `loadRoutesFrom` nel
provider, e conviene partire da li'.

## 4. Da decidere

**Vincoli**

- **D1** — Il percorso: `auth/me` come da titolo, oppure `/api/auth/me`? In Laravel il file di rotte
  del pacchetto decide anche il gruppo (`api` o `web`) e quindi quali middleware girano.
- **D2** — La rotta deve poter essere **spenta** da chi non la vuole (una voce di configurazione),
  o si accetta che chi installa il pacchetto se la prenda comunque?

**Conflitti**

- **D3** — Se un'applicazione ha gia' una sua `/me` allo stesso percorso, cosa si vuole: che vinca
  quella del pacchetto (comportamento naturale), o che il pacchetto si faccia da parte?

**Ignoto**

- **D4** — La risposta deve contenere anche il `provider_id` dell'applicazione che chiede, oltre a
  `user` e `roles`? Serve a chi ha piu' integrazioni nello stesso frontend, ma e' un dato che
  l'applicazione conosce gia' per conto suo.

## 5. Consigli

- **D1** — `auth/me` nel gruppo `api`, senza sessione e senza CSRF: e' una chiamata che il frontend
  fa con il token, non un modulo da inviare.
- **D2** — Si', una voce `routes.enabled` nella configurazione, con valore di ripiego «accesa». Costa
  tre righe e toglie l'unico motivo serio per non installare il pacchetto.
- **D3** — Vince quella del pacchetto, ma **il percorso e' configurabile**: chi ha gia' la sua la
  sposta invece di litigarci. Cosi' il breaking change ha una via d'uscita che non e' «disinstalla».
- **D4** — No. `user` e `roles`, niente altro. Il `provider_id` e' configurazione dell'applicazione,
  non identita' dell'utente, e una rotta che dice due cose diverse invecchia male.
