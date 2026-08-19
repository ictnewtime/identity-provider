# Piano d'azione — `ProviderUserRoleController`: messaggio ripetuto e costruttore vuoto

Sigla dichiarata dall'analisi: `TPU` — qui non si ridichiara.

Stato: **da approvare** · Data: 2026-08-12 · Analisi: [analysis.md](./analysis.md)

Fatti e alternative stanno nell'analisi: `F…` e `D…` puntano lì. V — `auto`: lo stabilisce un
comando · `man`: lo legge una persona.

## Onda 1 — accertare prima di toccare le stringhe

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TPU01 | **fatto** (2026-08-13) | **`D2`** — **nessuno** confronta i messaggi 404 (`F9`), quindi tradurli non è una regressione. E poiché il developer ha chiesto di aggiungere i test se mancavano: `ProviderUserRoleNotFoundTest` fissa i tre messaggi attuali, così quando `TPU03` li farà passare dalle traduzioni **il diff lo mostrerà**. Un quarto test verifica che la chiave `provider_user_roles.not_found` **esista già** e in inglese coincida col literale | `tests/Feature/ProviderUserRoleNotFoundTest.php` (nuovo) | basso | auto | 4 verdi. **Scoperto scrivendoli**: il 404 di `update()` è irraggiungibile con un corpo non valido — la validazione gira prima e risponde 422 |

## Onda 2 — le correzioni

`TPU03` non comincia prima che `TPU01` abbia risposto.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TPU02 | **fatto** (2026-08-13) | **`F4`, `D4`** — il costruttore vuoto è cancellato, non commentato: un costruttore senza parametri né corpo non fa niente che PHP non faccia da sé, e il commento che SonarQube chiedeva sarebbe stato un commento su una riga che non serve | `ProviderUserRoleController.php` | basso | auto | `grep -c __construct` sul file restituisce **0**; suite 71 verdi; specifico OpenAPI identico |
| TPU03 | **fatto** (2026-08-13) | **`F1`, `F2`, `F10`** — helper `notFound(string $key)` sul controller base, che compone la 404 passando dalle traduzioni. **Le chiavi non sono state create**: esistevano già in due lingue e i controller le ignoravano | `app/Http/Controllers/Controller.php`, `ProviderUserRoleController.php` | medio — cambia il corpo di risposte che un client potrebbe leggere (`F9`: nessuno lo legge) | auto | i tre 404 rispondono col messaggio tradotto; un test prova che **la stessa rotta risponde in due lingue diverse**, cosa impossibile col literale |
| TPU04 | **fatto** (2026-08-13) | **`F5`** — `delete()` e `bulkDelete()` usano ora `response()->noContent()`. Il frontend non leggeva quel corpo (`DeleteProviderUserRoleDialog.vue:22` usa `.then(() => …)` con un testo suo), quindi nessuna rottura. **Scoperta verificando**: Laravel **svuota da sé** il corpo dei 204 quando spedisce, quindi via HTTP le due forme sono indistinguibili — il difetto era nel **codice che mente**, non nella risposta. La verifica è stata cambiata di conseguenza | `ProviderUserRoleController.php:341,364` | basso — nessun consumatore leggeva il corpo | auto | 4 test: 204 senza `Content-Type`, la cancellazione avviene davvero, il 404 il corpo ce l'ha, e nessun `response()->json(…, 204)` resta nel controller. **Provati nei due versi**: rimettendo il difetto, l'ultimo diventa rosso |

## Onda 3 — subordinati a una risposta

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TPU05 | **fatto** (2026-08-13) | **`F6`, `D5`** — la differenza **è voluta**: un'associazione cancellata logicamente si consulta, non si modifica. Scritta in **tre punti**, uno per metodo — `find()` dichiara la scelta, `update()` e `delete()` la richiamano dall'altro lato. Il mio sospetto nell'analisi era che fosse una svista: sbagliato, ed è annotato lì | `ProviderUserRoleController.php` (3 commenti) | nullo | man | i tre metodi dicono *scelta*, non *da fare* |
| TPU06 | **fatto** (2026-08-13) | **`D1`** — estensione a **tutte e dodici** le occorrenze su cinque controller. Due hanno richiesto un trattamento a parte: `"Role id not found"` era lo stesso messaggio **scritto male** ed è stato normalizzato sulla stessa chiave; `SessionController` restituisce anche `valid`, quindi è stato tradotto **senza** passare dall'helper — che avrebbe dato il solo `message` e cambiato il contratto | `RoleController.php`, `UserController.php`, `ProviderController.php`, `Web/ProviderController.php`, `SessionController.php` | medio | auto | `grep -rn 'message" => "…not found"' app/Http/Controllers/` non trova più niente — e un **test** lo asserisce, così non torna |

## Cosa questo piano non copre

- **Il costruttore vuoto in `app/Services/AccountService.php:27`** (`F7`): toccare un service fa
  scattare il controllo perf/leak completo, che per una riga di commento non si giustifica. Va nel
  task che toccherà quel service per motivi veri (`D6`).
- **La normalizzazione di `"Role id not found"`** (`F2`), che è lo stesso messaggio scritto male: la
  raccolgo in `TPU06` solo se `D1` dice di estendere.
- **`OA_DESC_MSG_NOT_FOUND`** (`F8`) resta dov'è: è la descrizione per la documentazione OpenAPI, non
  il messaggio restituito al client, e confonderli farebbe dipendere la risposta HTTP da una costante
  di documentazione.
