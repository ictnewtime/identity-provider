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

## Onda 1 — accertare, prima di toccare l'autenticazione

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TTR01 | **fatto** (2026-08-13) | **`D5`** — analizzata la copia dell'estensione in `tmp/idp-extension`. **L'estensione non subisce la scadenza, la gestisce**: intercetta `TokenExpiredException`, chiama `token/exchange`, valida via JWKS e prosegue (`F11`). Ma guardandola è emerso `F12`: l'exchange **ricrea** una sessione cancellata — difetto `VDF14`, ora parte di questo task | nessuno (accertamento) | basso | auto | il flusso dell'estensione è letto e riportato in `F11`; `F12` e `F13` verificati nel codice dell'IdP |
| TTR02 | **fatto** (2026-08-13) | Il test che **fotografa il difetto**: app token scaduto, master token valido per altre ore, sessione viva — e l'IdP disconnette lo stesso. Nasce **al contrario**: verde ora perché descrive il comportamento attuale, dovrà diventare **rosso** con `TTR04`. Più un secondo test che rende esplicita l'inutilità odierna del master token (il risultato è identico con e senza) e un terzo che prova che il 401 dipende dalla **scadenza**, non da altro | `tests/Feature/Auth/TokenRefreshTest.php` (nuovo) | basso | auto | 3 verdi. Il file dichiara in testa che va riscritto da `TTR04`: senza quella riga, chi lo trova fra sei mesi lo legge come il comportamento desiderato |

## Onda 2 — disaccoppiare la sessione dal token

`F6` è la trappola: allo scadere dell'app token **anche la sessione risulta scaduta**, quindi non c'è
più niente da cui ripartire per rinnovare in sicurezza. Va prima di tutto il resto.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TTR03 | **fatto** (2026-08-13) | **`D3`** — `expires_at` della sessione legato al **master** token (28800) invece che all'app token (1800). **Conseguenza scoperta implementando**: da quando le due durate divergono, «sessione valida» non implica più «token valido» — e il ramo che restituiva il token esistente avrebbe consegnato un **token scaduto**, facendo fallire il rinnovo dei client proprio quando serve. Ora se la sessione è viva e il token no, si rinnova **la chiave** e il rapporto di fiducia resta | `SessionService.php` | **alto** — tocca la revoca | auto | la sessione dura 28800 s; con token scaduto e sessione viva ne esce uno nuovo **senza** creare una seconda riga |
| TTR08 | **fatto** (2026-08-13) | **`VDF14`** — `getValidProviderToken()` prende `canCreate`: il **login** può creare una sessione, il **rinnovo** no. Rinnovare significa rinnovare qualcosa che esiste; senza questa distinzione il logout amministrativo durava una richiesta sola. `SessionController::get_token()` (l'exchange) passa `canCreate: false` | `SessionService.php`, `SessionController.php:157` | **alto** | auto | dopo `destroySession()`, il rinnovo col master token ancora valido **restituisce null** e non ricrea la riga. **Provato nei due versi** |
| TTR09 | **fatto** (2026-08-13) | **`D3`, seconda metà** — verificato che il logout da `/admin/sessions` cancelli **tutte** le sessioni dell'utente: `SessionController::delete()` chiama già `destroyAllUserSessions()`, che itera su tutti i provider. Mancava la prova che **regga**, cioè che nessuna si ricrei — ed è quella che `TTR08` rende possibile | nessuno (verifica + test) | medio | auto | un utente con sessioni su **due** provider viene sloggato da entrambi, e nessuno dei due rinnovi le ricrea |

## Onda 3 — il rinnovo

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TTR04 | da approvare | **`D1`(a)** — quando l'app token è scaduto, `Authenticated` verifica il **master token**; se valido, rinnova con `getValidProviderToken()` — che conserva il vincolo **IP + user agent** (`F8`) — mette il nuovo app token nel cookie e lascia passare. Il meccanismo esiste già (`F7`): qui lo si chiama, non lo si riscrive | `app/Auth/Idp/` (una classe nuova), `Authenticated.php` | **alto** — percorso di autenticazione di tutta l'area protetta | auto | gli 8 test di `AuthenticatedTest` e i 5 di `IdpCompositionTest` restano verdi **senza modifiche**; `TTR02` passa da 401 a 200 |
| TTR05 | da approvare | **`D2`** — master token scaduto o assente: resta il logout, ma con un **messaggio diverso** da quello del token scaduto. Oggi sono lo stesso, e chi legge il log non distingue «sessione finita davvero» da «rinnovo non riuscito» | `Authenticated.php`, `lang/it.json`, `lang/en.json` | basso | auto | due messaggi distinti, e un test per ciascuno dei due casi |
| TTR06 | da approvare | **`D4`** — il rinnovo emette il cookie **solo sulle rotte di navigazione**. Un cookie in risposta a un `fetch` non viene raccolto come ci si aspetta, e il rinnovo sembrerebbe funzionare a intermittenza — il tipo di difetto che costa giorni | `Authenticated.php` | medio | man | una chiamata API con token scaduto e master valido non lascia l'interfaccia in uno stato ambiguo |
| TTR07 | da approvare | Test di composizione sul rinnovo: token scaduto + master valido → passa e il cookie cambia; token scaduto + master scaduto → 401; token scaduto + **IP diverso** → 401, perché il vincolo di `F8` non si aggira; due richieste in parallelo → un solo token nuovo | `tests/Feature/Auth/IdpCompositionTest.php` | medio | auto | quattro casi verdi, e il terzo è quello che protegge dal rinnovo di un token rubato |

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
