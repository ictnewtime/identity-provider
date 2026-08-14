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
| TTR02 | da approvare | Un test che **fissa il difetto**: con app token scaduto **e master token valido**, l'area amministrativa oggi risponde 401. Deve diventare 200 con `TTR04`. Nasce rosso al contrario — verde ora, e sarà il diff a mostrare il cambiamento | `tests/Feature/Auth/` | basso | auto | il test descrive il comportamento attuale e viene riscritto da `TTR04`, non aggiustato |

## Onda 2 — disaccoppiare la sessione dal token

`F6` è la trappola: allo scadere dell'app token **anche la sessione risulta scaduta**, quindi non c'è
più niente da cui ripartire per rinnovare in sicurezza. Va prima di tutto il resto.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TTR03 | da approvare | **`D3`** — `expires_at` della sessione legato al **master** token. **La semantica precisata dal developer cambia il punto**: la sessione non è la vita di un token, è il **segnalatore di dove un utente ha accesso** — la coppia utente/provider. Da lì discende sia la durata sia il fatto che debba essere revocabile | `SessionService.php:102-105`; verifica su `validateSession()` | **alto** — tocca la revoca | auto | una sessione resta valida oltre i 30 minuti, e l'estensione non la perde più a metà |
| TTR08 | da approvare | **`VDF14`** — `getValidProviderToken()` tratta l'assenza di una sessione come «prima volta» e ne crea una nuova (`F12`): dopo un logout amministrativo il primo `token/exchange` la **ricrea**, e la revoca dura una richiesta. Distinguere i due casi — nessuna sessione mai avuta, contro sessione **revocata** — e nel secondo **rifiutare** | `SessionService.php:80-107`, ed eventualmente una colonna o una tabella per la revoca | **alto** — è l'unico strumento che un amministratore ha per cacciare qualcuno | auto | dopo il logout da `/admin/sessions`, un `token/exchange` col master token ancora valido **fallisce**; e il test lo prova nei due versi |
| TTR09 | da approvare | **`D3`, seconda metà** — il pulsante di logout in `/admin/sessions` deve sloggare l'utente **da tutte** le sessioni, non da quella del solo provider. `destroyAllUserSessions()` esiste già ed è chiamato da `SessionController:213`: va verificato che copra davvero tutti i provider e che, con `TTR08`, la revoca **regga** | `SessionController.php`, `SessionService.php` | medio | auto | un utente con sessioni su due provider viene sloggato da entrambi, e nessuna delle due si ricrea |

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
