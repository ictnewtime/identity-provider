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
| TRC01 | da approvare | `[0-9]` → `\d` in `CHARACTER_FAMILIES` (`resources/js/Composables/usePassword.js:17`). In JavaScript senza il flag `u` i due sono la **stessa** classe: nessun cambio di comportamento, e la classe negata `[^A-Za-z0-9]` resta com'è perché là `\d` non accorcerebbe niente | `resources/js/Composables/usePassword.js` | basso | auto | uno script usa-e-getta confronta `passwordStrength()` **prima e dopo** su tutte le password generate: gli esiti devono essere **identici**, zero differenze. È il metodo con cui si chiuse `TFC01`, 8270 confronti |
| TRC02 | da approvare | **`D1`, strada (b) — le sequenze si generano.** L'algoritmo è quello indicato dal developer: si parte da un codice di carattere (`A` è 65, `a` è **97**) e si scorre; per le cifre si va avanti di tre da `1` e, arrivati a `9`, all'indietro. Una funzione produce le finestre di quattro caratteri **nei due versi** e cerca `includes()`. Le due regex lunghe **spariscono**: il rilievo sulla complessità cade per costruzione, non per soglia | `resources/js/Composables/usePassword.js` | **medio — cambia cosa l'utente vede**: la barra diventa più severa | auto | confronto prima/dopo su tutte le password generate: le differenze **devono esserci** e solo nel verso giusto — password che diventano «debole», nessuna che diventa più forte. Ogni differenza si elenca con la password che la causa |
| TRC03 | da approvare — **dipende da `D4`** | **I sei buchi misurati.** Le due cifre — `0123` e `3210` — le chiude `TRC02` da sé. **I quattro della tastiera no**: `rtyu`, `tyui`, `yuio`, `uiop` stanno nel pezzo che il developer vuole lasciare esplicito, e con quella scelta questo punto non arriva a zero. È la contraddizione aperta come `D4` nell'analisi | `resources/js/Composables/usePassword.js` | basso | auto | uno script genera **tutte** le finestre dei cinque alfabeti, nei due versi, e verifica che `isPredictable()` le riconosca: adesso ne manca **6**. Dopo, **0** se `D4` dice di generare anche le righe; **4** se restano esplicite — e allora quei quattro vanno dichiarati aperti qui, perché un buco scritto è un debito e un buco non scritto è una svista |
| TRC04 | da approvare | I tre `foreach (range(1, N) as $i)` di `tests/Feature/AuditListTest.php:66,185,215` diventano un metodo d'appoggio `audits(int $quante, array $attributi = [])` con un `for`, e i tre numeri diventano **costanti con un nome** — 5, 9 e 12 in mezzo a un test non dicono cosa sono. È la proposta del developer (un contatore che si usa, e costanti per il massimo) con una piega in più: la variabile sparisce del tutto invece di essere usata per finta | `tests/Feature/AuditListTest.php` | basso | auto | `grep -c "as \$i" tests/Feature/AuditListTest.php` → **0**, e la suite resta **96 passed**: i tre test coinvolti — `test_it_honours_per_page`, l'N+1 e `test_per_page_has_a_ceiling` — asseriscono sui conteggi, quindi un errore nel numero di righe li fa cadere |
| TRC05 | **fatto** (2026-08-21) | **`D2` — la riflessione non c'è più.** `APP_RUNNING_IN_CONSOLE` impostata in `setUp()` **prima** di `parent::setUp()`, dove l'applicazione nasce; rimessa come si è trovata in `tearDown()`. Via `ReflectionProperty` e via le **undici** chiamate a `outsideTheConsole()`, che non servono più: la leva vale per la classe. Ma un test voleva il guardiano **acceso** — «in console non scrive niente» — e due condizioni opposte non stanno in un file dove la variabile si decide una volta prima di `setUp()`: quel test è ora `tests/Feature/Audit/ConsoleGuardTest.php`, e il suo docblock dice **perché** sta da solo. Tutte e tre le forme (`putenv`, `$_ENV`, `$_SERVER`) perché ognuna da sola basta, e insieme non dipendono dall'adattatore di `Env` attivo | `tests/Feature/Audit/CustomAuditableTest.php`, nuovo `tests/Feature/Audit/ConsoleGuardTest.php` | basso | auto | **un test è caduto, e la sua caduta è informazione**: `a session touched only in service fields writes nothing` asseriva `assertSame([])` sul **totale** delle righe, e funzionava perché i dati di partenza si creavano con l'audit ancora spento. Ora anche le creazioni si auditano, quindi l'asserzione è sul **delta**: l'`update` sui campi di servizio non aggiunge nessuna riga — che è ciò che il test ha sempre voluto dire. Suite **96 passed (182 assertions)**. E la non-fuga della variabile è provata **nell'ordine che conta**: la suite intera non lo prova, perché in ordine alfabetico `ConsoleGuardTest` gira prima; lanciando `CustomAuditableTest` **e poi** `ConsoleGuardTest`, **12 passed** — il guardiano è tornato acceso da sé |
| TRC07 | da approvare | **Le tre parole ovvie diventano un controllo a parte**, come chiede il developer: `pass`, `admin`, `login` non sono sequenze e non hanno niente a che fare col generatore. Un elenco di stringhe e un `includes()`, con il nome che dice cosa sono — e il posto giusto per aggiungerne una domani senza toccare un'espressione regolare | `resources/js/Composables/usePassword.js` | basso | auto | il confronto prima/dopo non mostra **nessuna** differenza per queste tre: `passw`, `Admin1` e simili restano prevedibili come prima |
| TRC08 | da approvare — **dipende da `D4`** | **Le righe da tastiera restano esplicite**, se `D4` conferma: `qwer|wert|erty|asdf|sdfg|dfgh|zxcv|xcvb` in una regex sua, separata dalle sequenze generate. Il developer ha detto che se SonarQube segnala **solo quel pezzo** lo accetta, e ha senso: una riga di tastiera non ha ordine alfabetico, quindi in una regex non si esprime con un intervallo. **Ma al generatore si passa come stringa** — vedi `D4`: se la risposta cambia, questo punto si scarta e le righe entrano in `TRC02` | `resources/js/Composables/usePassword.js` | basso | auto | dopo la separazione, la complessità di **quella** regex è sotto la soglia da sola (8 alternative), e il rilievo che resta è al massimo un hotspot da accettare |
| TRC06 | da approvare | La conferma dal report: i sette rilievi non compaiono più. Se `D2` porta a tenere la riflessione, quel rilievo resta **come hotspot rivisto** e non come rilievo chiuso: la differenza va scritta qui | nessuno (verifica) | basso | man | il report non elenca più i sette; quello sulla riflessione, se resta, è marcato rivisto con la sua motivazione |

