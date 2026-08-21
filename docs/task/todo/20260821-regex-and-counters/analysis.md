# Regex e contatori — sette rilievi di SonarQube

**Identificatori**: `TRC` = task regex-and-counters

Stato: da approvare · Data: 2026-08-21

Elenco passato dal developer il **2026-08-21**. Sette rilievi in tre file, tutti di leggibilita' o
igiene dei test — nessuno rompe qualcosa che funziona. **Priorita' bassa**, con un'eccezione che
merita attenzione: leggendo i due pattern per il rilievo sulla complessita' e' venuto fuori che
**quelle liste hanno dei buchi**, e i buchi sono un difetto vero.

## 1. Obiettivo

Alla fine devono essere vere quattro cose.

**Le due espressioni regolari lunghe di `usePassword.js` scendono sotto la complessita' consentita**, e
non a scapito di cosa controllano — anzi: oggi controllano **meno** di quel che sembra.

**`[0-9]` diventa `\d`** nella lista delle famiglie di caratteri. Un rilievo di una parola, e lo
scriviamo per completezza.

**I tre `foreach (range(1, N) as $i)` di `AuditListTest` non dichiarano piu' una variabile che non
usano**, e i tre numeri — 5, 9, 12 — smettono di essere numeri nudi in mezzo al test.

**La riflessione in `CustomAuditableTest` e' decisa, non lasciata la'**: o si tiene e si dice perche' e'
sicura, o si toglie usando la leva che Laravel offre.

**Perche' adesso**: sono sette rilievi della stessa passata, e cinque su sette stanno nei test —
il posto dove un difetto non si vede perche' «i test passano».

## 2. Situazione attuale

| Rilievo | Dove | Cosa c'e' |
|---|---|---|
| `\d` invece di `[0-9]` | `resources/js/Composables/usePassword.js:17` | `const CHARACTER_FAMILIES = [/[A-Z]/, /[a-z]/, /[0-9]/, /[^A-Za-z0-9]/]` |
| complessita' 22 > 20 | `usePassword.js:27` | 23 alternative: sequenze numeriche, tre pezzi di riga da tastiera, tre parole |
| complessita' 22 > 20 | `usePassword.js:29` | 23 alternative: tutte le finestre di quattro lettere dell'alfabeto |
| bypass di accessibilita' | `tests/Feature/Audit/CustomAuditableTest.php:39-40` | `new \ReflectionProperty($this->app, "isRunningInConsole")` e `setValue(..., false)` |
| variabile `$i` non usata | `tests/Feature/AuditListTest.php:66` | `foreach (range(1, 5) as $i) { $this->audit(); }` |
| variabile `$i` non usata | `tests/Feature/AuditListTest.php:185` | uguale, con 9 e un `$this->audit([...])` |
| variabile `$i` non usata | `tests/Feature/AuditListTest.php:215` | uguale, con 12 |

**Il fatto che cambia il lavoro**: le due liste scritte a mano **non sono complete**, e l'ho misurato
generando le finestre di quattro caratteri e confrontandole con quelle presenti nel pattern.

| Insieme | Finestre esistenti | Presenti nel pattern | Mancanti |
|---|---|---|---|
| cifre crescenti `0123456789` | 7 | 6 | **`0123`** |
| cifre decrescenti `9876543210` | 7 | 6 | **`3210`** |
| riga da tastiera `qwertyuiop` | 7 | 3 | **`rtyu`, `tyui`, `yuio`, `uiop`** |
| alfabeto `a…z` | 23 | 23 | nessuna |

Quindi `Passw0123` non e' considerata prevedibile, e `qwertyuiop` — la riga intera — passa il controllo
sulle sue ultime quattro lettere. Non e' un difetto che rompe: e' un controllo che **dice di aver
guardato** e ha guardato in parte. Le due righe da tastiera `asdfghjkl` e `zxcvbnm` hanno lo stesso
buco (tre finestre su sei e due su quattro).

**Dipendenze e breaking change.** `passwordStrength()` e' esportata da `usePassword.js` e usata dal
frontend per la barra di robustezza; `isPredictable()` alza il verdetto a «debole». **Non ci sono test
JavaScript in questo repository** — nessun `vitest`, nessun `jest` — quindi qualunque modifica qui si
verifica come si verifico' `TFC01`: con uno script usa-e-getta che confronta il **prima** e il **dopo**
su un insieme di password generate. Il numero da battere e' quello di allora: 8270 confronti.

`AuditListTest` gira nella suite (11 test) e `CustomAuditableTest` anche (12): entrambi sono coperti
dall'esecuzione, quindi la' la verifica e' la suite.

## 3. Analisi

### Le due regex: due strade, e non fanno la stessa cosa

**Strada (a) — spezzare.** `PREDICTABLE_PATTERNS` e' **gia'** un array di espressioni: dividere ogni
regex da 23 alternative in due da ~12 fa scendere la complessita' sotto la soglia e **non cambia una
virgola** del comportamento. Costo: due righe in piu'. Non chiude i buchi.

