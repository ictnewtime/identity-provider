# La sessione legata al master token, e l'exchange v2

**Identificatori**: `TMT` = task master-token-sessions

Stato: da approvare · Data: 2026-08-28

Proposta del developer, e questa analisi la **verifica**: cosa e' esatto, cosa e' esatto a meta' e cosa
si rompe. **Ha rettificato e assorbito** [token-refresh](../../done/20260813-token-refresh/analysis.md) (`TTR`)
il 2026-08-28, per decisione del developer: quel task guardava lo stesso difetto dal lato dell'IdP —
«perche' l'IdP non usa il rinnovo che ha» — mentre questo guarda «perche' la riga di sessione muore col
token», e le domande che la' erano aperte qui hanno una risposta. I sette punti che questa analisi
**non** copriva sono stati portati nel piano come `TMT08`…`TMT14`: senza di loro **quattro difetti
aperti sarebbero rimasti senza un punto che li chiude**. L'analisi di `TTR` resta leggibile in `done/`,
ed e' la descrizione piu' dettagliata del flusso dei due token.

## 1. Obiettivo

**Una sessione dura quanto il master token, non quanto l'app token.** Oggi la riga in `sessions` scade
insieme all'app token — trenta minuti — e da quel momento chi la interroga non la trova piu' e
disconnette. Il master token, che vale otto ore, non ha alcuna riga che lo rappresenti.

**L'exchange fa una cosa sola e la fa in chiaro**: verificare il master token, verificare che l'utente
abbia accesso al provider, staccare un app token nuovo.

**Esiste una rotta `v2` che accetta i token negli header** — `x-master-token` e `x-app-token` — senza
rompere i client che oggi usano `Authorization: Bearer`.

**Perche' adesso**: e' il difetto che il developer incontra in prima persona ogni trenta minuti.

## 2. Situazione attuale — cosa ho verificato della proposta

### Esatto: la riga di sessione muore con l'app token

`SessionService.php:102-105` — dopo aver generato l'app token:

```php
$expirationTimeInSeconds = $tokenService->getAppTokenExpiredAt();
$expiresAt = now()->addSeconds($expirationTimeInSeconds);
$this->upsertSession($user->id, $provider_id, $ip_address, $user_agent, $token, null, $expiresAt);
```

`getAppTokenExpiredAt()` legge il parametro `app-token-exp-time-seconds` e ripiega su
`JWT_APP_TTL`, **1800 secondi** (`TokenProviderService.php:28`). Il master token ripiega su
`JWT_MASTER_TTL`, **28800**. Quindi a trenta minuti scadono insieme il token e la riga, ed e' esatto.

`SessionService.php:124-125` — `validateSession()` **cancella** la riga scaduta e risponde `404`, che il
chiamante traduce in «sessione terminata».

### Esatto a meta': «un record per ogni app-token»

La riga non e' per app token: e' per **coppia utente + provider**. `SessionService.php:80` e
`upsertSession():27` cercano `where user_id … where provider_id …`, e se la riga c'e' la **aggiornano**
tenendo lo stesso UUID. In pratica il developer ha ragione sull'effetto — c'e' una riga viva per ogni
app token in circolazione — ma la chiave e' un'altra, e questa differenza conta per il § 6.

### Da correggere: «`get_token` va riscritta verificando il master token e l'accesso al provider»

**Lo fa gia', ed e' l'unica parte che funziona.** `VerifyMasterToken` verifica la firma RS256 con la
chiave pubblica, controlla il claim `sub` e mette `jwt_user_id` fra gli attributi della richiesta
(`app/Http/Middleware/VerifyMasterToken.php:32-41`); `get_token()` lo legge (`SessionController.php:131`)
e chiama `getValidProviderToken()`, che come **prima cosa** fa `hasAccessToProvider($provider_id)`
(`SessionService.php:73`).

Quel che non va non e' la verifica: e' che subito dopo la stessa funzione **lega la riga alla vita
dell'app token**. Il difetto sta in `getValidProviderToken`, non in `get_token` — e questa e' la
differenza fra riscrivere venti righe e cambiarne tre.

### Esatto: oggi il master token viaggia come `Bearer`, non in un header suo

L'estensione fa `Http::withToken($master_token)` (`tmp/idp-extension/…/IdpAuthMiddleware.php:191`) e
manda nel corpo `provider_id`, `ip_address`, `user_agent`. `VerifyMasterToken.php:18` legge **solo**
`$request->bearerToken()`. Di `x-master-token` non c'e' traccia da nessuna parte, ne' qui ne'
nell'estensione.

### Il pezzo che nessuno ha nominato: `refresh_token` esiste e non si usa

La tabella ha una colonna `refresh_token` dal 2026-03-02
(`database/migrations/2026_03_02_142301_alter_table_session.php:28`). `upsertSession()` la scrive
(`SessionService.php:35`), ma **entrambi** i chiamanti passano `null`. E' un posto gia' pronto per
tenere il master token, senza migrazione.

