# Piano — il controllo sulle traduzioni, esteso al frontend

Sigla dichiarata dall'analisi: `TTC` — qui non si ridichiara.

Stato: **da approvare** · Data: 2026-08-19 · Analisi: [analysis.md](./analysis.md), che questo piano
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
| TTC02 | **fatto** (2026-08-19) — **ma la suite resta rossa, e non per colpa sua** | Le due traduzioni in `it` e `en`, coi testi ricavati dalle sorelle: «Ripristina Ruolo» / «Restore Role», e la domanda che finisce con «id: » senza punto interrogativo. Verificate a runtime una per una, in entrambe le lingue | `lang/it.json`, `lang/en.json` | basso | auto | le due chiavi ora **risolvono** in `it` e `en` — provato chiamando `__()`. **Ma `TTC01` non è diventato verde**: ha trovato una **terza** chiave, `primevue.first_day_of_week`, che non c'entra con `VDF23` e ha un meccanismo diverso (`TTC04`). La verifica del punto era «`TTC01` diventa verde», e non è vera: è vera la sua metà, e questa riga dice quale |
| TTC04 | **fatto** (2026-08-19) | **`VDF24`** — il primo giorno della settimana **esce dalle traduzioni**: una mappa `{ it: 1, en: 0 }` nel componente, con `getActiveLanguage()`. Scelta la strada più lunga fra le due: `"00"` in `en.json` avrebbe funzionato, ma **un file JSON non ammette commenti** e un `"00"` senza spiegazione sarebbe stato «pulito» dal primo che passa — riportando il difetto in silenzio. Un numero non è un testo: sta nel codice, dove la ragione si può scrivere accanto. Le due chiavi ora inutilizzate sono state rimosse da `it.json` e `en.json` | `resources/js/ui/LocalizedDatePicker.vue`, `lang/it.json`, `lang/en.json`, `tests/Feature/TranslationKeysTest.php` | basso | auto | `TTC01` è **verde**; suite intera **96 verdi**. E un test nuovo tiene ferma la **classe** del difetto, non il caso: `test_no_translation_value_is_falsy` legge i due file e vieta ogni valore falso — `"0"` e `""` — perché `Translator::get()` fa `$line ?: $key`. **Provato nei due versi**: rimettendo un valore `"0"` il test lo nomina |
| TTC03 | da approvare | **Tutti gli identificatori dei test in inglese** — deciso dal developer il 2026-08-19, e portato qui da `BDB34`. `CLAUDE.md` fissa «identificatori in inglese, contenuto dei documenti in italiano», ma i test scritti finora hanno nomi in italiano. Misurato: **15 file, 82 nomi di test e 3 metodi d'appoggio**. `TranslationKeysTest` è già nella forma giusta e serve da modello: **nomi in inglese, commenti in italiano** — perché la spiegazione è documento, e il nome è codice. **Un commit per file**: sono nomi di metodo, il rischio è nullo e il rumore in revisione altissimo; mescolarli a una modifica di sostanza renderebbe illeggibile l'una e l'altra | `tests/` — 15 file | basso | auto | `grep -rE "function test_[a-z_]*(_la_\|_il_\|_una_\|_non_\|_senza_)" tests/` non trova più niente, e la suite passa con **lo stesso numero di test di prima**: rinominare non è riscrivere, e se il conteggio cambia qualcosa è andato perso |

## Cosa questo piano non copre

- **`__($variabile)`**: le chiavi costruite a runtime non sono verificabili dall'esterno. Oggi nel
  frontend non ce n'è nessuna (`F5`) e nei sorgenti PHP nemmeno, ma nulla lo impedisce.
- **Le lingue oltre `it` e `en`** (`D2`): aggiungerne una renderebbe il controllo rosso su 393 chiavi in
  un colpo. È la cosa giusta e va saputa prima.
- **I due blade** (`F6`): non contengono chiamate di traduzione, e non entrano nel perimetro. Se un
  giorno ne conterranno, il perimetro va allargato — e questa riga è il posto dove leggerlo.
