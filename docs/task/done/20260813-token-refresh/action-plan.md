> **RETTIFICATO DA `TMT` IL 2026-08-28.** Questo task non si esegue piu': l'analisi di
> [master-token-sessions](../../todo/20260828-master-token-sessions/analysis.md) e' piu' avanzata e le
> decisioni che qui erano domande la' sono risposte. **Nessun punto e' andato perso**: i sette che `TMT`
> non copriva — il rinnovo dentro l'IdP, `VDF14`, gli indici, il `down()` della migrazione — sono stati
> portati la' come `TMT08`…`TMT14`, e ogni riga qui sotto dice dove e' finita. Il documento resta perche'
> **l'analisi vale ancora**: e' la storia di come si e' arrivati a quelle decisioni.

# Piano d'azione — l'app token scade e nessuno lo rinnova

Sigla dichiarata dall'analisi: `TTR` — qui non si ridichiara.

Stato: **da approvare** · Data: 2026-08-13 · Analisi: [analysis.md](./analysis.md)

Fatti e alternative stanno nell'analisi: `F…` e `D…` puntano lì. V — `auto`: lo stabilisce un
comando · `man`: lo legge una persona.

> **Aggiornato il 2026-08-13 con le risposte del § 4.** `D1` = **(a)**, rinnovo trasparente nel
> middleware. Ma il piano è cambiato più di così: analizzando l'estensione per rispondere a `D5` è
> emerso **`VDF14`** — il logout amministrativo non slogga, perché l'exchange **ricrea** la sessione
> cancellata. È lo stesso meccanismo guardato dall'altro lato, e si corregge qui: **se il rinnovo
> deve funzionare, deve anche poter essere fermato.**

> **Implementazione annullata il 2026-08-19, e i punti riaperti.** Il developer ha fatto
> `git revert` di `a0b58ba` — il commit che conteneva tutto il rinnovo — e ha riportato
> `SessionService` e `SessionController` a `0b2108a`, cioè a **prima** del disaccoppiamento
> token/sessione. Motivo: dopo tutte le correzioni il login su `devices/telefoni` continuava a non
> entrare, e si riparte da una base ferma invece che da una catena di rattoppi.
>
> **Cosa è tornato indietro davvero**, verificato sul disco e non dedotto: le tre classi
> `app/Auth/Idp/{IdpMasterTokenVerifier,IdpRenewal,IdpTokenRenewer}.php` non ci sono più;
> `Authenticated.php` e `VerifyMasterToken.php` sono alle versioni precedenti; `TokenExchangeTest` e
> `SessionIndexesTest` sono stati rimossi; `TokenRefreshTest` è tornato alla stesura di `TTR02`,
> quella che **fotografa il difetto**; le quattro chiavi in `lang/` (`auth.token-expired`,
> `auth.token-invalid`, `auth.renew-failed`, `auth.renew-refused`) **non ci sono più** — quindi
> `__("auth.token-expired")` torna a restituire la chiave stessa, che è il difetto che `TTR05` aveva
> trovato per caso; la migrazione degli indici è stata annullata sul database (`migrate:rollback`
> eseguito dal developer) e il file è stato rimosso, ma **la sua copia col `down()` corretto sta in
> `tmp/20260813-token-refresh/`**: senza quella, il `down()` da riscrivere è quello rotto.
>
> **Cosa NON si rifà da zero: le misure.** Sono la parte che è costata, e valgono ancora perché
> descrivono il sistema, non il codice cancellato:
>
> - il `down()` fallisce con l'errore **1553** perché l'`up()` fa **sparire** l'indice della chiave
>   esterna su `user_id` — riprodotto su MariaDB 12.3.2 (`TTR12`);
> - `Domain=localhost` **non** viene scartato dal browser: l'`idp-session` dell'IdP lo usa e torna
>   indietro;
> - `cookie()` prende i **minuti**, non i secondi;
> - l'IdP e l'applicazione vedono il client da due punti diversi — `172.22.0.1` contro `172.29.0.1` —
>   quindi confrontare l'IP fra i due non ha senso;
> - `IdpSessionValidator` cerca per `token` a ogni richiesta autenticata, e senza indice è una
>   scansione completa.
>
> Chi rifà i punti parta da qui: sono già state pagate una volta.