### Il vincolo che la proposta incontra: `provider_id` non e' opzionale

`provider_id` e' `unsignedInteger` **non nullable** con chiave esterna verso `providers`
(stessa migrazione, righe 20-21). Una riga «del master token» un provider deve nominarlo lo stesso — e
sara' quello dell'IdP, perche' e' con quello che il master token viene generato
(`LoginController.php:133`).

## 3. Analisi

### Il difetto, detto in una riga

`expires_at` della riga descrive **la vita dell'app token**, ma la riga viene usata per rispondere a una
domanda diversa: «questo utente e' ancora dentro?». Sono due durate diverse — trenta minuti contro otto
ore — tenute in un campo solo.

### Tre modi di separarle, e non sono equivalenti

| Strada | Cosa cambia | Cosa costa |
|---|---|---|
| **(a) — scelta per la `v1`** — la riga resta per utente+provider, `expires_at` rappresenta il **master** token (otto ore), e il master token si **salva nella riga** | poche righe in `getValidProviderToken`, piu' il salvataggio al login | prima scartata, poi **scelta il 2026-08-28** quando l'implicazione 2 ha mostrato cosa costava la (b). Il master token va in **`refresh_token`** e non in `token`, perche' due ricerche cercano l'**app** token per trovare la sessione (§ 6) |
| **(b) — scelta per la `v2`** — una riga per **master token**, senza provider e senza app token | il modello della tabella, `validateSession`, la lista dell'amministratore, la revoca per singola app | scelta, rivista e infine **collocata**: come modello unico rompeva `validateSession()` e i client che oggi funzionano, quindi vale **solo per la rotta nuova**, dove nessuno la interroga per provider (§ 3, «due modelli, uno per rotta») |
| ~~**(c)**~~ Due tipi di riga: una «madre» per il master token e le «figlie» per gli app token | il modello, ma senza perdere niente | **valutata e scartata dal developer** «per il momento»: e' la strada che tiene tutto, e resta quella da riprendere se un domani la revoca per applicazione serve di nuovo |

### Il dettaglio che mancava: **due modelli, uno per rotta**

Precisato dal developer il 2026-08-28, e cambia la lettura di tutto il resto. Non si sceglie fra (a) e
(b): **convivono**, divise per rotta.

| | `v1/token/exchange` — quello che esiste | `v2/token/exchange` — quello nuovo |
|---|---|---|
| righe in `sessions` | **una per utente+provider**, come oggi | **una sola per utente** |
| `provider_id` | quello del provider | **nessuno** |
| `token` (app token) | l'app token, come oggi | **nessuno** |
| `refresh_token` | il master token | **il master token**, ed e' l'unica cosa che c'e' |
| `expires_at` | otto ore, perche' rappresenta il master token | otto ore |
| chi vede quali app | la riga per provider, come oggi | **le righe di `audits`** |

Il ragionamento che le tiene insieme e' in una frase del developer: **«basta il master-token per avere
l'app-token»**. Se e' vero — e lo e', perche' l'exchange non chiede altro — allora la riga per provider
nella v2 non serve a validare niente: serve solo a **raccontare**, e a raccontare basta l'audit.

**Perche' non si sceglie una sola delle due**: la v1 deve continuare a funzionare per i client che ci
sono, e `validateSession()` cerca per `provider_id`. Togliere quelle righe significherebbe romperli. La
v2 nasce senza quel vincolo perche' nasce ora.

**Il vincolo che una riga «senza provider» incontra**, ed e' l'unico ostacolo tecnico di questo disegno:
`provider_id` e' `unsignedInteger` **non nullable** con chiave esterna verso `providers`
(`2026_03_02_142301_alter_table_session.php:20-21`). Una riga senza provider **oggi non si puo'
scrivere**. Due modi: renderla nullable con una migrazione — e allora «senza provider» si legge
`provider_id IS NULL`, che e' anche il modo di distinguere una riga v2 da una v1 — oppure metterci il
provider dell'IdP, che pero' e' una bugia: quella riga non rappresenta una sessione sull'IdP.

**Come si distinguono le due specie di riga**, che e' la domanda che ogni query si porra': con la
migrazione, `provider_id IS NULL` **e'** il marcatore, e non serve una colonna in piu'.

**Cosa succede a chi ha tutte e due**: un utente puo' avere righe v1 (per le app vecchie) e la riga v2
(per quelle nuove) nello stesso momento. Va bene, ed e' lo stato di transizione — ma va detto perche'
il logout deve cancellarle **tutte**, e oggi lo fa gia': `performLogout()` cancella tutte le sessioni
dell'utente, non quelle di un provider.

### Il punto delicato, che vale per tutte e tre

`getValidProviderToken()` oggi restituisce il **token salvato** se la riga non e' scaduta
(`SessionService.php:82-92`). Funziona per un motivo fragile: riga e token muoiono insieme, quindi «riga
viva» implica «token valido». **Appena le due durate si separano, quell'implicazione salta**: con una
riga da otto ore, la funzione restituirebbe un app token scaduto per sette ore e mezza, e il client se
lo vedrebbe rifiutare a ogni richiesta.