**Strada (b) — generare.** Le sequenze non si scrivono, si ricavano: dato un alfabeto (`"abc…z"`,
`"0123456789"`, `"qwertyuiop"`, `"asdfghjkl"`, `"zxcvbnm"`) si scorrono le finestre di quattro
caratteri, nei due versi, e si cerca `includes()`. Sparisce ogni regex lunga — quindi il rilievo cade
per costruzione, non per soglia — e i buchi si chiudono tutti insieme. Restano a parte le tre parole
ovvie (`pass`, `admin`, `login`), che sono un elenco e non una sequenza.

La (b) **rende il controllo piu' severo**: password che oggi passano diventerebbero «prevedibili».
Nessuna password memorizzata cambia e nessuno viene bloccato — `isPredictable()` influenza solo la
barra e il suggerimento — ma un utente che ieri vedeva «media» oggi vede «debole». E' una decisione di
prodotto, non di stile: sta nel § 4.

### La riflessione nel test

`Application::runningInConsole()` memorizza il proprio esito, quindi sotto PHPUnit vale sempre `true` e
il trait di audit resta inerte. La leva usata e' `ReflectionProperty` su `isRunningInConsole` — ed e'
esattamente cio' che il rilievo segnala: un test che raggiunge una proprieta' privata.

Due strade. **Tenerla**, e allora il rilievo si segna come rivisto in SonarQube: e' un test, la
proprieta' e' dell'applicazione di test, e il codice di produzione non si tocca. **Toglierla**, usando
la variabile che Laravel legge prima di memorizzare — `APP_RUNNING_IN_CONSOLE` — impostata **prima** di
`parent::setUp()`, cioe' prima che l'applicazione nasca. La seconda e' piu' pulita e ha un rischio suo:
va verificato che la variabile arrivi davvero prima della memorizzazione, e che non resti impostata per
i test successivi.

### I contatori

Il developer propone un `while` con `i++` e delle costanti per il massimo. La stessa idea con una
piega in piu': i tre loop chiamano `$this->audit()` e nient'altro, quindi il conteggio diventa un
**metodo d'appoggio** — `audits(5)` — e i tre numeri diventano tre costanti col loro nome. Il rilievo
cade perche' la variabile sparisce del tutto, e il test guadagna: `RIGHE_PER_IL_TETTO = 12` dice cosa
sono quelle dodici righe, `range(1, 12)` no.

**Cancellazioni**: nessuna, se non le due regex lunghe nella strada (b).

## 4. Da decidere

**Risposte del developer, 2026-08-21.** Tre chiuse, una nuova aperta da una contraddizione fra due di
esse.

### Vincoli