## Onda 1 — accertare, prima di toccare l'autenticazione

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TTR01 | **scartato** (2026-08-28) — rettificato da `TMT`, assorbito nell'analisi di `TMT` (§ 2, «cosa fanno davvero i client») | **`D5`** — analizzata la copia dell'estensione in `tmp/idp-extension`. **L'estensione non subisce la scadenza, la gestisce**: intercetta `TokenExpiredException`, chiama `token/exchange`, valida via JWKS e prosegue (`F11`). Ma guardandola è emerso `F12`: l'exchange **ricrea** una sessione cancellata — difetto `VDF14`, ora parte di questo task | nessuno (accertamento) | basso | auto | il flusso dell'estensione è letto e riportato in `F11`; `F12` e `F13` verificati nel codice dell'IdP |
| TTR02 | **scartato** (2026-08-28) — rettificato da `TMT`, il test esiste (`tests/Feature/Auth/TokenRefreshTest.php`) e lo riscrive `TMT07` | Il test che **fotografa il difetto**: app token scaduto, master token valido per altre ore, sessione viva — e l'IdP disconnette lo stesso. Nasce **al contrario**: verde ora perché descrive il comportamento attuale, dovrà diventare **rosso** con `TTR04`. Più un secondo test che rende esplicita l'inutilità odierna del master token (il risultato è identico con e senza) e un terzo che prova che il 401 dipende dalla **scadenza**, non da altro | `tests/Feature/Auth/TokenRefreshTest.php` (nuovo) | basso | auto | 3 verdi. Il file dichiara in testa che va riscritto da `TTR04`: senza quella riga, chi lo trova fra sei mesi lo legge come il comportamento desiderato |

## Onda 2 — disaccoppiare la sessione dal token

