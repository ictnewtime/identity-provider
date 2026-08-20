# Piano — il controllo sulle traduzioni, esteso al frontend

Sigla dichiarata dall'analisi: `TTC` — qui non si ridichiara.

Stato: **chiuso** (2026-08-19) — 4 punti fatti · Data: 2026-08-19 · Analisi: [analysis.md](./analysis.md), che questo piano
**cita e non ripete**. `F…` e `D…` puntano lì. V — `auto`: lo stabilisce un comando · `man`: lo legge
una persona.

**I primi due punti, e l'ordine è il contenuto**: `TTC01` rende la suite **rossa** perché il difetto
esiste, `TTC02` la rimette verde perché il difetto è corretto. Unirli in un commit solo farebbe sparire
la dimostrazione.

`TTC03` è un **terzo lavoro, di soggetto diverso** — la lingua degli identificatori, non quella dei
messaggi — messo qui dal developer perché entrambe le cose riguardano quale lingua si parla e dove. Non
dipende dai primi due e si può fare prima, dopo, o in mezzo.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TTC01 | **fatto** (2026-08-19) | Il test a parte che scansiona `resources/js/` per `trans("…")` e `$t("…")` — **329** chiavi — e le verifica in tutte le lingue di `LOCALES`. La scansione è diventata parametrica (cartelle, estensioni, espressione), così i due perimetri sono **due costanti dichiarate** e non due copie del codice. Il terzo test verifica ora che **entrambe** le scansioni trovino ancora qualcosa | `tests/Feature/TranslationKeysTest.php` | basso | auto | **nato rosso, come doveva**, nominando le due chiavi con `file:riga`: `admin.roles.restore.title` in `RoleTable.vue:383` e `admin.provider_user_roles.restore.prompt_user` in `RestoreProviderUserRolesDialog.vue:57`. E ha trovato **una terza chiave che nessuno cercava** — vedi `TTC04` |
| TTC02 | **fatto** (2026-08-19) — verde **dopo `TTC04`** | Le due traduzioni in `it` e `en`, coi testi ricavati dalle sorelle: «Ripristina Ruolo» / «Restore Role», e la domanda che finisce con «id: » senza punto interrogativo. Verificate a runtime una per una, in entrambe le lingue | `lang/it.json`, `lang/en.json` | basso | auto | le due chiavi ora **risolvono** in `it` e `en` — provato chiamando `__()`. `TTC01` **non** è diventato verde subito: aveva trovato una **terza** chiave, `primevue.first_day_of_week`, di meccanismo diverso — è diventato verde con `TTC04`. La verifica del punto («`TTC01` diventa verde») è soddisfatta **insieme a quello**, non da sola, e questa riga dice perché |
| TTC04 | **fatto** (2026-08-19) | **`VDF24`** — il primo giorno della settimana **esce dalle traduzioni**: una mappa `{ it: 1, en: 0 }` nel componente, con `getActiveLanguage()`. Scelta la strada più lunga fra le due: `"00"` in `en.json` avrebbe funzionato, ma **un file JSON non ammette commenti** e un `"00"` senza spiegazione sarebbe stato «pulito» dal primo che passa — riportando il difetto in silenzio. Un numero non è un testo: sta nel codice, dove la ragione si può scrivere accanto. Le due chiavi ora inutilizzate sono state rimosse da `it.json` e `en.json` | `resources/js/ui/LocalizedDatePicker.vue`, `lang/it.json`, `lang/en.json`, `tests/Feature/TranslationKeysTest.php` | basso | auto | `TTC01` è **verde**; suite intera **96 verdi**. E un test nuovo tiene ferma la **classe** del difetto, non il caso: `test_no_translation_value_is_falsy` legge i due file e vieta ogni valore falso — `"0"` e `""` — perché `Translator::get()` fa `$line ?: $key`. **Provato nei due versi**: rimettendo un valore `"0"` il test lo nomina |
| TTC03 | **fatto** (2026-08-19) | **Gli identificatori dei test in inglese**: 15 file, **82 nomi di test** e i metodi d'appoggio — `fuoriDallaConsole` → `outsideTheConsole`, `conteggi` → `counts`, `chiamaCon` → `callWith`, `conDatabase` → `withDatabase`, e così via. I **commenti restano in italiano**, che è la regola: la spiegazione è documento, il nome è codice. `TranslationKeysTest` era il modello | `tests/` — 15 file | basso | auto | **la suite passa con lo stesso numero di test di prima: 96**, che è la verifica che conta — rinominare non è riscrivere, e un metodo che perdesse il prefisso `test_` non verrebbe più eseguito senza che nessuno lo dica. Nessun nome italiano resta (i tre apparenti sono `per_page`, che è un parametro). **Un difetto mio, trovato e corretto**: la prima passata ha sostituito le parole anche **dentro i commenti** — «una sessione viva» era diventata «una sessionFor viva» — su 10 righe di 3 file; la seconda passata tocca la prosa solo per i nomi che iniziano con `test_` |

## Cosa questo piano non copre

- **`__($variabile)`**: le chiavi costruite a runtime non sono verificabili dall'esterno. Oggi nel
  frontend non ce n'è nessuna (`F5`) e nei sorgenti PHP nemmeno, ma nulla lo impedisce.
- **Le lingue oltre `it` e `en`** (`D2`): aggiungerne una renderebbe il controllo rosso su 393 chiavi in
  un colpo. È la cosa giusta e va saputa prima.
- **I due blade** (`F6`): non contengono chiamate di traduzione, e non entrano nel perimetro. Se un
  giorno ne conterranno, il perimetro va allargato — e questa riga è il posto dove leggerlo.