Quindi qualunque strada si scelga, quella condizione deve guardare **la scadenza del token**, non quella
della riga. E' il cuore del lavoro, ed e' tre righe di codice — ma senza di esse la modifica peggiora le
cose invece di migliorarle.

### La rotta v2

Aggiungere `api/v2/token/exchange` che accetta `x-master-token` (con o senza `Bearer` davanti) e
adattare `VerifyMasterToken` perche' guardi in tutti e due i posti e' **retro-compatibile** e costa poco:
il middleware oggi ha un solo `bearerToken()` da cui partire.

Su `x-app-token` la domanda e' stata posta e **la risposta e' che non serve**: per staccare un app token
nuovo al server bastano il master token e il `provider_id`. Il developer ha deciso di **non inviarlo**,
quindi la v2 non lo accetta e non lo documenta — un header accettato e ignorato e' un contratto che
qualcuno un giorno credera' vero.

**Cosa risponde la v2**: tutti e due i token, perche' la rotazione ha senso solo se il chiamante riceve
anche quello nuovo. **Negli header** — `x-master-token` e `x-app-token`, corpo vuoto — deciso dal
developer il 2026-08-28. Avevo consigliato di metterli nel corpo (`{"master_token": …}`) con
l'argomento che `x-` e' una convenzione degli **header** e come nome di campo JSON confonde i due
piani; la scelta degli header ha pero' una simmetria che il corpo non ha — **la richiesta i token li
manda negli header**, e la risposta li restituisce dove sono arrivati. La v1 continua a rispondere
`{"token": …}` nel corpo e non si tocca: e' il motivo per cui esiste una v2.

### La rotazione del master token dopo un'ora

E' la parte con piu' conseguenze e meno codice. Il server puo' benissimo generare un master token nuovo
e metterlo nella risposta. Il problema non e' generarlo: e' che **qualcuno deve conservarlo**, e quel
qualcuno e' l'estensione, che sta in un altro repository. Se non lo conserva, continua a mandare il
vecchio — che resta valido fino alle otto ore — e la rotazione non fa niente. Se invece si decide che il
vecchio **non vale piu'**, chi non ha ricevuto il nuovo viene disconnesso. Le due cose vanno decise
insieme, e stanno nel § 4.

### `TMT` e gli altri due task: cosa ha assorbito e cosa **no**

Domanda del developer il 2026-08-28: «`TMT` e' la nuova versione di `session-bootstrap` e di
`token-refresh`, corretto?». **Per `TTR` si', per `TSB` no**, e la differenza si conta.

`TTR` (token-refresh) e' stato **assorbito per intero** e chiuso: tutti e undici i suoi punti hanno una
destinazione, sette sono diventati `TMT08`…`TMT14`.

`TSB` (session-bootstrap) **no**: ha dodici punti e `TMT` ne tocca **uno**.

| Punto di `TSB` | Di cosa parla | In `TMT`? |
|---|---|---|
| `TSB03` | il login apre la sessione del provider di destinazione | **si'**, e' quello che serve a `TMT27` |
| `TSB05` | tenere traccia delle sessioni **revocate**, per distinguerle da quelle mai esistite | in parte — era l'altra strada per `TMT27`, scartata in `D8` |
| `TSB01` `TSB02` | i due test che riproducono il ciclo di login e il vicolo cieco | no |
| `TSB04` `TSB12` | l'IP che l'IdP vede non e' quello del client (`VDF18`), e `rebindDevice` | no |
| `TSB06` | `validateSession()` cancella la sessione quando cambia lo user agent | no |
| `TSB07` `TSB09` `TSB10` `TSB11` | il cookie del master token: quando si accoda, il dominio, i **minuti** | no |
| `TSB08` | spuntare `VDF16` e `VDF17` nel registro | no |

Quindi **dire che `TMT` sostituisce `TSB` perderebbe undici punti**, fra cui le correzioni di `VDF16`,
`VDF17` e `VDF18`. `TSB` resta dov'e' e non si tocca (richiesta del developer).

**Cio' che `TMT` prende da `TSB` e' una cosa sola**, e la riscrive con parole sue perche' `TSB03` e'
scritto dentro un ragionamento che qui non c'e': **il login deve aprire la sessione**, e da due punti —
il login esplicito con `provider_id` e il SSO trasparente di chi e' gia' dentro e apre una seconda
applicazione. Senza **tutti e due**, chi apre un'altra applicazione ore dopo non avrebbe una riga e
resterebbe fuori. E' il punto `TMT28`, e **sovrappone `TSB03`**: si fa **una volta**, di qua o di la', e
l'altro si scarta.

### Cosa resta fuori, e perche' — con le risposte del developer

Spostato qui dal piano il 2026-08-28, su richiesta del developer: sono decisioni di **perimetro**, e il
perimetro e' materia d'analisi.