`F6` è la trappola: allo scadere dell'app token **anche la sessione risulta scaduta**, quindi non c'è
più niente da cui ripartire per rinnovare in sicurezza. Va prima di tutto il resto.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TTR03 | **scartato** (2026-08-28) — rettificato da `TMT`, → **`TMT02`** | **`D3`** — `expires_at` della sessione legato al **master** token (28800) invece che all'app token (1800). **Conseguenza scoperta implementando**: da quando le due durate divergono, «sessione valida» non implica più «token valido» — e il ramo che restituiva il token esistente avrebbe consegnato un **token scaduto**, facendo fallire il rinnovo dei client proprio quando serve. Ora se la sessione è viva e il token no, si rinnova **la chiave** e il rapporto di fiducia resta | `SessionService.php` | **alto** — tocca la revoca | auto | la sessione dura 28800 s; con token scaduto e sessione viva ne esce uno nuovo **senza** creare una seconda riga |
| TTR08 | **scartato** (2026-08-28) — rettificato da `TMT`, → **`TMT11`** (chiude `VDF14`) | **`VDF14`** — `getValidProviderToken()` prende `canCreate`: il **login** può creare una sessione, il **rinnovo** no. Rinnovare significa rinnovare qualcosa che esiste; senza questa distinzione il logout amministrativo durava una richiesta sola. `SessionController::get_token()` (l'exchange) passa `canCreate: false` | `SessionService.php`, `SessionController.php:157` | **alto** | auto | dopo `destroySession()`, il rinnovo col master token ancora valido **restituisce null** e non ricrea la riga. **Provato nei due versi** |
| TTR09 | **scartato** (2026-08-28) — rettificato da `TMT`, → **`TMT12`** | **`D3`, seconda metà** — verificato che il logout da `/admin/sessions` cancelli **tutte** le sessioni dell'utente: `SessionController::delete()` chiama già `destroyAllUserSessions()`, che itera su tutti i provider. Mancava la prova che **regga**, cioè che nessuna si ricrei — ed è quella che `TTR08` rende possibile | nessuno (verifica + test) | medio | auto | un utente con sessioni su **due** provider viene sloggato da entrambi, e nessuno dei due rinnovi le ricrea |

## Onda 3 — il rinnovo

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TTR04 | **scartato** (2026-08-28) — rettificato da `TMT`, → **`TMT08`** | **`D1`(a)** — quando l'app token è scaduto, `Authenticated` verifica il **master token** dal cookie; se regge, rinnova con `getValidProviderToken(canCreate: false)` — che conserva il vincolo **IP + user agent** (`F8`) — emette il cookie nuovo e lascia passare. Il meccanismo esiste già (`F7`): qui lo si chiama, non lo si riscrive. Tre classi piccole invece di un middleware più grosso: `IdpMasterTokenVerifier` (la verifica RS256, **presa anche da `VerifyMasterToken`**, che smette di duplicarla), `IdpTokenRenewer` (la decisione e il cookie), `IdpRenewal` (l'esito). **Fuori dai file dichiarati**: `VerifyMasterToken.php`, per non lasciare due decodifiche RS256 nello stesso repo — il comportamento è invariato e lo tengono fermo i 6 test di `TTR07` | `app/Auth/Idp/IdpMasterTokenVerifier.php`, `IdpTokenRenewer.php`, `IdpRenewal.php` (nuovi), `Authenticated.php`, `VerifyMasterToken.php` | **alto** — percorso di autenticazione di tutta l'area protetta | auto | gli 8 test di `AuthenticatedTest` e i 5 di `IdpCompositionTest` restano verdi **senza modifiche**; `TokenRefreshTest` passa da 401 a 200. **Provato nei due versi**: sostituendo il rinnovo con `IdpRenewal::notAttempted()`, 5 test su 10 diventano rossi |
| TTR05 | **scartato** (2026-08-28) — rettificato da `TMT`, → **`TMT09`** | **`D2`** — tre esiti, tre messaggi: `auth.renew-failed` (master assente o scaduto: la sessione è finita davvero), `auth.renew-refused` (master valido, è la **sessione** a dire di no — revocata o altro dispositivo), `auth.token-expired` (rinnovo non tentato). **Scoperto scrivendolo**: `auth.token-expired` e `auth.token-invalid` **non esistevano in nessuna lingua** — `__()` restituiva la chiave, e all'utente arrivava la stringa `auth.token-expired` sul form di login. Aggiunte tutt'e quattro in `it` e `en` | `Authenticated.php`, `IdpRenewal.php`, `lang/it.json`, `lang/en.json` | basso | auto | un test per ciascuno dei due casi, più un terzo che verifica che i messaggi siano **diversi fra loro** e che **abbiano** una traduzione — quest'ultimo fallisce sulla situazione di prima |
| TTR06 | **scartato** (2026-08-28) — rettificato da `TMT`, → **`TMT10`** | **`D4`** — il rinnovo non si tenta nemmeno fuori dalla navigazione. Criterio: `expectsJson()` senza `X-Inertia` è una chiamata API — lo stesso che `forceLogoutAndRedirect()` usa già, così i due non possono divergere. Chi chiama in API non resta scoperto: ha il 401 e poi `token/exchange`, che è la strada che l'estensione già percorre | `IdpTokenRenewer.php` | medio | auto (era `man`) | una chiamata API con token scaduto e master valido risponde 401 e **non** tocca il token in sessione. **Provato nei due versi**: rendendo la navigazione sempre vera, quel test diventa rosso |
| TTR04b | da approvare | **I test, e devono essere rossi.** Due navigazioni in parallelo con lo stesso app token scaduto: oggi rinnovano **tutt'e due**, e ognuna emette un token diverso. L'ultima scrittura vince sulla riga di sessione, ma il browser tiene l'ultimo cookie **ricevuto** — e se i due ordini non coincidono, la richiesta dopo non trova la sua sessione (`isAlive()` fallisce) e l'utente esce: **lo stesso difetto `VDF13`, sotto concorrenza**. È l'ultimo caso rimasto di `TTR07`. Il punto si chiude quando i test **falliscono**, e falliscono per il motivo giusto | `tests/Feature/Auth/TokenRefreshTest.php` | basso | auto | rosso: due rinnovi concorrenti producono due token, e il cookie della prima risposta non è quello in sessione. Il file dichiara in testa che è rosso di proposito e chi lo chiude è `TTR04c` |
| TTR04c | da approvare | **Il codice che li fa passare**: il rinnovo diventa **atomico** per coppia utente+provider. Chi arriva secondo non conia un secondo token: rilegge la sessione e restituisce quello appena scritto, se nel frattempo è diventato valido. Da valutare in `TTR04c` quale dei due strumenti — `lockForUpdate()` dentro una transazione, oppure `Cache::lock()` — perché il primo tiene solo se il motore è transazionale e il secondo dipende dal driver di cache configurato | `IdpTokenRenewer.php`, forse `SessionService.php` | **alto** — è il percorso di autenticazione, e un lock messo male lo blocca | auto | i test di `TTR04b` passano **senza essere modificati**, ed è la sola prova che vale: due richieste in parallelo → **un solo** token nuovo. La suite resta verde |
| TTR10 | **scartato** (2026-08-28) — rettificato da `TMT`, → **`TMT13`** (chiude `VDF15`) | **`VDF15`** — indice su `token` e indice composto `(user_id, provider_id)`. **Scoperto misurando, e diverso da come l'avevo scritto**: la ricerca per utente+provider non scandisce niente, perché su MariaDB le chiavi esterne portano già un indice che nessuno ha dichiarato; quella per `token` invece sì — `EXPLAIN` dà `type=ALL`. Su `token`, che è un `text`, la lunghezza dell'indice va decisa: MariaDB 12.3.2 senza lunghezza accetta e costruisce il prefisso **massimo** (768 caratteri), MySQL alla stessa istruzione **si ferma** con l'errore 1170. Da qui il prefisso esplicito di 191, che basta a distinguere due JWT e tiene l'indice un quarto | `database/migrations/2026_08_14_090000_add_indexes_to_sessions_table.php` (nuova), `tests/Feature/SessionIndexesTest.php` (nuovo) | medio | auto | il test **chiede al motore** con `EXPLAIN` se per quelle due query l'indice lo usa, non si limita a controllare che esista. Verde su sqlite; le tre forme SQL provate su MariaDB 12.3.2 in un container usa-e-getta, poi rimosso. **Provato nei due versi**: senza la migrazione i tre test diventano rossi. **Non ancora applicata a nessun database reale**: le migrazioni le esegue il developer |
| TTR12 | **scartato** (2026-08-28) — rettificato da `TMT`, → **`TMT14`** (chiude `VDF19`) | **Il `down()` di `TTR10` non funzionava sul database vero** (`VDF19`, errore 1553). **Causa riprodotta**, non dedotta: aggiungendo il composto `(user_id, provider_id)`, MariaDB **cancella da sé** l'indice `sessions_user_id_foreign` che si era creato con la chiave esterna, perché è diventato ridondante. Da quel momento il composto è l'**unico** con `user_id` a sinistra, e InnoDB rifiuta di togliere l'ultimo indice che regge un vincolo. Il `down()` ora, su MySQL/MariaDB, toglie il vincolo, toglie l'indice e **rimette** il vincolo — che si riporta dietro il proprio indice. Su sqlite `dropForeign()` non è supportato e il caso non esiste: due strade | `database/migrations/2026_08_14_090000_add_indexes_to_sessions_table.php` | medio — tocca un vincolo | man | **replay della storia vera su MariaDB 12.3.2 in un container usa-e-getta**, poi rimosso: stato di partenza (3 indici) → `up()` → l'indice della chiave esterna **spa­risce** → il `down()` vecchio fallisce con 1553 lasciando lo stesso stato a metà di `idp_develop` → il `down()` nuovo riporta **esattamente** ai 3 indici e 2 vincoli iniziali. Il nuovo `down()` **ripara** anche lo stato a metà: le guardie `hasIndex` saltano ciò che è già stato tolto. **Il `migrate:rollback` sul database lo esegue il developer** |
| TTR07 | **scartato** (2026-08-28) — rettificato da `TMT`, assorbito: le verifiche stanno nei punti di `TMT` che le richiedono | **Composizione sull'exchange, attraversato per intero**: sei test che passano dalla rotta vera — `POST api/v1/token/exchange` — quindi da `VerifyMasterToken`, dalla validazione e dal controller. `SessionRevocationTest` provava `SessionService` **da solo**; qui si prova il passaggio fra i pezzi, che è dove i test per classe non guardano. Ed è il flusso che i client usano davvero. **Non copre il rinnovo dentro l'IdP**: quei casi appartengono a `TTR04` e vanno con lui — scriverli ora vorrebbe dire lasciare test rossi che descrivono codice che non esiste. **Aggiornamento del 2026-08-14**: tre dei quattro casi li ha portati `TTR04` in `TokenRefreshTest`; il quarto — due richieste in parallelo — non è coperto da nessuno, e ha ora due punti suoi, `TTR04b` e `TTR04c` | `tests/Feature/Auth/TokenExchangeTest.php` (nuovo) | medio | auto | 6 verdi, **provati nei due versi**: togliendo `canCreate: false` dall'exchange, i due test sulla revoca e sul dispositivo diverso diventano rossi |

