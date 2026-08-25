# Due API di stringa da aggiornare — tre rilievi di SonarQube

**Identificatori**: `TDS` = task deprecated-string-api

Stato: da approvare · Data: 2026-08-21

Elenco passato dal developer il **2026-08-21**, subito dopo la chiusura di
[regex-and-counters](../20260821-regex-and-counters/action-plan.md).

**Questi tre rilievi sono il residuo di quel lotto, e vanno detti per quello che sono: li ha
introdotti il codice scritto oggi in `TRC02`.** Non c'erano prima, perche' prima non c'era una
funzione che generasse le sequenze. Non e' un difetto del lotto precedente — quel lotto ha chiuso 51
finestre non riconosciute — ma e' un fatto che va scritto, perche' il modo di lavorare che lo evita
esiste: due righe scritte con l'API di oggi invece di quella di ieri.

## 1. Obiettivo

`usePassword.js` non usa piu' API di stringa deprecate o sconsigliate, e il comportamento e'
**identico**: nessuna password cambia punteggio. Sono due righe, e la verifica conta piu' della
modifica.

**Perche' adesso**: sono in un file toccato oggi, e con il confronto prima/dopo gia' pronto — lo stesso
strumento con cui si e' verificato `TRC02`. Fra un mese costerebbe rifare l'apparato per due parole.

## 2. Situazione attuale

| Rilievo | Dove | Cosa c'e' |
|---|---|---|
| `String.fromCodePoint()` invece di `String.fromCharCode()` | `resources/js/Composables/usePassword.js:29` | `String.fromCharCode(firstCode + i)` dentro `alphabetFromCodes()` |
| firma `substr(from, length?)` deprecata | `resources/js/Composables/usePassword.js:71` | `text.substr(start, SEQUENCE_LENGTH)` dentro `hasSequence()` |

Il primo rilievo e' **contato due volte** nell'elenco del developer, ma la riga e' una: `29`.

**Cercate altrove, e non ci sono**: `grep -rnE "fromCharCode|\.substr\("` su `resources/js/` e
`cypress/` trova **soltanto** queste due righe. Nessun `escape()`, nessun `unescape()`, nessun altro
`substring()` in giro. Quindi il lotto e' chiuso in due punti, e non c'e' la coda «gli altri uguali che
il report non nomina» che ha accompagnato i lotti precedenti.

**Dipendenze**: `alphabetFromCodes()` e `hasSequence()` sono interne al modulo — non esportate, non usate
altrove (`grep` su `resources/js/`). `passwordStrength()` e' l'unica porta d'ingresso, e la usa il
frontend per la barra di robustezza. **Non ci sono test JavaScript**: la verifica e' il confronto
prima/dopo, come per `TRC01`…`TRC03`.

## 3. Analisi

**`fromCharCode` → `fromCodePoint`.** Le due funzioni differiscono **solo** sopra `0xFFFF`:
`fromCharCode` lavora su unita' UTF-16 e per un code point astrale va alimentata con la coppia di
surrogati, `fromCodePoint` lo accetta intero. Qui gli argomenti sono `97..122` e `48..57` — ASCII — e le
due danno **la stessa stringa**. Il rilievo ha ragione comunque, e non per pedanteria: il giorno che
qualcuno aggiungesse un alfabeto non latino a `SEQUENCE_SOURCES`, `fromCharCode` sbaglierebbe in
silenzio. La sostituzione e' una parola.

**`substr` → `slice`.** `substr(from, length)` e' deprecata perche' e' l'unica delle tre con il
**secondo argomento diverso**: `slice(from, to)` e `substring(from, to)` prendono un indice di fine.
La traduzione e' `slice(start, start + SEQUENCE_LENGTH)`. Con argomenti non negativi — che e' il nostro
caso, `start` parte da `0` e cresce — le tre sono equivalenti. Si preferisce `slice` a `substring`
perche' `substring` scambia gli argomenti quando sono invertiti, cioe' nasconde un errore invece di
mostrarlo.

**Un'alternativa vista e scartata**: togliere `alphabetFromCodes()` e scrivere `"abcdefghijklmnopqrstuvwxyz"`
e `"0123456789"` come stringhe. Farebbe sparire il rilievo insieme alla funzione, ed e' meno codice. Ma
la derivazione dai codici e' stata una **richiesta esplicita** del developer in `D1` di `TRC`, e ha una
ragione che resta valida: una stringa scritta a mano e' il posto dove manca una lettera senza che
nessuno lo noti — che e' esattamente il difetto che quel lotto ha chiuso. Non si torna indietro per due
parole.

**Cancellazioni**: nessuna.

## 4. Da decidere

### Vincoli

- Nessuno. Le due sostituzioni non cambiano comportamento e non toccano interfacce.

### Conflitti

- ~~**`D1`**~~ — **risposta del developer, 2026-08-21: si', i bordi si aggiungono al confronto.** Il
  confronto su 20045 password distinte dice se qualcosa e' cambiato **per quelle**; su un cambio di
  firma la differenza, se c'e', sta ai bordi. I quattro casi sono quelli del § 5 — password vuota,
  password piu' corta della finestra, alfabeto piu' corto della finestra, password che finisce
  esattamente con l'ultima finestra — e diventano parte della verifica di `TDS02`, non un controllo a
  parte: se il confronto resta a zero differenze anche la', la prova regge.

### Ignoto

- Niente di ignoto: due righe, entrambe lette, entrambe con una traduzione meccanica.

## 5. Consigli

- **`D1`: aggiungere ai casi del confronto i bordi**, e sono quattro: password vuota, password piu'
  corta della finestra, alfabeto piu' corto della finestra (`SEQUENCE_SOURCES` con `"ab"`, per vedere
  che il ciclo non entri mai) e una password che finisce **esattamente** con l'ultima finestra di un
  alfabeto — `"...vwxyz"`. Se il confronto resta a zero differenze anche la', la prova regge.
- **Farlo adesso e in un solo giro**: due parole, un confronto, e il lotto si chiude. Aspettare
  significa rifare l'apparato di verifica per due parole.
