# Piano — l'errore che il server spiega e l'interfaccia non dice

Sigla dichiarata dall'analisi: `TER` — qui non si ridichiara.

Stato: **da approvare** · Data: 2026-08-20 · Analisi: [analysis.md](./analysis.md), che questo piano
**cita e non ripete**. `F…` e `D…` puntano lì. V — `auto`: lo stabilisce un comando · `man`: lo legge
una persona.

**Il primo punto è indipendente da tutte le domande** e chiude la segnalazione da cui il task nasce.
Il resto dipende da `D1` e `D2`, e tocca cosa l'utente legge quando qualcosa va storto.

**Priorità: bassa** — deciso il 2026-08-20. Il difetto è reale ma nessuno lo sta subendo in modo grave:
chi incontra un errore legge una frase sbagliata, non una schermata bianca. `TER01` resta l'eccezione,
perché costa tre righe e chiude la segnalazione da cui il task è nato.

## Onda 1 — il lato server, che non dipende da niente

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TER01 | da approvare | **Le tre risposte `404` senza corpo** (`F1`) diventano `$this->notFound("user.error.not_found")`: in `delete()`, `restore()` e `getUserRoles()`. Non è una scelta di stile — è **la forma che il repository ha già** in dodici altri punti (`F2`), e questi tre sono rimasti indietro. Effetto immediato: chi cancella un utente inesistente legge «Utente non trovato» invece di «errore durante l'eliminazione», **senza toccare una riga di JavaScript**, perché il dialogo il messaggio lo legge già (`F3`) | `app/Http/Controllers/Manage/UserController.php` | basso — stato invariato, si aggiunge un corpo | auto | tre test, uno per metodo: stato 404 **e** corpo `{"message": "Utente non trovato"}`. Oggi nessuno dei tre ha un test |

## Onda 2 — la frase generica

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TER02 | da approvare | **`D3` — le due frasi che oggi non esistono** (`F6`): `common.server_unreachable` per quando la risposta **non è mai arrivata**, e `common.unexpected_error` per quando è arrivata e non spiegava. Sono due casi distinguibili con precisione — `error.response` assente contro presente senza `data.message` — e oggi si confondono in una frase che parla dell'operazione mentre il problema è la rete (`F7`) | `lang/it.json`, `lang/en.json` | basso | auto | le due chiavi esistono in entrambe le lingue: lo verifica `TranslationKeysTest` appena qualcuno le usa |

## Onda 3 — il lato frontend, che dipende da `D1` e `D2`

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TER06 | da approvare | **L'inventario: dove i toast non seguono la regola.** La regola è nel § 3 — il messaggio del server ha la precedenza, la frase locale è il ripiego — e `DeleteUserDialog.vue` è il modello. Questo punto produce l'**elenco puntuale** dei `catch` che non la seguono: `file:riga` per ognuno, il componente, e per ciascuno se **può** seguirla o se rientra in una delle due eccezioni (messaggio non mostrabile, risposta mai arrivata). Il conto è già noto — **34 su 39** (`F4`) — la lista no, e senza lista `TER04` non si può dividere per componente. Lo produce uno script, non una rilettura: un `catch` dimenticato è un `catch` che resta sordo | nessuno (verifica, l'esito va in questa analisi) | basso | auto | l'elenco è nell'analisi, e **rieseguendo lo script il conto coincide**: se non coincide, la scansione non guarda tutto — è lo stesso controllo che `TranslationKeysTest` fa su se stesso |
| TER03 | da approvare | **`D2` — il posto dove sta la decisione**: una funzione condivisa che, dato l'errore di axios, restituisce il testo da mostrare — il messaggio del server se c'è, `common.server_unreachable` se la risposta non è arrivata, altrimenti la frase specifica del caso. Consigliata **(b)** e non la ripetizione: qui non si duplica un elenco di campi come in `TFC05`, si duplica **una decisione**, e 34 copie di una decisione divergono | `resources/js/Composables/` (nuovo) | medio | man | dato un errore senza `response`, esce la frase della rete; con `data.message`, esce quella del server; con `response` ma senza messaggio, quella del caso |
| TER04 | da approvare | **`D1` — i 34 `catch` che ignorano il messaggio** (`F4`), uno **per componente** e non tutti in un punto: nove componenti, nove interventi che si possono rivedere guardando lo schermo. Un punto unico da 34 file non si rivede, si approva | `resources/js/components/` — nove file | medio — cambia cosa l'utente legge in caso d'errore | man | per ogni componente: con il server che risponde 4xx **con** messaggio, l'utente legge quello; senza, la frase del caso; con il server spento, quella della rete |

## Onda 4 — il difetto che l'interfaccia stava nascondendo

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TER05 | da approvare | **`D4`, e va guardato prima di `TER04`**: `RoleController::delete()` fa `response()->json(["message" => $e], 500)` — **l'eccezione intera** nel corpo. Finché i `catch` ignorano `data.message` nessuno la vede; il giorno che cominciano a mostrarla, quel messaggio finisce **sullo schermo dell'utente**, con dentro quello che l'eccezione porta. Il difetto è del server, e `TER04` lo renderebbe visibile: va corretto prima, non dopo | `app/Http/Controllers/Manage/RoleController.php`, e ogni altro punto con la stessa forma | medio — è una possibile fuga di dettagli interni | auto | `grep -rn 'json(\["message" => \$e' app/` non trova più niente; un test verifica che il 500 porti un messaggio **scritto**, non l'eccezione |

## Cosa questo piano non copre

- **Un interceptor di axios** (strada **(c)** del § 3): normalizzerebbe il messaggio una volta per tutte,
  ma cambierebbe il comportamento di **ogni** chiamata, comprese quelle che oggi funzionano. Se `D2`
  dovesse orientarsi lì, è un task suo — non un punto in coda a questo.
- **I `catch` che non mostrano toast**: la scansione ne conta 39 con toast; gli altri fanno solo
  `console.error`, e sono un'altra questione.
- **Il fatto che nulla di tutto questo sia coperto da test nel frontend**: in questo repository non c'è
  modo di eseguire JavaScript (deciso in `frontend-complexity`, `D1`). `TER03` e `TER04` si verificano
  **a mano**, e questa riga è dove sta scritto.
