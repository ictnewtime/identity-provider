# Analisi — complessità cognitiva nel frontend, e un errore trovato leggendolo

**Identificatori**: `TFC` = task frontend-complexity

Stato: **chiuso** (2026-08-19) · Data: 2026-08-19

## 1. Obiettivo

Portare sotto la soglia di 15 le tre funzioni che SonarQube segnala come `high`:

| Funzione | Complessità | File |
|---|---|---|
| `strength` | **22** | `resources/js/Composables/usePassword.js` |
| `submit` | **25** | `resources/js/components/ProviderForm.vue` |
| `submit` | **16** | `resources/js/components/ProviderUserRoleForm.vue` |

È il gemello frontend di [findings-v2](../../done/20260812-static-analysis-findings-v2/action-plan.md),
che ha fatto lo stesso lavoro sul PHP. Ma quel task poteva appoggiarsi a PHPUnit, e qui **non c'è niente
che esegua codice JavaScript** (`F4`): è il vincolo che decide la forma del lavoro, non la complessità.

E leggendo quelle funzioni per capirle è venuto fuori un **errore in esercizio** che non c'entra con la
complessità (`F6`). Sta in questo task perché sta in una di quelle righe, ma va corretto **prima** e da
solo, o la correzione si nasconde dentro un rifacimento.

## 2. Situazione attuale

| # | Fatto | Prova |
|---|---|---|
| F1 | `strength` è **logica pura**: prende una stringa e restituisce un numero da 0 a 5. Non tocca la rete, non tocca il DOM. La complessità viene da **nove condizioni indipendenti** — lunghezza, varietà, caratteri ripetuti, sequenze da tastiera, sequenze alfabetiche, blocchi ripetuti, monotonia — ognuna con un suo `if` | `usePassword.js`, la `computed` |
| F2 | In `ProviderForm.submit` la complessità è **concentrata**: sei `if (backendErrors.X) errors.value.X = backendErrors.X[0]` uno sotto l'altro, più due ternari sul messaggio del toast. Il resto della funzione è lineare | `ProviderForm.vue:135-152` |
| F3 | `ProviderUserRoleForm.submit` ha **la stessa forma** con tre campi invece di sei, ed è **uno solo** sopra la soglia: 16 contro 15 | `ProviderUserRoleForm.vue:163-176` |
| F4 | **Non esiste un modo di eseguire codice JavaScript in questo repository**: `package.json` non ha `vitest` né `jest` né uno script `test`; fra le dipendenze di sviluppo ci sono solo build, `prettier` e `cypress` | `npm run` non elenca nessun `test` |
| F5 | **E Cypress oggi non gira**: manca il container ([e2e-test-container](../../todo/20260812-e2e-test-container/), `TEC`), e `cypress/e2e/provider/crud-provider.cy.js` — la specifica che avrebbe coperto `ProviderForm` — **è stata cancellata oggi**, perché conteneva zero test | `cypress/e2e/` ha 3 file: `auth/login`, `user/crud-user`, `user/search-accented` |
| F6 | **`ProviderUserRoleForm.vue:170` fa `emit("item-error", err)` dentro un `catch (error)`.** `err` in quel punto non esiste: gli altri `err` del file stanno in due `catch` diversi, righe 53 e 75. A ogni salvataggio fallito quella riga solleva `ReferenceError`, e **le righe successive non vengono eseguite** — cioè gli errori per campo del backend non compaiono mai | `grep -n "\berr\b" ProviderUserRoleForm.vue` → 53, 54, 61, 75, 76, 83, **170** |
| F7 | `usePassword` è usato da **tre** schermate: `UserForm.vue`, `Pages/Auth/ForcePasswordChange.vue`, `Pages/Auth/ResetPassword.vue`. Un cambiamento nel punteggio si vede in tutte e tre | `grep -rln usePassword resources/js/` |

### Dipendenze e breaking change

- **`strength` è un giudizio che l'utente legge**: la barra di robustezza della password. Se il
  rifacimento cambiasse il punteggio di un caso, nessuno se ne accorgerebbe — non c'è test, e la
  differenza è un colore diverso su una barra. È il rischio principale di tutto il task.
- **I due `submit` parlano col backend**: cambiare la mappatura degli errori significa cambiare cosa
  l'utente vede quando sbaglia un campo. È lo stesso punto che oggi è rotto in uno dei due (`F6`).
- **Nessun cambiamento di comportamento è previsto**: l'obiettivo è un numero, non una funzione nuova.
  Che è precisamente la ragione per cui serve un modo di dimostrare che il comportamento non è cambiato.

## 3. Analisi

### `strength`: nove condizioni diventano una tabella