## Cosa questo piano non copre

- **Un esecutore di test JavaScript**: in questo repository non c'è né `vitest` né `jest`, quindi
  `TRC01`, `TRC02` e `TRC03` si verificano con script usa-e-getta come si fece per `TFC01`. Il
  confronto prima/dopo è una misura vera, ma **non resta** nel repository: il giorno che qualcuno
  cambia una riga di `usePassword.js` non c'è niente che glielo dica. È il difetto di fondo, e non è di
  questo lotto.
- **La soglia della barra di robustezza**: `TRC02` rende `isPredictable()` più severo, non cambia i
  punteggi né dove sta il confine fra «media» e «forte». Se dopo il cambio la barra sembrasse troppo
  pessimista, la leva è quella e va decisa a parte.

## Perf/leak — la dichiarazione della policy per `TRC05`

Policy dell'organizzazione, voce per voce. `TRC05` ha toccato **due file di test** e nessun file di
prodotto: nessun service, nessuna API Resource, nessuna rotta.

| Voce | Esito | Perché |
|---|---|---|
| Query N+1 | non applicabile | nessuna query aggiunta. L'asserzione riscritta legge `audits` **due volte** invece di una, in un test: due `select` su una tabella che in quel test ha meno di dieci righe |
| Data leakage | non applicabile | non passa da nessuna API. Va però detto che la classe ora **audita anche i dati di partenza** dei suoi test, quindi le righe di `audits` in memoria sono più di prima: sono dati di prova, e il database è sqlite in memoria che muore col test |
| Scope/tenant | non applicabile | niente di ciò che è stato toccato interroga o filtra per utente o provider |
| Memory/streaming | non applicabile | un array di righe di test, già in memoria |
| Query non vincolate | non applicabile | `DB::table("audits")->orderBy("id")->get()` è senza limite, **ed era già così**: è un test su una tabella che il test stesso riempie con poche righe |

