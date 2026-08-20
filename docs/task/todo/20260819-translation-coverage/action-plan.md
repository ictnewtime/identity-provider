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
| TTC01 | da approvare | **Il test che rende visibile `VDF23`**: un test **a parte** in `TranslationKeysTest` che scansiona `resources/js/` per `trans("…")` e `$t("…")` — **329** chiavi contro le 64 di oggi — e ne verifica la traduzione in tutte le lingue di `LOCALES`. A parte e non nello stesso test, per la diagnosi: due scansioni cercano cose diverse, e il rosso deve dire **da quale lato** (§ 3). Il terzo test, quello che verifica che le scansioni trovino ancora qualcosa, copre entrambe | `tests/Feature/TranslationKeysTest.php` | basso | auto | **rosso**, e nomina le due chiavi con `file:riga`: `admin.roles.restore.title` in `RoleTable.vue:383` e `admin.provider_user_roles.restore.prompt_user` in `RestoreProviderUserRolesDialog.vue:57`. Se nasce verde, la scansione non sta guardando `resources/js/` |
| TTC02 | da approvare | **Le due traduzioni**, in `it` e `en`, coi testi ricavati dalle sorelle e dall'uso nel componente (`F7`, `D1`): «Ripristina Ruolo» / «Restore Role», e la domanda che finisce con «id: » **senza** punto interrogativo, perché il `?` lo mette il template | `lang/it.json`, `lang/en.json` | basso | auto | `TTC01` diventa **verde senza essere modificato** — è l'unica prova che il test guardava la cosa giusta. Suite intera verde |
| TTC03 | da approvare | **Tutti gli identificatori dei test in inglese** — deciso dal developer il 2026-08-19, e portato qui da `BDB34`. `CLAUDE.md` fissa «identificatori in inglese, contenuto dei documenti in italiano», ma i test scritti finora hanno nomi in italiano. Misurato: **15 file, 82 nomi di test e 3 metodi d'appoggio**. `TranslationKeysTest` è già nella forma giusta e serve da modello: **nomi in inglese, commenti in italiano** — perché la spiegazione è documento, e il nome è codice. **Un commit per file**: sono nomi di metodo, il rischio è nullo e il rumore in revisione altissimo; mescolarli a una modifica di sostanza renderebbe illeggibile l'una e l'altra | `tests/` — 15 file | basso | auto | `grep -rE "function test_[a-z_]*(_la_\|_il_\|_una_\|_non_\|_senza_)" tests/` non trova più niente, e la suite passa con **lo stesso numero di test di prima**: rinominare non è riscrivere, e se il conteggio cambia qualcosa è andato perso |

## Cosa questo piano non copre

- **`__($variabile)`**: le chiavi costruite a runtime non sono verificabili dall'esterno. Oggi nel
  frontend non ce n'è nessuna (`F5`) e nei sorgenti PHP nemmeno, ma nulla lo impedisce.
- **Le lingue oltre `it` e `en`** (`D2`): aggiungerne una renderebbe il controllo rosso su 393 chiavi in
  un colpo. È la cosa giusta e va saputa prima.
- **I due blade** (`F6`): non contengono chiamate di traduzione, e non entrano nel perimetro. Se un
  giorno ne conterranno, il perimetro va allargato — e questa riga è il posto dove leggerlo.
