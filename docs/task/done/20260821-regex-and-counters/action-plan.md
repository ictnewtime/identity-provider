# Piano — regex e contatori

Sigla `TRC`. L'analisi e' in [analysis.md](./analysis.md) e questo piano **la cita, non la ripete**: i
sette rilievi, i sei buchi misurati nelle liste e le due strade per le sequenze stanno la'.

**Priorita' bassa**: sette rilievi di leggibilita' e igiene dei test, nessuno rompe qualcosa che
funziona. Ma `TRC03` porta con se' un difetto vero — un controllo che guarda meta' di cio' che dice di
guardare — e quello vale piu' del rilievo che lo ha fatto trovare.

**Ordine e stato delle decisioni** (2026-08-21): `D1` risposta — si generano; `D2` risposta e **prova gia'
fatta**, riuscita (§ 6 dell'analisi); `D3` risposta — strada (b). **Resta `D4`**, nata da una contraddizione
fra due risposte: le righe da tastiera si generano anche loro o restano esplicite? Ne dipendono `TRC03` e
`TRC08`. `TRC01`, `TRC02`, `TRC04`, `TRC05` e `TRC07` non dipendono da niente.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TRC01 | **fatto** (2026-08-21) | `[0-9]` → `\d` in `CHARACTER_FAMILIES`. In JavaScript senza il flag `u` sono la stessa classe; `[^A-Za-z0-9]` resta com'era, perché là `\d` non accorcerebbe niente | `resources/js/Composables/usePassword.js` | basso | auto | incluso nel confronto prima/dopo di `TRC02`: **nessuna** password cambia punteggio per questa modifica |
| TRC02 | **fatto** (2026-08-21) | **`D1`, strada (b): le sequenze si ricavano.** `alphabetFromCodes(97, 26)` e `alphabetFromCodes(48, 10)` costruiscono lettere e cifre dai codici — l'algoritmo indicato dal developer — e una funzione scorre le finestre di quattro caratteri **nei due versi** cercando `includes()`. **Le due regex lunghe sono sparite**: restano solo le due che sono *forme* e non elenchi (caratteri ripetuti, blocchi ripetuti), quindi il rilievo sulla complessità cade per costruzione | `resources/js/Composables/usePassword.js` | medio — la barra diventa più severa | auto | confronto prima/dopo su **20045 password distinte** (generatore `mulberry32`, deterministico): **3** diventano più severe, **0** più permissive. Il primo tentativo di confronto era da buttare — il generatore sfondava gli interi sicuri di JavaScript e ripeteva le stesse password: il numero era 20045 *confronti* ma poche password distinte |
| TRC03 | **fatto** (2026-08-21) | **I buchi, e sono molti più di sei.** Contando anche il verso all'indietro — che è come li conta il codice nuovo — le finestre non riconosciute erano **51 su 94**: tutto l'alfabeto all'indietro (`zyxw`…`dcba`), le due cifre (`0123`, `3210`), e le righe di tastiera nei due versi. Le sei che avevo contato prima erano le sole in avanti | `resources/js/Composables/usePassword.js` | basso | auto | uno script genera **tutte** le finestre dei cinque insiemi nei due versi (94) e le annega in una password per il resto robusta: se il punteggio resta sopra 2 la finestra non è riconosciuta. **Prima: 51 non riconosciute. Dopo: 0** |
| TRC04 | **fatto** (2026-08-21) | I tre `foreach (range(1, N) as $i)` diventano `audits(int $howMany, array $overrides = [])` con un `for` interno, e i tre numeri diventano costanti: `ROWS_FOR_A_SECOND_PAGE`, `EXTRA_ROWS_FOR_THE_N_PLUS_ONE_CHECK`, `ROWS_ABOVE_THE_CEILING`. È la proposta del developer — contatore usato e costanti per il massimo — col contatore chiuso nel metodo, così nei test non si vede affatto | `tests/Feature/AuditListTest.php` | basso | auto | `grep -c "as \$i"` → **0**, `php -l` pulito, suite **96 passed**: i tre test coinvolti asseriscono sui conteggi, quindi un numero sbagliato li farebbe cadere |
| TRC05 | **fatto** (2026-08-21) | **`D2` — la riflessione non c'è più.** `APP_RUNNING_IN_CONSOLE` impostata in `setUp()` **prima** di `parent::setUp()`, dove l'applicazione nasce; rimessa come si è trovata in `tearDown()`. Via `ReflectionProperty` e via le **undici** chiamate a `outsideTheConsole()`, che non servono più: la leva vale per la classe. Ma un test voleva il guardiano **acceso** — «in console non scrive niente» — e due condizioni opposte non stanno in un file dove la variabile si decide una volta prima di `setUp()`: quel test è ora `tests/Feature/Audit/ConsoleGuardTest.php`, e il suo docblock dice **perché** sta da solo. Tutte e tre le forme (`putenv`, `$_ENV`, `$_SERVER`) perché ognuna da sola basta, e insieme non dipendono dall'adattatore di `Env` attivo | `tests/Feature/Audit/CustomAuditableTest.php`, nuovo `tests/Feature/Audit/ConsoleGuardTest.php` | basso | auto | **un test è caduto, e la sua caduta è informazione**: `a session touched only in service fields writes nothing` asseriva `assertSame([])` sul **totale** delle righe, e funzionava perché i dati di partenza si creavano con l'audit ancora spento. Ora anche le creazioni si auditano, quindi l'asserzione è sul **delta**: l'`update` sui campi di servizio non aggiunge nessuna riga — che è ciò che il test ha sempre voluto dire. Suite **96 passed (182 assertions)**. E la non-fuga della variabile è provata **nell'ordine che conta**: la suite intera non lo prova, perché in ordine alfabetico `ConsoleGuardTest` gira prima; lanciando `CustomAuditableTest` **e poi** `ConsoleGuardTest`, **12 passed** — il guardiano è tornato acceso da sé |
| TRC07 | **fatto** (2026-08-21) | Le tre parole ovvie in un elenco loro — `OBVIOUS_WORDS` e un `includes()` sul testo in minuscolo, che è quel che faceva il flag `i`. Non sono sequenze e nessun algoritmo le genera: è il posto dove aggiungerne una quarta senza allungare un'espressione regolare | `resources/js/Composables/usePassword.js` | basso | auto | controprova nel confronto: `Password1!`, `myADMINpanel9` e `Login-2026` restano al tetto **2**, e una password senza parole ovvie prende **5** |
| TRC08 | **scartato** (2026-08-21) — **la condizione di `D4` si è risolta nell'altro verso** | Il punto teneva le righe da tastiera in una regex esplicita. La risposta del developer era condizionale: «se `qwer|wert|erty` si possono generare allora generale, altrimenti raggruppale in un controllo esplicito». **Si possono**: al generatore non serve un ordine numerico, serve una stringa ordinata — e `"qwertyuiop"` lo è quanto `"abcdefghij"`. Quindi le tre righe sono entrate in `SEQUENCE_SOURCES` di `TRC02`, con **un solo** meccanismo da mantenere invece di due, e le loro finestre non le scrive più nessuno | nessuno | — | — | il punto non si esegue: la sua verifica è quella di `TRC03`, 51 → 0 |
| TRC06 | **chiuso dal developer** (2026-08-21) | La conferma dal report: i sette rilievi non compaiono più. **Il report l'ha guardato il developer**, non l'agente — qui non c'è una misura mia, e va detto perché è l'unico punto del lotto senza un comando che lo dimostri. La riflessione non compare né come rilievo né come hotspot da giustificare: `TRC05` l'ha tolta, quindi la via d'uscita di `D2` non è servita | nessuno (verifica) | basso | man | il developer ha chiuso il punto dopo aver letto il report |

## Cosa questo piano non copre

- **Un esecutore di test JavaScript**: in questo repository non c'è né `vitest` né `jest`, quindi
  `TRC01`, `TRC02` e `TRC03` si verificano con script usa-e-getta come si fece per `TFC01`. Il
  confronto prima/dopo è una misura vera, ma **non resta** nel repository: il giorno che qualcuno
  cambia una riga di `usePassword.js` non c'è niente che glielo dica. È il difetto di fondo, e non è di
  questo lotto.
- **La soglia della barra di robustezza**: `TRC02` rende `isPredictable()` più severo, non cambia i
  punteggi né dove sta il confine fra «media» e «forte». Se dopo il cambio la barra sembrasse troppo
  pessimista, la leva è quella e va decisa a parte.

## Perf/leak — la dichiarazione della policy per i punti chiusi

Policy dell'organizzazione, voce per voce. I punti chiusi hanno toccato **un composable del frontend**
(`usePassword.js`, che gira nel browser) e **tre file di test**. Nessun service, nessuna API Resource,
nessuna rotta, nessuna query nuova.

| Voce | Esito | Perché |
|---|---|---|
| Query N+1 | non applicabile | nessuna query aggiunta. `usePassword.js` non parla con il server: calcola in memoria. L'asserzione riscritta in `TRC05` legge `audits` **due volte** invece di una, in un test, su una tabella che quel test riempie con meno di dieci righe |
| Data leakage | non applicabile | non passa da nessuna API. Va però detto che la classe ora **audita anche i dati di partenza** dei suoi test, quindi le righe di `audits` in memoria sono più di prima: sono dati di prova, e il database è sqlite in memoria che muore col test |
| Scope/tenant | non applicabile | niente di ciò che è stato toccato interroga o filtra per utente o provider |
| Memory/streaming | **verificato** | `hasSequence()` non costruisce elenchi: scorre le finestre con un indice e si ferma alla prima che combacia. I cinque insiemi sono cinque stringhe corte, costruite una volta al caricamento del modulo — non per password |
| Query non vincolate | non applicabile | `DB::table("audits")->orderBy("id")->get()` è senza limite, **ed era già così**: è un test su una tabella che il test stesso riempie con poche righe |