## Perf/leak — esito, voce per voce (policy dell'organizzazione)

Su `IdpTokenRenewer`, che è codice nuovo di servizio, e su `SessionService` per la strada nuova che lo attraversa.

| Voce | Esito |
|---|---|
| **Query N+1** | **ok**. Il rinnovo fa tre query a colpo, tutte per chiave: `User::find`, il `first()` sulla sessione, il `first()` sul provider dentro `cookieCretion()`. Nessun ciclo, nessuna relazione serializzata, nessuna query dentro una Resource — il rinnovo non serializza niente, restituisce un token |
| **Data leakage** | **ok**. Il token nuovo ha lo **stesso payload** di quello del login: nessun campo in più. Nei log finiscono `user_id` e `provider_id`, **mai il token** né il master token — controllato riga per riga nelle tre classi nuove |
| **Scope/tenant** | **ok**. Il rinnovo è vincolato tre volte: `sub` del master token (non un id preso dalla richiesta), `hasAccessToProvider()`, e il confronto IP + user agent con la riga di sessione. `canCreate: false` impedisce che il rinnovo diventi una seconda porta d'accesso |
| **Memory/streaming** | **non applicabile**. Nessun payload grande, nessun file: si legge una chiave pubblica da disco e si firma un JWT |
| **Query non vincolate** | **difetto trovato — `VDF15`, corretto con `TTR10`**. Le query erano vincolate (`first()`, `exists()`), ma la ricerca per `token` non aveva indice e scandiva la tabella intera (`EXPLAIN` → `type=ALL`). La migrazione c'è e il test lo verifica col motore; **applicarla ai database reali spetta al developer** |

## Cosa questo piano non copre

- **L'applicazione `anagrafe`**: altro repository, e il developer la guarderà dopo. **Non aspetta
  questo task**: se usa l'estensione, rinnova già da sé (`F11`); se si slogga lo stesso, la causa è
  altrove — e `VDF14` è il primo posto dove guardare, perché una sessione revocata e ricreata cambia
  il token sotto i piedi al client.
- **La strada (b)**: non è un lavoro da fare, **è già fatta** — l'estensione la implementa per
  intero. Questo task non la tocca.
- **Allungare l'app token**: **escluso** (`D6`). I 30 minuti sono la finestra di revoca, non un
  valore ereditato: allungarli allargherebbe l'intervallo in cui una revoca non ha effetto — cioè
  peggiorerebbe `VDF14` invece di aiutare.
- **`token/exchange`**: funziona e non si tocca. Questo piano fa in modo che l'IdP usi la stessa
  logica, non che la sostituisca.
