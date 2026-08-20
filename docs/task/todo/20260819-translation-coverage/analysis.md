# Analisi — il controllo sulle traduzioni guarda un sesto del prodotto

**Identificatori**: `TTC` = task translation-coverage

Stato: da approvare · Data: 2026-08-19

## 1. Obiettivo

Chiudere `VDF23` — due testi dell'interfaccia mostrano all'utente la **chiave** di traduzione invece del
testo — e chiudere insieme la ragione per cui nessuno se ne era accorto: `TranslationKeysTest` legge le
chiavi dei sorgenti **PHP** e non guarda il frontend, dove le chiavi sono **cinque volte tante**.

Perché adesso: le due chiavi mancanti stanno in due **dialoghi di ripristino**, e chi li apre legge una
stringa tecnica al posto di un titolo e di una domanda. È il difetto più visibile fra quelli aperti, e
si chiude con due righe di traduzione — ma se si chiudesse solo così tornerebbe, come è già tornato
`VDF20` fra il 14 e il 19 agosto.

## 2. Situazione attuale

| # | Fatto | Prova |
|---|---|---|
| F1 | **64 chiavi** passate a `__()` nei sorgenti PHP, **tutte tradotte** in `it` e `en`. **329 chiavi** nel frontend fra `trans("…")` e `$t("…")`, di cui **2 senza traduzione** | scansione eseguita il 2026-08-19 |
| F2 | `admin.roles.restore.title` è l'intestazione di un `Dialog`: `:header="$t('admin.roles.restore.title')"`. L'utente legge quella stringa **come titolo della finestra** | `resources/js/components/RoleTable.vue:383` |
| F3 | `admin.provider_user_roles.restore.prompt_user` sta prima dell'elenco degli id in grassetto e del `?`: al posto della domanda l'utente legge la chiave | `resources/js/components/provider-user-role/RestoreProviderUserRolesDialog.vue:57` |
| F4 | `TranslationKeysTest` scansiona `app/`, `routes/`, `database/` e **solo** `__()`. Il limite è dichiarato nel suo docblock — ma è un limite scritto, non un controllo | `tests/Feature/TranslationKeysTest.php`, costante `SOURCE_DIRECTORIES` |
| F5 | Nel frontend le chiamate hanno **due forme**: `trans("…")` nel codice e `$t("…")` nei template. Nessuna chiave è costruita dinamicamente — `grep` di `trans(\`` e `$t(variabile)` non trova niente — quindi **tutte e 329 sono verificabili** | `grep -rnE "(trans\|\\$t)\(\s*\`" resources/js/` → nessun risultato |
| F6 | I **blade** sono due — `app.blade.php` e `welcome.blade.php` — e non contengono chiamate di traduzione: il perimetro non deve comprenderli | `grep -rlE "@lang\(\|__\(" resources/views/` |
| F7 | I testi delle due chiavi **non vanno inventati**: le sorelle esistono per ogni altra entità — `admin.parameters.restore.title` «Ripristina Parametro», `admin.providers.restore.title` «Ripristina Provider», `admin.provider_user_roles.restore.title` «Ripristina Associazione»; e per la domanda, `admin.users.restore.prompt_users` «…ripristinare gli utenti selezionati con id: » | `lang/it.json`, `lang/en.json` |
| F8 | `VDF20` — cinque chiavi PHP mancanti, due visibili sul form di login — era stato chiuso il 14 e **due di quelle chiavi sono tornate rotte il 19** col revert. Le ha viste solo il controllo, e solo perché copriva quella metà | `VDF20` nel registro |

### Dipendenze e breaking change

- **Il controllo nuovo nasce rosso**: le due chiavi mancano oggi. È voluto, ed è l'ordine deciso dal
  developer — prima il punto che le rende rosse, poi quello che le rende verdi. Fra i due la suite è
  rossa, e chi committa in mezzo lo vede.
- **Nessun rischio sul comportamento**: si aggiungono due voci in due file di traduzione e un test.
  L'unico modo di sbagliare è scrivere un testo che non c'entra, e per questo `F7` conta.

## 3. Analisi

### Due test, non uno

La scansione del frontend va in un **test a parte** nello stesso file, e la ragione è la diagnosi: le
due scansioni cercano cose diverse — `__()` da un lato, `trans()` e `$t()` dall'altro — e con due test
il rosso dice **da quale lato** sta il problema senza leggere l'elenco. Con un test solo, un rosso
significa «una chiave da qualche parte».

Resta condiviso il terzo test, quello che verifica che le scansioni **trovino ancora qualcosa**: senza
di lui un'espressione regolare rotta renderebbe verdi gli altri due, che passerebbero guardando niente.

### I due testi, ricavati e non inventati

