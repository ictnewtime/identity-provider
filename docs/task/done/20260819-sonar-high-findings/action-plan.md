# Piano — literali nei file di configurazione e costruttori vuoti

Sigla dichiarata dall'analisi: `TSH` — qui non si ridichiara.

Stato: **chiuso** (2026-08-19) — 5 punti fatti, 3 scartati · Data: 2026-08-19 · Analisi: [analysis.md](./analysis.md), che questo piano
**cita e non ripete**. `F…` e `D…` puntano lì. V — `auto`: lo stabilisce un comando · `man`: lo legge
una persona.

**La verifica dei due punti sulla configurazione va preparata prima**: i valori risolti si leggono con
`php artisan tinker --execute` (o `config:show`) e si confrontano dopo. Cambiare per sbaglio un default
di configurazione non rompe nessun test — si vede il giorno che qualcuno gira senza `.env`.

## Onda 1 — i costruttori, dove la decisione è già presa

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TSH01 | **fatto** (2026-08-19) | I due costruttori vuoti **cancellati**, non commentati (`F4`, `F5`): stessa decisione di `TPU02` su un rilievo identico. `failed()` non è stato toccato in nessuno dei due file — è vuoto ma fa parte del contratto di `ShouldQueue`, e il commento che ha già è la forma giusta per quel caso (`F6`) | `app/Listeners/LogLoginListener.php`, `app/Listeners/RegistrationListener.php` | basso | auto | `grep -c __construct` sui due file → **0**, `failed()` ancora presente col suo commento; e la prova che conta per una classe messa in coda: **il container li risolve ancora** — `app(LogLoginListener::class)` e `app(RegistrationListener::class)` costruiscono, con `handle` e `failed` al loro posto. Chiude 2 rilievi |

## Onda 2 — la configurazione, se `D1` dice di toccarla

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TSH02 | **scartato** (2026-08-19) | `'127.0.0.1'` × 5 in `config/database.php`. **`D1`: il file non si tocca**, e il developer scarta il rilievo a mano dal server di SonarQube. La ragione pesa il file invece del rilievo: è dello scheletro di Laravel, e due righe nostre lì dentro sono due righe da riconciliare a ogni aggiornamento del framework. **Cosa resta scoperto**: il «won't fix» vive nel database di SonarQube, non nel repository — al primo progetto nuovo, o a un reset del server, il rilievo torna e niente nel codice spiega perché era stato accettato | — | — | — | — |
| TSH03 | **scartato** (2026-08-19) | `storage_path('logs/laravel.log')` × 3 in `config/logging.php`. **Scartato con `TSH02`**, stessa ragione e stessa conseguenza |  — | — | — | — |

