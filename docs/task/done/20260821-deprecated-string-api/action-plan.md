# Piano — due API di stringa da aggiornare

Sigla `TDS`. L'analisi e' in [analysis.md](./analysis.md) e questo piano **la cita, non la ripete**: la
differenza fra le due coppie di funzioni, l'alternativa scartata e il fatto che questi tre rilievi
**vengono dal codice scritto oggi** stanno la'.

**Priorita' bassa e lotto corto**: due righe. Il peso sta nella verifica, non nella modifica.

**`D1` risposta il 2026-08-21**: i quattro casi ai bordi entrano nella verifica di `TDS02`. Nessun punto
aspetta piu' una risposta.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TDS01 | **fatto** (2026-08-21) | `String.fromCharCode` → `String.fromCodePoint` in `alphabetFromCodes()`. Il perché sta ora nel docblock della funzione, dove lo legge chi la modifica: per l'ASCII di qui le due danno la stessa stringa, ma sopra `0xFFFF` `fromCharCode` vuole la coppia di surrogati — e il giorno che a `SEQUENCE_SOURCES` si aggiungesse un alfabeto non latino sbaglierebbe **in silenzio** | `resources/js/Composables/usePassword.js` | basso | auto | confronto prima/dopo: **0 differenze** su 20006 password distinte. E il controllo che conta per questo punto: le **94** finestre generate dai cinque insiemi restano tutte riconosciute — se gli alfabeti fossero cambiati, quel numero cadrebbe |
| TDS02 | **fatto** (2026-08-21) | `text.substr(start, SEQUENCE_LENGTH)` → `text.slice(start, start + SEQUENCE_LENGTH)`, col perché accanto al ciclo: `substr` è l'unica delle tre con il secondo argomento **diverso** — una lunghezza invece di un indice — ed è deprecata per questo; `slice` e non `substring` perché `substring` **scambia** gli argomenti se sono invertiti, cioè nasconde un errore invece di mostrarlo | `resources/js/Composables/usePassword.js` | basso | auto | **i quattro bordi di `D1`, uno per uno**: password vuota `0 → 0`; più corta della finestra (`"a"`, `"abc"`) `1 → 1`; che finisce esattamente con l'ultima finestra (`…vwxyz`, `…0123`, `…3210`) `2 → 2`; e l'alfabeto più corto della finestra — provato patchando `SEQUENCE_SOURCES` con `"ab"` e `"xyz"` in **entrambe** le versioni: 8 casi, **0 differenze** e nessuna eccezione, perché la condizione del ciclo non ci entra nemmeno |
| TDS03 | **fatto** (2026-08-21) | Il controllo che non ce ne siano altre, scritto una volta. **E il comando che avevo messo nel piano era sbagliato**: `grep -rnE "fromCharCode|\.substr\("` conta anche i **commenti** che nominano l'API vecchia — dopo `TDS01` trovava due righe che sono spiegazioni, non codice. È lo stesso inciampo di `TEW07` con `cy.wait`, e la forma giusta filtra le righe di commento: `grep -rnE "^[^*/]*(fromCharCode|\.substr\()" resources/js/ cypress/` | nessuno (verifica) | basso | auto | il comando corretto non trova **niente** in `resources/js/` e in `cypress/` |
| TDS04 | **chiuso dal developer** (2026-08-21) | La conferma dal report: i tre rilievi non compaiono più. **Il report l'ha guardato il developer**, non l'agente | nessuno (verifica) | basso | man | il developer ha confermato dopo aver letto il report |

## Cosa questo piano non copre

- **Le altre API deprecate del frontend, se ce ne sono**: `TDS03` cerca **queste due**, non fa una
  passata su tutto il linguaggio. Un controllo generale è un lavoro suo, e lo strumento che lo fa già
  esiste — è SonarQube.
- **Il fatto che questi tre rilievi nascano dal codice di oggi**: il piano li chiude, non cambia il modo
  di lavorare che li ha prodotti. Se vale la pena scriverlo come regola — «l'API la si scrive nella
  forma di oggi, non in quella che si ricorda» — il posto è `backlog/`, non un punto qui.

## Perf/leak — la dichiarazione della policy per `TDS01` e `TDS02`

Policy dell'organizzazione, voce per voce. Le due modifiche sono **due chiamate di funzione** dentro un
composable del frontend, che gira nel browser e non parla con il server.

| Voce | Esito | Perché |
|---|---|---|
| Query N+1 | non applicabile | nessuna query: `passwordStrength()` calcola in memoria, non chiede niente a nessuno |
| Data leakage | non applicabile | nessuna API Resource e nessun campo esposto. La password non lascia il browser: entra nella funzione ed esce un numero da 0 a 5 |
| Scope/tenant | non applicabile | la funzione non conosce utenti né provider |
| Memory/streaming | **verificato, e migliora di un filo** | `slice` e `substr` allocano entrambe una stringa di quattro caratteri per finestra; il ciclo si ferma alla prima che combacia, come prima. Gli alfabeti restano costruiti **una volta** al caricamento del modulo |
| Query non vincolate | non applicabile | nessuna query |