**Il rinnovo del master token: per ora si fa a mano, e c'e' un problema che il developer ha visto per
primo.** Con `TMT02` la disconnessione non arriva piu' a trenta minuti ma a **otto ore**: quando il
master token scade, l'utente rifa' il login. Il developer propone di studiare una rotta di refresh —
«ti do il token vecchio, se e' valido ricevo quello nuovo», verificato con le JWKS — e nota subito il
buco: **se il master token e' stato rubato, chi ce l'ha lo rinnova all'infinito**.

E' esatto, ed e' il problema noto di qualunque token che si rinnova da se': senza un modo di accorgersi
del furto, la rotazione **allunga** la vita di un token rubato invece di limitarla. La difesa standard
ha un nome — *rotazione con rilevamento del riuso* — e funziona cosi': il token vecchio, una volta
scambiato, **non vale piu'**; se qualcuno lo ripresenta, non e' un errore, e' la **prova** che due
soggetti hanno lo stesso token, e allora si butta giu' **tutta** la sessione, non solo quella richiesta.

Il pezzo che serve per farlo **c'e' gia' da oggi**: il master token e' salvato nella riga
(`refresh_token`, punto `TMT02`). Confrontare quello presentato con quello salvato e' una `where` in
piu'. Oggi **nessuno lo confronta** — ed e' l'implicazione 11 del § 6, che li' era un avvertimento e qui
diventa la strada. Non e' pero' lavoro di questo lotto: **e' il task
[token-reuse-detection](../20260828-token-reuse-detection/analysis.md) (`TRR`), aperto il 2026-08-28**, e
va deciso sapendo che rende il sistema **piu' fragile ai falsi positivi** — due dispositivi o una
richiesta ripetuta dalla rete possono somigliare a un furto. `TRR` viene **dopo** questo lotto, perche'
il confronto che gli serve usa la colonna che riempie `TMT02`.

**Una sessione per dispositivo: non si fa, e si accetta cosa comporta.** La chiave e' utente+provider,
quindi la sessione del dispositivo 2 **sovrascrive** quella del dispositivo 1. Risposta del developer:
va bene, e chi vuole il rinnovo sul dispositivo 1 **si riautentica**. Va detto perche' non e' evidente:
`TMT02` allunga la riga a otto ore, quindi la sovrascrittura dura di piu' e si nota di piu' di prima.
Cambiarlo sarebbe una migrazione — la chiave dovrebbe comprendere il dispositivo — e non e' di questo
lotto.

**Le estensioni si aggiornano tutte e due, e sono nel lotto.** `tmp/idp-extension` (PHP) e
`tmp/idp-extension-node` sono copie di lavoro di altri due repository: qui si scrive la modifica, il
developer la replica di la'. Sono i punti `TMT24` e `TMT25`, e sono **necessari** perche' la `v2` serva a
qualcosa: oggi la PHP legge `json("token")` (`IdpAuthMiddleware.php:204`) e la Node costruisce l'URL da
`IDP_URL_TOKEN_EXCHANGE`, che vale `api/v1/…` per difetto (`IdpService.js:7`) — nessuna delle due saprebbe
cosa fare di un master token nuovo nella risposta.

**Il rinnovo dal lato dell'IdP** resta cio' che era il task `TTR`, ora assorbito: sono i punti `TMT08`,
`TMT09` e `TMT10`. Chiudere `TMT02` senza quelli toglie la disconnessione a trenta minuti ma lascia
l'IdP senza rinnovo — il difetto si sposta a otto ore invece di sparire.

### Cosa dicono le prove del 2026-08-31, fatte dal developer sull'ambiente vero

Con `app-token-exp-time-seconds` a **10 secondi** e un'applicazione che usa ancora l'estensione
**non aggiornata** — quindi la rotta `v1` — il developer ha osservato quattro comportamenti. Due sono
giusti, due no, e il log li spiega tutti e quattro.

| Osservato | Giusto? | Perche' |
|---|---|---|
| login all'IdP → riga `IDP` | si' | la scrive `openProviderSession()` dal ramo senza `provider_id` |
| login all'app «device» → righe **`device` e `null`** | **no** | `openProviderSession()` scrive **sempre** tutte e due: quella per provider (v1) e quella del master token (v2). Il login pero' **non sa** quale rotta usera' l'applicazione |
| dopo 10 s sull'app: non slogga, rinnova | si' | l'estensione chiama l'exchange col master token |
| dopo 10 s **sull'IdP: slogga** | **no** | vedi sotto: e' `TMT10`, e il modo in cui rifiuta |

**Perche' l'IdP slogga, e non e' «il rinnovo non funziona»**: nel log il rinnovo **riesce sei volte** e
**non viene tentato tre volte**. Le tre volte sono richieste che `isApiCall()` classifica come API —
`expectsJson()` senza `X-Inertia` — e sono le **chiamate XHR della sua stessa interfaccia**, quelle che
le tabelle fanno con `window.axios` verso `admin/v1/…`.