**Cosa intendo per «chiavi sorelle»** (`D1`): le chiavi di questo progetto sono costruite come
`admin.<entità>.<contesto>.<ruolo>`, e la stessa schermata esiste per più entità. Sorelle sono le chiavi
**identiche tranne l'entità in mezzo**: cambiando `roles` in `providers` o in `parameters` si ottiene una
chiave che esiste già ed è già tradotta. Il testo che manca non va inventato, va **ricavato per
sostituzione** — è la stessa frase con un'altra parola dentro.

Per la prima chiave, la famiglia `admin.*.restore.title` è al completo tranne una:

| Chiave | `it` |
|---|---|
| `admin.parameters.restore.title` | Ripristina Parametro |
| `admin.providers.restore.title` | Ripristina Provider |
| `admin.provider_user_roles.restore.title` | Ripristina Associazione |
| **`admin.roles.restore.title`** | **manca** → «Ripristina Ruolo» / *Restore Role* |

Per la seconda la sorella diretta non c'è — nessun'altra entità ha un `prompt_user` — e la più vicina è
`admin.users.restore.prompt_users`, «Sei sicuro di voler ripristinare gli utenti selezionati con id: ».
Da lì il testo: «Sei sicuro di voler ripristinare le associazioni con id: » / *Are you sure you want to
restore the associations with the following ids: *.

**E qui l'analogia da sola sbaglierebbe**, per questo `F3` conta: il componente stampa la chiave, poi gli
id in grassetto, poi il `?`. Quindi la frase finisce con «id: » e **senza** punto interrogativo. Copiando
la sorella di un'altra entità senza guardare **come** è usata si otterrebbe una frase con due punti
interrogativi, o con nessuno.

### Cosa resta fuori anche dopo

- `__($variabile)` nei sorgenti PHP: non verificabile dall'esterno. Oggi non ce ne sono, ma nulla lo
  impedisce.
- Le chiavi che il **backend** manda al frontend già tradotte: quelle passano da `__()` e sono coperte
  dalla scansione PHP.
- Le lingue oltre `it` e `en`: l'elenco è nella costante `LOCALES`, e chi ne aggiunge una lo aggiorna lì.

### Un terzo lavoro, di soggetto diverso

`TTC03` — gli identificatori dei test in inglese — non c'entra con le traduzioni dell'interfaccia: c'entra
con la **lingua del codice**. Il developer l'ha messo qui perché entrambe le cose rispondono alla domanda
«quale lingua, e dove», e `CLAUDE.md` ha già la risposta: identificatori in inglese, contenuto dei
documenti in italiano.

Misurato prima di scriverlo: **15 file, 82 nomi di test, 3 metodi d'appoggio** con parole italiane nel
nome. `TranslationKeysTest`, riscritto oggi, è la forma da imitare — nomi in inglese, commenti in
italiano — perché **la spiegazione è documento e il nome è codice**, e la regola vale su ciascuno per
quello che è.

La verifica di quel punto ha una parte che non si vede: la suite deve passare **con lo stesso numero di
test di prima**. Rinominare non è riscrivere, e se il conteggio cambia qualcosa è andato perso per
strada — un metodo che perde il prefisso `test_`, per esempio, non viene più eseguito e nessuno se ne
accorge.

## 4. Da decidere

### Vincoli

- Niente: l'ordine dei due punti l'ha deciso il developer — prima il test, poi le traduzioni.

### Conflitti

- ~~**`D1`**~~ — **chiarito il 2026-08-19 su richiesta del developer**: «chiavi sorelle» significa le
  chiavi identiche tranne l'entità in mezzo — `admin.<entità>.restore.title` — e il § 3 ora le elenca una
  per una, con la famiglia al completo tranne quella che manca. Resta da confermare che i due testi
  vadano bene: sono parole che l'utente legge.

### Ignoto

- ~~**`D2`**~~ — **risposta del 2026-08-19: nessun problema.** Aggiungere una lingua renderà il controllo
  rosso su 393 chiavi tutte insieme, e va bene così: è il conto esatto di quanto costa una lingua nuova,
  detto prima di cominciare invece che a metà.

## 5. Consigli

- **`D1` → usare i testi proposti**, e cambiarli senza remore: vengono dalle sorelle, non da me. Se
  suonano male è perché suonano male anche le sorelle — «Ripristina Parametro», «Ripristina Provider» —
  e allora è un altro lavoro, che riguarda tutta la famiglia e non questa chiave.
- ~~**`D2`**~~ — risposto: nessun problema. Resta scritto qui il numero — **393 chiavi** — perché è la
  cifra da mettere sul tavolo il giorno che si valuta una lingua nuova.
- **L'ordine dei due punti va rispettato anche nei commit**: il punto del test da solo lascia la suite
  rossa, e questa è la sua utilità — dimostra che il difetto esiste. Chi li unisce in un commit perde
  la dimostrazione.
