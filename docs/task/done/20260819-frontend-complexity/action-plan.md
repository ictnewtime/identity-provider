# Piano — complessità cognitiva nel frontend

Sigla dichiarata dall'analisi: `TFC` — qui non si ridichiara.

Stato: **chiuso** (2026-08-19) — 4 punti fatti, 2 scartati · Data: 2026-08-19 · Analisi: [analysis.md](./analysis.md), che questo piano
**cita e non ripete**. `F…` e `D…` puntano lì. V — `auto`: lo stabilisce un comando · `man`: lo legge
una persona.

I punti sono ordinati così: prima il difetto in esercizio, poi i rifacimenti. **`D1` ha escluso i test
JavaScript** (`TFC02`, `TFC03` scartati), quindi la verifica di `TFC04` è un **confronto dei punteggi
eseguito una volta** con `node`, e quella di `TFC05` è a mano. Vale la pena saperlo leggendo il piano:
due dei tre rifacimenti chiudono un rilievo senza lasciare in casa niente che li difenda domani.

## Onda 1 — il difetto, prima di toccare la forma

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TFC01 | **fatto** (2026-08-19) | **`F6`, `VDF22`** — `emit("item-error", err)` dentro un `catch (error)`: `err` non esisteva in quel punto, quindi ogni salvataggio fallito sollevava `ReferenceError` e le righe successive — quelle che copiano gli errori del backend sui campi — **non giravano**. Una parola: `err` → `error` | `resources/js/components/ProviderUserRoleForm.vue:170` | basso | auto | **cercato altrove prima di dichiararlo chiuso**: uno script attraversa tutti i `catch` di `resources/js/`, ne prende il corpo per bilanciamento di graffe e verifica che non nomini una variabile d'errore **diversa** da quella catturata — scartando accessi a proprietà e stringhe, che alla prima passata davano sette falsi positivi. Prima: **un** vero caso, questo. Dopo: **nessuno**. E il blocco `<script setup>` estratto passa `node --check` |

## Onda 2 — il modo di verificare

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TFC02 | **scartato** (2026-08-19) | Aggiungere `vitest`. **`D1`: (c), per ora ci si accontenta del numero di SonarQube.** **Cosa resta scoperto**: in questo repository non esiste ancora nessun modo di eseguire una funzione JavaScript, quindi ogni rifacimento nel frontend resta senza rete — e `VDF22` è il difetto che è sopravvissuto per esattamente questa ragione. Il punto non è riscritto ma scartato: se un giorno servirà, servirà per un motivo suo | — | — | — | — |
| TFC03 | **scartato** (2026-08-19) | La tabella dei casi per `strength`, in `vitest`. **Scartato con `TFC02`**: senza esecutore non c'è dove scriverla. **Al suo posto, in `TFC04`, un confronto usa-e-getta**: `node` c'è, quindi la versione vecchia e la nuova si eseguono su qualche centinaio di password e si confrontano i punteggi. È una verifica che vale una volta e non resta nel progetto — meno di un test, molto più di una rilettura | — | — | — | — |

## Onda 3 — i tre rifacimenti

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TFC04 | **fatto** (2026-08-19) | **`strength` da settanta righe a una** (`F1`): `const strength = computed(() => passwordStrength(getPwd()))`. Le nove condizioni sono diventate **tre tabelle di dati** — `LENGTH_POINTS`, `CHARACTER_FAMILIES` + `VARIETY_POINTS`, `PREDICTABLE_PATTERNS` — e **cinque funzioni con un nome**: `lengthPoints`, `varietyPoints`, `isMonotonous`, `isPredictable`, `passwordStrength`. Le due asimmetrie del calcolo, che nel codice originale si leggevano solo contando, ora sono scritte: i punti per lunghezza sono **cumulativi**, quelli per varietà **no**. `passwordStrength()` è **esportata**: è logica pura, e chi un giorno aggiungerà un esecutore di test la trova senza istanziare Vue | `resources/js/Composables/usePassword.js` | **alto senza rete** — decide cosa l'utente legge sulla robustezza della password | auto | **confronto dei punteggi vecchio contro nuovo su 8270 password**, con la versione precedente copiata **verbatim** in uno script `node` usa-e-getta: casi ostili scelti a mano (`aaa`, `qwerty123`, `abcabcabc`, monotone, accentate, giapponesi, spazi) più generazione deterministica su sei alfabeti e lunghezze 0-24. **Zero differenze.** Provato nei due versi: portando `MONOTONY_THRESHOLD` da `0.3` a `0.4` il confronto trova **447** differenze, quindi lo zero significa qualcosa |
| TFC05 | **fatto** (2026-08-19) | **`D2`: funzioni nello stesso file.** In ciascuno dei due componenti due aiuti privati: `applyBackendErrors()` — un elenco di campi più un ciclo, al posto dei sei `if` in `ProviderForm` e dei tre in `ProviderUserRoleForm` — e `savedDetail()`, che porta fuori dal `submit` il ternario sul messaggio del toast. I due `submit` restano lineari: validazione, payload, `try`, due `toast`, `finally`. **La duplicazione fra i due file resta e sta scritta nel commento**: è il prezzo di `D2`, non una dimenticanza | `ProviderForm.vue`, `ProviderUserRoleForm.vue` | medio | auto | **confronto esaustivo**, non a mano: uno script `node` esegue la catena di `if` di prima e il ciclo di adesso su **tutti** i sottoinsiemi dei campi (64 + 8) più le forme degeneri — `{}`, `response` vuota, `errors: null`, un campo che non esiste, array vuoti. **84 casi, zero differenze.** E gli elenchi che ho scritto a mano sono stati confrontati con i campi degli `if` originali, estratti dai file salvati: **identici e nello stesso ordine**. Provato nei due versi: facendo saltare un campo al ciclo, il confronto trova 36 differenze |
| TFC06 | **fatto** (2026-08-19) | La conferma dal report: le tre funzioni sono sotto la soglia di 15. **Approvato da SonarQube**, riferito dal developer — è la sola prova che il rilievo è chiuso, e **non dice niente sul comportamento**: quello lo dicono i confronti eseguiti in `TFC04` (8270 password, zero differenze) e `TFC05` (84 casi, zero differenze) | nessuno (verifica) | basso | man | i tre rilievi `high` non compaiono più nel report |

## Cosa questo piano non copre

- **Le specifiche Cypress** (`D4`, `F5`): `ProviderForm` non ha più una specifica E2E — quella che
  c'era è stata cancellata oggi perché non conteneva test — e il container per eseguirle non esiste.
  Riscriverle è lavoro di [e2e-test-container](../../todo/20260812-e2e-test-container/), non di qui.
- **Gli altri form con la stessa forma**: `RoleForm`, `UserForm` e le tabelle. Non sono nei rilievi, e un
  lavoro non chiesto è un rischio non chiesto. Il composable di `TFC05` è lì il giorno che servano.
- **Il resto del quality gate**: questo piano chiude tre rilievi. Chiusi i punti, il task va in `done/`.