E il colpo non e' il 401: e' quello che succede subito dopo. La sequenza nel log:

```
09:37:26  App token scaduto → RINNOVO non tentato (chiamata API)
09:37:29  Nessun token trovato nel cookie [idp_token_1]     ← il cookie non c'e' piu'
09:37:31  Nessun token trovato nel cookie [idp_token_1]
09:37:38  [LOGIN] Master token generato                     ← ha rifatto il login
```

`forceLogoutAndRedirect()` non risponde soltanto 401: **accoda `Cookie::forget`** sul token
dell'applicazione. Quindi una XHR dell'interfaccia, rifiutata perche' «e' una chiamata API», **cancella
il cookie del browser** — e la navigazione successiva non ha piu' niente in mano. Il criterio di
`TMT10` e' giusto nell'intenzione (a una chiamata macchina-a-macchina non si da' un token nuovo di
nascosto) ma **troppo grosso**: mette nello stesso mucchio l'integrazione esterna e l'XHR della propria
interfaccia.

**La versione la deduce l'IdP, non la dichiara l'app** (decisione del developer, 2026-08-31): `api/v1/…`
→ v1, `api/v2/…` → v2, e l'IdP la scrive sul provider. Il pregio e' che le estensioni gia' installate
non devono fare **niente** oltre a cio' che e' gia' stato cambiato, e da li' in poi e' l'IdP a sapere
come comportarsi — alla creazione della sessione, al rinnovo, alla revoca.

**Il disegno regge, e sposta il problema di un passo**: la versione si conosce **dopo** la prima
chiamata, mentre la riga la scrive il **login**, che viene prima. Per un provider mai visto l'IdP non
sa ancora quale riga serve — e se sbaglia per difetto scrivendo solo quella della v1, un'applicazione
**v2** al suo primo exchange trova la riga del master token mancante e **viene rifiutata**, perche' per
`TMT23` una riga mancante e' una revoca.

La via d'uscita che avevo proposto — «finche' la versione e' ignota, il primo exchange puo' creare» — e'
stata **superata**: il § 7 mostra che il problema non esiste se la riga senza provider diventa la prova
che l'utente e' entrato. Restava una toppa a una domanda mal posta.

**Questa parte e' stata rettificata**: `TMT29` e `TMT31` sono stati scartati il 2026-08-31, perche' chiedevano al login un'informazione che al login non esiste. Il modello giusto e' nel **§ 7**, ed e' il punto `TMT32`. Resta valido tutto quello che sta qui sopra sullo sloggamento dell'IdP: e' `TMT30`.

## 4. Da decidere

**Tutte risposte dal developer il 2026-08-28.** Restano scritte perche' la domanda spiega la risposta —
e perche' due di esse hanno un prezzo che il § 6 misura.

### Vincoli