- ~~**`D1`**~~ — **generare dove e' possibile, e dove non lo e' spezzare per contesto.** Le sequenze si
  ricavano dai codici dei caratteri: si parte da `A` (65) o da `a` (**97**, non 98 come nella nota — e'
  l'unico numero della risposta che cambia il codice) e si scorre; per le cifre si parte da `1` e si va
  avanti di tre, e arrivati a `9` si procede all'indietro. Cosi' l'algoritmo genera le finestre invece
  di tenerle scritte. Regola per il resto: **se le alternative sono unite da un contesto non si
  spezzano; se non lo sono, si spezzano per contesto** — non a meta' per far scendere un numero.
- ~~**`D3`**~~ — **scelta la (b)**, generare.

### Conflitti

- ~~**`D2`**~~ — **si prova a togliere la riflessione, e si tiene se la prova fallisce**, con il report
  scritto qui sotto al § 6. Se resta, il rilievo si giustifica in SonarQube per quello che e': «questo
  bypass serve per un test». **La prova e' stata fatta, ed e' riuscita** — § 6.

### Ignoto

- **`D4` — le righe da tastiera: generate anche loro, o esplicite come chiede il developer?** Nasce da
  una contraddizione fra due risposte, e va sciolta perche' cambia il risultato:
  - `D3` dice **(b)**, generare, e la (b) chiudeva **sei** buchi;
  - ma la risposta su `qwer|wert|erty|asdf|sdfg|dfgh|zxcv|xcvb` dice di lasciarlo **esplicito**, perche'
    «non c'e' modo di semplificarlo», e al massimo di accettare il rilievo su quel pezzo.

  **Quattro dei sei buchi stanno proprio la'**: `rtyu`, `tyui`, `yuio`, `uiop` — e le altre due righe
  hanno lo stesso problema (`asdfghjkl`: tre finestre su sei; `zxcvbnm`: due su quattro). Lasciando quel
  pezzo esplicito, `TRC03` non puo' arrivare a zero.

  **Il punto tecnico che vale la pena dire**: l'intuizione «non si semplifica» e' giusta **per una
  regex** — una riga di tastiera non ha un ordine alfabetico, quindi non si esprime con un intervallo.
  Ma non vale per il **generatore**: al generatore una riga si passa come **stringa** (`"qwertyuiop"`),
  esattamente come si passa `"abcdefghijklmnopqrstuvwxyz"`. E' lo stesso codice, con un dato in piu' —
  zero righe aggiuntive — e chiude i quattro buchi.

## 5. Consigli

- **`D1`: la (b), generare.** Il rilievo sulla complessita' e' il sintomo, non la malattia: una lista
  scritta a mano di 23 casi e' esattamente il posto dove mancano sei casi senza che nessuno se ne
  accorga. Generarle rende il codice piu' corto **e** il controllo completo, e il rilievo cade per
  costruzione. Sulla severita' maggiore: e' il verso giusto — un suggerimento che dice «debole» su
  `qwertyuiop` sta facendo il suo lavoro.
- **`D2`: provare a togliere la riflessione, e tenerla se la prova fallisce.** Il costo della prova e'
  un'esecuzione della suite; il beneficio e' un rilievo che si chiude senza motivazioni da ricordare.
  Se `APP_RUNNING_IN_CONSOLE` non arriva in tempo, la riflessione resta ed e' il rilievo a essere
  segnato come rivisto — con il perche' scritto nel test, dove lo legge chi lo apre.
- **`D3`: se si sceglie la (b) la domanda decade**, perche' le righe da tastiera vengono generate intere.
  Se si sceglie la (a), chiudere i buchi vale piu' del rilievo: meglio due regex un po' complesse che
  un controllo che guarda meta' della tastiera.

### La risposta a `D4`, consigliata

Passare le tre righe da tastiera al generatore, come stringhe. Non contraddice l'intuizione del
developer: quella riguarda la **regex**, e la regex se ne va comunque. Se invece si vuole tenere il
pezzo esplicito, allora `TRC03` va ridotto ai due buchi delle cifre e i quattro della tastiera vanno
dichiarati **aperti** nel piano — un buco scritto e' un debito, un buco non scritto e' una svista.

## 6. Report — la prova di `D2`: si puo' togliere la riflessione?

**Domanda**: `Application::runningInConsole()` memorizza il proprio esito. Basta impostare
`APP_RUNNING_IN_CONSOLE` **prima** che l'applicazione nasca, o serve la riflessione su
`isRunningInConsole`?

**Come l'ho provato**, il 2026-08-21: un test usa-e-getta — `tests/Feature/Audit/ProbeConsoleEnvTest.php`,
**creato e poi cancellato** — che imposta la variabile in `setUp()` prima di `parent::setUp()`, la
rimuove in `tearDown()`, e verifica due cose: che `runningInConsole()` risponda `false`, e che una
cancellazione di provider **scriva davvero** la riga di audit. La seconda e' quella che conta: la prima
da' sola non dimostrerebbe che il trait si accende.

**Esito: riuscita.** `1 passed (2 assertions)` — la variabile arriva in tempo **e** l'audit scrive.
Quindi la riflessione si puo' togliere: e' `TRC05`, e non e' piu' una scommessa.

Tre cose misurate attorno, che servono a chi scrivera' il punto:

| Domanda | Risposta |
|---|---|
| basta `putenv()`? | **si'**, da solo: `1 passed` |
| bastano `$_ENV` e `$_SERVER`? | **si'**, da soli: `1 passed` — quindi la forma non e' vincolata, e conviene impostarli tutti e tre per non dipendere da quale adattatore di `Env` e' attivo |
| la variabile sfugge ai test successivi? | **no**: suite intera con la sonda dentro, **97 passed (184 assertions)**; togliendo la sonda, **96 passed (182)** |

**Cosa questa prova non dimostra.** Ha coperto **un** caso — la cancellazione con audit — non i dodici
del file. `TRC05` deve farli passare tutti e dodici, e li' la verifica e' la suite. E resta valida la
via d'uscita di `D2`: se sui dodici qualcosa non regge, la riflessione torna e il rilievo si giustifica
in SonarQube come «bypass per un test», con il perche' scritto **nel test** — dove lo legge chi lo apre,
non nell'interfaccia di uno strumento.

**Esito dell'applicazione, lo stesso giorno** (`TRC05`, fatto): la strada ha tenuto, e ha portato con se'
due conseguenze che la sonda non poteva mostrare, perche' la sonda era un test nuovo e non undici
esistenti. **Prima**: la leva non e' piu' per-test ma per-classe, quindi il test che vuole il guardiano
**acceso** e' uscito in un file suo — `ConsoleGuardTest`. **Seconda**: un test asseriva «zero righe di
audit» sul **totale**, e funzionava solo perche' i dati di partenza si creavano con l'audit spento; ora
l'asserzione e' sul delta. Nessuna delle due e' un effetto collaterale da nascondere: sono il prezzo di
avere una condizione dichiarata una volta invece di una leva accesa e spenta a mano.