Le condizioni non sono annidate: sono **nove regole indipendenti** scritte una sotto l'altra. Una
tabella di regole — espressione regolare, e tetto che impone — attraversata da un ciclo, le riduce a
una: il codice diventa *dato* più *un ciclo*, e la complessità cognitiva crolla senza che il punteggio
cambi di un punto. Il blocco della monotonia (un ciclo sui caratteri) esce in una funzione con nome.

**È anche il caso più adatto a un test** che ci sia in questo repository: entra una stringa, esce un
numero. Una tabella di casi ostili — `aaa`, `qwerty123`, `abcabcabc`, `Prova-2026!x` — vale più di
qualunque rilettura.

### I due `submit`: la duplicazione è il vero rilievo

Sei `if` che copiano `backendErrors.X` in `errors.value.X` sono **un ciclo su un elenco di campi**.
E i due componenti fanno la stessa cosa con elenchi diversi: la forma va in un posto solo — un
composable `useFormErrors` — e i due `submit` la chiamano. Così:

- la complessità scende in tutt'e due i punti;
- la duplicazione fra i componenti sparisce, e non era nel rilievo;
- il difetto `F6` non può ripetersi in un terzo form, perché il codice che sbaglia non c'è più.

Gli altri form (`RoleForm`, `UserForm`) hanno la stessa forma e **non** sono nei rilievi: si toccano solo
se il developer lo chiede, per non allargare il lavoro dove nessuno l'ha chiesto.

### Il verificare, che qui è il problema vero

Tre strade, e la scelta non è di stile:

| Strada | Cosa dà | Cosa costa |
|---|---|---|
| **(a) `vitest`** | l'unico modo di provare che `strength` dà gli **stessi punteggi** di prima. Vite è già in casa: `vitest` ne riusa la configurazione | una dipendenza di sviluppo e uno script `test` nuovi. Va deciso, non aggiunto di nascosto |
| **(b) A mano nel browser** | ripetibile quanto è ripetibile una persona | il difetto `F6` è nato e sopravvissuto **esattamente** così: nessuno ha mai fatto fallire quel salvataggio |
| **(c) Il solo numero di SonarQube** | dice che la complessità è scesa | non dice niente sul comportamento. Verifica il rilievo, non il prodotto |

### Codice cancellato

Le sei righe `if (backendErrors.X)` di `ProviderForm`, le tre di `ProviderUserRoleForm`, e le nove
condizioni sparse di `strength` — sostituite da dati e da un ciclo.

## 4. Da decidere

### Vincoli

- ~~**`D1`**~~ — **risposta del 2026-08-19: (c), per ora ci si accontenta del numero di SonarQube.**
  Nessun `vitest`. **Conseguenza registrata**: `strength` si rifà senza rete, e un punteggio che cambia
  non lo vede nessuno — si manifesterebbe come un colore diverso su una barra. Il § 5 propone la cosa
  che si può fare **senza** aggiungere niente al progetto.
- ~~**`D2`**~~ — **risposta: funzioni nello stesso file**, non un composable condiviso. Ogni componente
  si tiene le sue. **Conseguenza**: la duplicazione fra i due form resta — resta anche la forma in cui
  `F6` è nato — e i due file sono indipendenti l'uno dall'altro, che è il pregio della scelta.

### Conflitti

- ~~**`D3`**~~ — **risposta: sì, in questo task e come punto a sé.** È `TFC01`, e sta prima dei
  rifacimenti.

### Ignoto

- ~~**`D4`**~~ — **risposta: non è lavoro di questo task.** La specifica Cypress di `ProviderForm`, se
  tornerà, tornerà con `TEC`.

## 5. Consigli

- ~~**`D1` → (a)**~~ — **il developer ha scelto (c)**, e va bene per il rilievo. Ma «niente esecutore di
  test» non deve diventare «niente verifica»: `node` c'è (v22 sull'host, v20 nel container), quindi
  `strength` si può rifare **confrontando i punteggi**. Un file usa-e-getta fuori dall'albero esegue la
  versione **vecchia** e quella **nuova** su qualche centinaio di password — corte, lunghe, ripetute,
  sequenze, monotone, robuste — e stampa le differenze. Se non ce ne sono, il rifacimento è dimostrato;
  il file poi si butta, e nel progetto non resta nessuna dipendenza nuova. È la strada (b) fatta da una
  macchina invece che da una persona, e costa una volta.
- ~~**`D2` → il composable**~~ — **il developer ha scelto le funzioni nel file.** Il prezzo è che la
  duplicazione fra i due form resta, e con essa la forma in cui `F6` è nato; il guadagno è che nessuno
  dei due componenti dipende dall'altro, e che il lavoro non tocca file che nessun rilievo nomina.
- **`D3` → punto a sé, e per primo.** È un difetto in esercizio, si corregge in una riga, e va visto in
  revisione come una riga — non come un dettaglio di un rifacimento da 25 a 15.
- **`D4` → di `TEC`.** Questo task non riscrive specifiche E2E che non possono girare.