- ~~**`D1`**~~ — **tutte e due, divise per rotta**, precisato il 2026-08-28: la **(a)** per la `v1`
  (riga per utente+provider, `expires_at` a otto ore, master token nella riga) e la **(b)** per la `v2`
  (una riga sola per utente, senza provider e senza app token, col solo master token). Non e' un
  compromesso: e' che la v1 ha `validateSession()` che cerca per provider e la v2 no. La (c) resta
  scartata «per il momento», che non e' «mai».
  **Tre decisioni di contorno, prese con la stessa risposta**: il master token va in **`refresh_token`**
  (non in `token`, che due ricerche interrogano con l'**app** token); la revoca per master token, se
  arrivera', filtra **anche per `user_id`**; `refresh_token` resta **senza indice**, accettando che sia
  meno veloce.
  **E vale anche per il login all'IdP**: quando un utente entra nell'IdP si salvano **tutti e due** i
  token, esattamente come per un provider esterno — sennò l'IdP resterebbe l'unico posto senza master
  token nella riga, cioe' l'unico che non puo' rinnovare.
- ~~**`D2`**~~ — **la rotazione sovrascrive il master token vecchio, ma solo quando viene chiamata la
  rotta v2.** La v1 non aggiorna niente: la riga scade alle otto ore e viene cancellata. Quindi il
  vecchio token non viene invalidato *attivamente* — smette di avere una riga quando la riga cambia.

### Conflitti

- ~~**`D3`**~~ — **`x-app-token` non si manda e non si accetta.**
- ~~**`D4`**~~ — **vale per la `v2`, non per la `v1`**. Sulla v1 la riga per provider resta, quindi la
  revoca per singola applicazione e la colonna «Provider» **non si perdono**. Sulla v2 non c'e' riga per
  provider, e al suo posto **ogni app token staccato scrive una riga in `audits`**: evento `created`,
  entita' `AppToken` — che come modello non esiste, e va bene perche' `auditable_id` qui e' una
  **stringa** — con nei dettagli `provider_id`, il token e `created_at`. **Confermato dal developer il
  2026-08-28**, token compreso: la nota sul rischio resta al § 6, nona implicazione, e non blocca.

### Ignoto

- ~~**`D5`**~~ — **le estensioni si possono cambiare**, tutte e due: `tmp/idp-extension` (PHP) e
  `tmp/idp-extension-node`. Questo sblocca la rotazione: c'e' un capo che riceve il token nuovo.

### Aperte da questa risposta

- ~~**`D8`**~~ — **si sblocca facendo creare la sessione al login** (`TMT28`), risposta del developer il
  2026-08-28. L'alternativa era **tenere traccia delle revoche**: invece di cancellare la riga, marcarla
  — per esempio con una colonna `revoked_at` — cosi' che l'exchange possa distinguere «questa sessione
  e' stata revocata» da «questa sessione non c'e' mai stata» e creare solo nel secondo caso. E' piu'
  robusta, perche' non dipende dal fatto che ogni ingresso passi dal login, ma costa una **migrazione**
  e un concetto in piu' nel modello. **Scartata per ora**, e resta scritta qui: se un domani si scoprira'
  un ingresso che non passa dal login, e' la strada da riprendere.

- **`D6` — «se c'e' il master token deve tornare 200 e un token nuovo, sempre»: fino a dove?** Oggi
  `getValidProviderToken()` restituisce `null` per **due** ragioni diverse, e il chiamante le confonde in
  un 403 solo:
  1. `!$user->hasAccessToProvider($provider_id)` (`SessionService.php:73`) — l'utente e' disabilitato o
     non ha ruoli su quel provider;
  2. `generateAppToken()` non trova il provider (`TokenProviderService.php:60-62`).

  Sul secondo caso il developer ha ragione e il 403 e' fuorviante. **Sul primo no**: rispondere 200 con
  un token vorrebbe dire consegnare un app token a chi non ha accesso a quell'applicazione, e il master
  token dice «questa persona e' autenticata», non «questa persona puo' entrare qui». Vanno separati.
- **`D7` — il token in chiaro nei dettagli dell'audit?** La riga di `D4` porterebbe l'app token dentro
  `audits`, che l'interfaccia amministrativa mostra (`admin/v1/audits`). Un JWT e' una credenziale:
  chiunque legga quella tabella potrebbe usarlo finche' non scade. Vedi § 6.

## 5. Consigli

- **`D1`: la (a), e subito** — perche' e' l'unica che si puo' fare senza migrazione e senza toccare
  `validateSession`, e chiude il sintomo che si subisce ogni trenta minuti. La (c) resta la strada
  giusta se un domani servono davvero due livelli di sessione, e la (b) sconsiglio: perde informazione
  che oggi si usa (vedi `D4` e § 6).
- **`D2`: no, non invalidare** — almeno finche' l'estensione non sa ricevere il token nuovo. Un master
  token che si rinnova **senza** invalidare il precedente e' un miglioramento silenzioso e senza vittime;
  il contrario e' una disconnessione di massa al primo deploy.
- **`D3`: non chiederlo.** Un header che si accetta e si ignora e' un contratto che qualcuno un giorno
  credera' vero.
- **`D4`: tenerla.** E' l'unica leva che l'amministratore ha per un singolo servizio, ed e' anche cio'
  che rende leggibile la pagina delle sessioni.
- ~~**`D1`…`D5`**~~ — risposte; i consigli precedenti restano leggibili nella storia del documento, e
  dove il developer ha deciso diversamente il § 6 dice cosa costa.
- **`D6`: separare i due `null`.** «Sempre 200 se il master token e' valido» vale per il rinnovo, non per
  l'autorizzazione: chi non ha ruoli su quel provider deve continuare a ricevere **403**, altrimenti
  l'exchange diventa il modo per ottenere un token su un'applicazione a cui non si ha accesso. La forma:
  `getValidProviderToken()` distingue «non autorizzato» da «non generato», e `get_token()` risponde 403
  al primo e 500 al secondo — che oggi e' un 403 e non e' vero.
- **`D7`: non scrivere il token, scrivere la sua impronta.** Nei dettagli bastano `provider_id`,
  `created_at` e le ultime otto lettere del token (o un `sha256` troncato): servono a **riconoscere**
  quale token, che e' lo scopo, senza metterne una copia usabile in una tabella che l'amministratore
  legge e che nessuno cifra. Se invece si vuole poter **revocare** quel token, allora quel che serve non
  e' l'audit: e' la riga «figlia» della strada (c).

## 6. Implicazioni — cosa migliora e soprattutto cosa si rompe

### In positivo

- **Il sintomo sparisce**: niente piu' disconnessione a trenta minuti, ne' sull'IdP ne' sui client.
- **La sessione diventa una cosa sola e comprensibile**: «l'utente e' dentro per otto ore», invece di
  una riga che significa «l'ultimo app token e' ancora fresco».
- **`refresh_token` smette di essere una colonna morta**: e' il posto naturale per il master token, e non
  serve una migrazione per usarla.
- **La rotta v2 rende l'exchange chiamabile** anche da contesti in cui `Authorization` e' gia' occupato,
  che oggi e' il motivo per cui un client dovrebbe inventarsi qualcosa.

### In negativo — con la risposta del developer, e cosa ne resta

Le undici implicazioni sono state **lette e discusse una per una il 2026-08-28**. Sotto, per ognuna:
cosa diceva, cosa ha risposto il developer, e cosa **resta** — perche' tre reggono ancora, e una regge
solo a una condizione precisa.

| # | Cosa diceva | Risposta | Cosa resta |
|---|---|---|---|
| 1 | un app token scaduto restituito come valido | «si rigenera **sempre**, quindi non lo restituiamo mai» | **risolta, a una condizione**: il ramo `return $existingSession->token` (`SessionService.php:82-92`) va **tolto**. Finche' c'e', il rischio c'e' |
| 2 | `validateSession()` cerca per `provider_id`: la (b) la romperebbe | «hai ragione, non possiamo permettercelo» → la (b) vale **solo per la v2**, dove nessuno interroga per provider | **risolta dividendo per rotta**: la v1 tiene le sue righe e `validateSession()` non si tocca |
| 3 | la revoca per singola applicazione sparisce | vale **solo per i client che passano alla v2** | **circoscritta**: sulla v1 la revoca resta; sulla v2 si perde e la rimpiazza l'audit. Ed e' una perdita vera, perche' l'audit **racconta** e non **interviene** |
| 4 | `isAlive()` cerca per token: due schede si pestano | «il master token sta in un cookie di dominio, le schede lo condividono» | **risolta per le schede**. Resta vero per **due dispositivi**, che e' il punto 5 |
| 5 | due dispositivi, una riga | «si', e la v2 verifica il master token senza guardare il provider» | **accettata**: e' il comportamento di oggi, non un peggioramento |
| 6 | la rotazione senza un ricevitore non fa niente | risposto al 4 | **risolta**: le estensioni si possono cambiare (`D5`) |
| 7 | due rotte di exchange da mantenere | «accettabile» | **accettata** |
| 8 | niente di tutto questo e' coperto da test | «aggiungi un punto per i test unitari» | **accolta**: e' `TMT21` |
| 9 | il token in chiaro dentro `audits` e' una credenziale | «accettabile in quanto e' firmato» — **riconfermato** il 2026-08-28 con la specifica dell'audit | **decisione presa, rischio annotato**: si scrive il token. La ragione data e' pero' rovesciata, e resta scritta qui sotto perche' un domani qualcuno la rilegga |
| 10 | «sempre 200» non puo' valere per l'autorizzazione | «ho spiegato male: se non ha accesso, 401 o 403» | **risolta**: e' `TMT15`, e il 403 resta |
| 11 | la rotazione sovrascrive, due client si pestano | risposto al 4 | **risolta** dallo stesso cookie condiviso |

### Le due che restano aperte, e una parola sulla nona

**La strada e' cambiata, e va detto con il suo nome.** La risposta al punto 2 non e' una correzione di
dettaglio: descrive la **strada (a)** — riga per utente+provider, `expires_at` che rappresenta il master
token — con il master token **dentro la riga**. Non e' la (b). Il piano e' stato riscritto su questo, e
la (b) resta scartata nel § 3 con il motivo: rompeva `validateSession()`.

**In quale colonna, e non e' un dettaglio.** «Nella colonna `token` il master token, sempre uguale»
avrebbe rotto **due** ricerche che oggi cercano l'**app** token:

```
app/Auth/Idp/IdpSessionValidator.php:11        Session::where("token", $token)->exists()
app/Http/Middleware/RedirectIfAuthenticated.php:121  Session::where("token", $tokenString)->exists()
```

Entrambe ricevono l'app token dal cookie: con dentro il master, non troverebbero piu' niente e l'IdP
disconnetterebbe. **Decisione del developer**: il master token va in **`refresh_token`**, la colonna che
esiste dal 2026-03-02 e che non usa nessuno. `token` resta l'app token, e le due ricerche non si toccano.

**Due cose guardate e accettate come sono**, entrambe su `refresh_token`:

- *revoca per master token*: se un domani si cancellassero «tutte le righe con quel master token», la
  query va filtrata **anche per `user_id`** — difesa in profondita' contro una collisione teorica. Il
  developer conferma che si fa per `user_id`, quindi non c'e' niente da correggere;
- *indice*: la colonna non ne ha, perche' non e' mai stata usata, e la v2 la interroghera' a ogni
  chiamata. **Accettato senza indice per ora**, sapendo che e' meno veloce. Se un domani pesa, e' la
  stessa migrazione di `TMT13`.

**Sulla nona, la decisione e' presa e il rischio resta scritto.** «E' accettabile perche' e' firmato»
inverte il fatto: **la firma e' cio' che lo rende utilizzabile**. Un JWT firmato e non scaduto e' una
credenziale al portatore — chi legge quella riga di `audits` **e'** quell'utente su quell'applicazione,
fino alla scadenza dell'app token. Con la v2 quelle righe diventano una per exchange, quindi la tabella
accumula chiavi valide a ritmo costante, e la mostra l'interfaccia amministrativa senza cifrarla.

Il developer ha deciso di scriverlo lo stesso, e si fa. Questa nota **non blocca niente**: sta qui
perche' il giorno che qualcuno chiedera' «perche' c'e' un token dentro gli audit?» la risposta non sia
«non ci avevamo pensato». Se un domani si volesse togliere, l'impronta — ultime otto lettere o uno
`sha256` troncato — fa lo stesso lavoro di riconoscimento senza la chiave.

## 7. Il modello delle righe, rifatto da capo il 2026-08-31

Questa sezione **rettifica** l'analisi che sta piu' sopra (`TMT29`, `TMT31`): quella cercava di far
sapere al login una cosa che al login **non si puo' sapere** — quale rotta usera' l'applicazione — e
per averla proponeva una colonna sulla tabella dei provider. La strada giusta e' un'altra, ed e' del
developer: **non serve saperlo in anticipo**.

### Il caso che rende tutto chiaro: tre applicazioni, versioni miste

Un utente entra in tre applicazioni. Una ha l'estensione vecchia (`v1`), una la nuova (`v2`), della
terza non si sa. Quante righe servono in `sessions`?

- **una sola** per l'utente, quella **senza provider**, che dice «questa persona e' entrata» — e vale
  per tutte e tre, perche' il master token e' uno;
- **una per ogni applicazione che usa la `v1`**, perche' la v1 e' fatta cosi': la sua sessione e'
  per coppia utente+provider, e `validateSession()` la cerca cosi'.

Quindi il numero di righe non si decide al login: **si scopre strada facendo**, ed e' giusto che sia
cosi'.

### Le tre regole, e perche' tengono

1. **Al login** si scrive la riga **senza provider**. Si puo' sempre, perche' non dipende da quale
   applicazione l'utente aprira': dipende solo dal fatto che sia entrato.
2. **Se arriva una chiamata `v1`**: si guarda che ci sia la riga senza provider — e' la prova che
   l'utente e' entrato davvero — e **si crea** la riga per quel provider, come la v1 vuole.
3. **Se arriva una chiamata `v2`**: si guarda la stessa riga senza provider e **non si crea** niente
   altro, come la v2 vuole.

**Perche' non riapre `VDF14`**, che e' la domanda che conta: la revoca cancella **tutte** le righe
dell'utente (`destroyAllUserSessions()` filtra per `user_id` e basta, verificato), quindi porta via
anche quella senza provider. Dopo una revoca la regola 2 non trova la prova e **rifiuta**. La sessione
non si ricrea da sola, che era il difetto.

**E rende inutile la colonna della versione**: la rotta chiamata basta a se' stessa nel momento in cui
viene chiamata. Non c'e' niente da dedurre in anticipo, niente da salvare, e nessun primo accesso che
fallisce.

### Cosa cambia rispetto a com'e' scritto oggi il codice

Oggi la `v1` chiede la **riga per provider** (`canCreate: false` la pretende gia' esistente) e conta
sul fatto che l'abbia creata il login. Con la regola 2 chiede la **riga senza provider** e la riga per
provider se la crea. Da qui due conseguenze buone:

- il login **smette di scrivere la riga per provider** delle applicazioni esterne: quella nasce solo se
  e quando una `v1` la chiede — ed e' la riga «di troppo» che il developer ha visto in tabella;
- un'applicazione `v2` non ha piu' alcuna riga per provider, che e' quello che il suo modello dice.

### La riga dedicata dell'IdP: si', e non e' una scelta

Verificato: `Authenticated` valida ogni pagina con `IdpSessionValidator::isAlive($token)`, che fa
`Session::where("token", $token)->exists()` — cerca **l'app token**. Senza una riga sua, ogni
navigazione nell'IdP fallirebbe. Quindi la riga dell'IdP **serve**, e l'IdP e' un'applicazione come le
altre anche in questo: si autentica in modo diverso, ma la sua sessione si scrive e si legge come
quella di chiunque.

### Una precisazione su `RedirectIfAuthenticated`

Il developer scrive: «quando l'user naviga nell'app IDP viene eseguito `RedirectIfAuthenticated`».
**Non e' cosi'**, ed e' meglio saperlo perche' cambia dove guardare: quel middleware e' l'alias `guest`
(`bootstrap/app.php:49`) e sta **solo** sul gruppo delle rotte per chi non e' autenticato — `/`,
`/forgot-password`, `/reset-password` (`routes/web.php:30-37`). Navigando in `/admin/...` gira
`Authenticated`, non lui.

`RedirectIfAuthenticated` interviene quando un utente **gia' autenticato** apre la pagina di login: e'
li' che decide se mandarlo alla sua applicazione (SSO trasparente) o all'amministrazione. Ed e' per
questo che `TMT28` ha messo l'apertura della sessione anche li': e' l'unico ingresso di chi apre una
seconda applicazione senza rifare il login.

