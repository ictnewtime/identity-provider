# Piano d'azione — `ProviderUserRoleController`: messaggio ripetuto e costruttore vuoto

Sigla dichiarata dall'analisi: `TPU` — qui non si ridichiara.

Stato: **da approvare** · Data: 2026-08-12 · Analisi: [analysis.md](./analysis.md)

Fatti e alternative stanno nell'analisi: `F…` e `D…` puntano lì. V — `auto`: lo stabilisce un
comando · `man`: lo legge una persona.

## Onda 1 — accertare prima di toccare le stringhe

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TPU01 | da approvare | **`D2`** — verificare se qualche client, test E2E o componente Vue confronta le stringhe dei messaggi 404. Se sì, tradurle è una regressione e il piano cambia forma | nessuno (accertamento) | basso, ma è ciò che rende sicuro il resto | auto | `grep -rn 'not found' resources/js/ cypress/e2e/` — l'esito si riporta qui, qualunque sia |

## Onda 2 — le correzioni

`TPU03` non comincia prima che `TPU01` abbia risposto.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TPU02 | da approvare | **`F4`** — cancellare il costruttore vuoto. Non commentarlo: un costruttore senza parametri né corpo non fa niente che PHP non faccia da sé (§ 3, `D4`) | `ProviderUserRoleController.php:19` | basso | auto | la riga non esiste più; `docker exec idp_app_2 php artisan test` verde |
| TPU03 | da approvare | **`F1` + `F2`** — un helper `notFound()` sul controller base che compone la risposta 404 passando dalle traduzioni, più le chiavi in `lang/it.json` e `lang/en.json`. Sostituisce le **tre** occorrenze segnalate; l'estensione alle altre nove dipende da `D1` | `app/Http/Controllers/Controller.php`, `ProviderUserRoleController.php:221,292,333`, `lang/it.json`, `lang/en.json` | medio — cambia il corpo di risposte che il frontend legge | auto | test: le tre rotte rispondono 404 con il messaggio tradotto nella lingua della richiesta; `grep -c 'Provider user role not found' app/` restituisce 0 |
| TPU04 | da approvare | **`F5`** — `delete()` restituisce oggi `204` **con un corpo JSON**, che è una contraddizione: il 204 dichiara che non c'è contenuto. Passare a 204 senza corpo (raccomandazione `D3`), dopo aver guardato cosa si aspetta il frontend | `ProviderUserRoleController.php:337` | medio — è un cambiamento di contratto, in meglio | auto | test: la cancellazione risponde 204 e il corpo è vuoto; la tabella Vue continua a comportarsi come prima |

## Onda 3 — subordinati a una risposta

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TPU05 | da approvare | **`F6`, `D5`** — se la differenza fra `withTrashed()->find()` in lettura e la sua assenza in `update()`/`delete()` è **voluta**, scriverla in un commento e chiudere. Se non lo è, è un difetto: `update()` risponde «non trovato» su un record che `find()` restituisce con 200 | `ProviderUserRoleController.php:219,290,331` | medio se è un difetto, nullo se è una scelta | man | la risposta del developer, e il commento che ne resta |
| TPU06 | da approvare | **`D1`** — estendere l'helper di `TPU03` alle altre **nove** occorrenze dello stesso schema su tutto lo strato dei controller (`F2`). Se decidi di contenere lo scope, questo punto diventa `scartato` e il resto **si apre come task**, altrimenti resta un file diverso dagli altri quattro | `ProviderController.php`, `UserController.php`, `RoleController.php` e i rispettivi messaggi | medio | auto | `grep -rhno 'message" => "[A-Za-z ]*not found"' app/Http/Controllers/` non trova più occorrenze |

## Cosa questo piano non copre

- **Il costruttore vuoto in `app/Services/AccountService.php:27`** (`F7`): toccare un service fa
  scattare il controllo perf/leak completo, che per una riga di commento non si giustifica. Va nel
  task che toccherà quel service per motivi veri (`D6`).
- **La normalizzazione di `"Role id not found"`** (`F2`), che è lo stesso messaggio scritto male: la
  raccolgo in `TPU06` solo se `D1` dice di estendere.
- **`OA_DESC_MSG_NOT_FOUND`** (`F8`) resta dov'è: è la descrizione per la documentazione OpenAPI, non
  il messaggio restituito al client, e confonderli farebbe dipendere la risposta HTTP da una costante
  di documentazione.