## Onda 3 — il test, se sopravvive

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TSH04 | **scartato** (2026-08-19) | `"10.0.0.1"` × 3 in `SessionRevocationTest.php`. **`D2`: il file si cancella**, quindi il rilievo se ne va col file e una costante lì dentro sarebbe lavoro buttato | — | — | — | — |
| TSH05 | **fatto** (2026-08-19) — **dal developer** | `tests/Feature/Auth/SessionRevocationTest.php` cancellato (`D2`). Con lui vanno via 5 test rossi — 3 con errore fatale `Unknown named parameter $canCreate` — e la suite torna **tutta verde**: da 5 rossi / 78 verdi a **75 verdi, zero rossi** | `tests/Feature/Auth/SessionRevocationTest.php` (cancellato) | medio | auto | suite tutta verde, verificato. **Tre test che passavano sono spariti con il file**, e uno dei tre non ha nessun punto che lo riporti: vedi `TSH06` |
| TSH06 | **fatto** (2026-08-19) | Il controllo perso con `TSH05`, riscritto **fuori** da quel file e **allargato**: legge tutti i `.php` di `app/`, `routes/` e `database/`, estrae le chiavi letterali passate a `__()` — **63** — e verifica che ognuna abbia una traduzione **in tutte e due le lingue**, perché una chiave tradotta in italiano e non in inglese è lo stesso difetto per chi usa l'inglese. Il quello vecchio guardava un solo controller e una sola lingua. **Trovato subito 5 chiavi senza traduzione** (`VDF20`), quindi il test nascerebbe rosso: hanno una lista `DEBITO` con la data e il perché, e un **terzo test impedisce che quella lista invecchi** — appena una di quelle chiavi viene tradotta diventa rosso e chiede di togliere la riga | `tests/Feature/TranslationKeysTest.php` (nuovo) | basso | auto | **provato nei due versi, entrambi**: mettendo `__("auth.chiave.che.non.esiste")` in `Authenticated.php` il test rosso **nomina file e riga** (`app/Http/Middleware/Authenticated.php:55`); e traducendo una chiave del debito in **entrambe** le lingue diventa rosso il terzo test. La prima volta quella seconda prova l'avevo fatta male — una lingua sola — e passava: il test aveva ragione, la prova no |
| TSH07 | **fatto** (2026-08-19) | **Tre chiavi tradotte, due cancellate.** Tradotte in `it` e `en`: `auth.token-expired` e `auth.token-invalid` col testo recuperato dal commit `a0b58ba` (le aveva scritte `TTR05`, il revert le ha portate via) e `parameter.error.creating` nella forma delle sue quattro sorelle. **Cancellate** le due `admin.providers.errors.*`: non erano da tradurre — erano nominate in due voci morte di `ProviderRequest::messages()`, che il validatore non rende mai, quindi tradurle voleva dire scrivere testo che nessuno avrebbe letto. Via anche `"domain.unique"`, perché il developer ha risposto che **due provider possono legittimamente avere lo stesso dominio**; rimaste vuote, la mappa dei messaggi e il metodo che la conteneva sono stati rimossi. **Correzione banale annunciata**: con essi i due residui della regola `unique` che non c'è più — la variabile `$providerId` assegnata e mai usata, e l'import `Rule` mai usato | `lang/it.json`, `lang/en.json`, `app/Http/Requests/ProviderRequest.php`, `tests/Feature/TranslationKeysTest.php` | basso | auto | i tre messaggi escono tradotti in entrambe le lingue, verificato a runtime; `grep -rn admin.providers.errors app/ lang/` → **nessuna occorrenza**; `DEBITO` è **vuota**; i messaggi di validazione sono quelli di prima (erano già i predefiniti, quindi nessuna regressione); suite **78 verdi** |
| TSH08 | **fatto** (2026-08-19) | `messages()` ricreata con le chiavi **`campo.regola`** che il validatore usa davvero — `domain.required` e `logoutUrl.url` — collegate ai testi che erano **già** in `lang` in due lingue. La forma sbagliata era la mappa, non il testo: le chiavi di traduzione stavano al posto dei nomi delle regole. **Fuori dai file dichiarati**: un test nuovo, perché la verifica di questo punto è un comportamento e senza test resterebbe un'osservazione fatta una volta | `app/Http/Requests/ProviderRequest.php`, `tests/Feature/ProviderRequestMessagesTest.php` (nuovo) | medio | auto | con `domain` vuoto e locale `it` il messaggio è «Il dominio del provider non puo' essere vuoto» e **non** `The domain field is required.`; in `en` è «The provider domain cannot be empty». Quattro test, di cui uno guarda la **forma**: ogni chiave di `messages()` deve nominare un campo che le `rules()` validano — è la guardia contro il difetto originale, non contro il suo sintomo. **Provato nei due versi**: rimettendo la mappa sbagliata, 3 dei 4 diventano rossi. Suite **82 verdi** |

## Cosa questo piano non copre

- **Il terzo costruttore vuoto, `app/Services/AccountService.php:27`**: esiste, non è nell'elenco dei
  rilievi portati, e `TPU` lo aveva già lasciato fuori **con una ragione** — toccare un service fa
  scattare la checklist perf/leak completa, che per una riga non si giustifica. La decisione di allora
  vale ancora: va nel task che toccherà quel service per motivi veri.
- **La causa dei 5 test rossi di `SessionRevocationTest`**: viene dal revert del 2026-08-19 e si chiude
  rifacendo `TTR03` e `TTR08`, non qui. Questo piano **cancella il file** (`TSH05`); il comportamento
  che descriveva resta da rifare, e sta nel piano di quel task.
- **I due rilievi sui file di `config/`**: restano aperti nel codice e chiusi a mano sul server
  (`TSH02`, `TSH03`). Se un giorno quei default si toccassero per un motivo vero, la forma da usare è
  scritta nel § 3 dell'analisi — due variabili locali, non costanti.
- **Il resto del quality gate**: come per
  [route-literals](../../done/20260819-route-literals/action-plan.md), un piano risponde dei propri
  punti. Chiusi i punti, il task va in `done/`.
