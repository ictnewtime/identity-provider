# Analisi — `ProviderUserRoleController`: messaggio ripetuto e costruttore vuoto

**Identificatori**: `TPU` = task provider-user-roles

Stato: da approvare · Data: 2026-08-12 · Tranche **v4** di 4 —
[v1](../../done/20260812-static-analysis-findings-v1/analysis.md) · [v2](../../todo/20260812-static-analysis-findings-v2/analysis.md) · [v3](../../done/20260812-static-analysis-findings-v3/analysis.md)

## 1. Obiettivo

Chiudere due rilievi su `ProviderUserRoleController` — il messaggio `"Provider user role not found"`
ripetuto tre volte e il costruttore vuoto — **dopo aver stabilito se sono casi isolati o istanze di
uno schema**. Sono istanze di uno schema, in entrambi i casi (§ 2), e questo cambia la correzione.

Perché adesso: cercando le tre occorrenze ne sono emerse **dodici dello stesso tipo** su tutto lo
strato dei controller, più due difetti che nessun rilievo nominava (`F5`, `F6`).

## 2. Situazione attuale

| # | Fatto | Prova |
|---|---|---|
| F1 | `"Provider user role not found"`, identico, in tre metodi diversi dello stesso file | `ProviderUserRoleController.php:221` (`find`), `:292` (`update`), `:333` (`delete`) |
| F2 | Lo **stesso schema** vale su tutto lo strato: `"Provider not found"` ×3, `"User not found"` ×3, `"Role not found"` ×2, più un `"Role id not found"` ×1 che è la stessa cosa scritta male | `grep -rhno 'message" => "[A-Za-z ]*not found"' app/Http/Controllers/` → 12 occorrenze, 5 messaggi distinti |
| F3 | Questi messaggi **non passano dalle traduzioni**: sono inglese scritto nel codice, mentre il progetto ha `lang/it.json` e `lang/en.json` e li usa altrove | `lang/it.json`, `lang/en.json`; nessun `__()` sulle righe di `F1` |
| F4 | Costruttore vuoto senza corpo né commento | `ProviderUserRoleController.php:19` |
| F5 | **`delete()` restituisce `204` con un corpo JSON.** Il 204 è «nessun contenuto»: il corpo che si è preso la briga di scrivere, molti client lo scartano | `ProviderUserRoleController.php:337` |
| F6 | Tre metodi dello stesso file recuperano la stessa entità in **tre modi diversi**: `withTrashed()->find()`, `where("id", $id)->first()`, `find()`. Quindi `find()` trova i cancellati logicamente, `update()` e `delete()` no — e nessuno lo dichiara | `:219` vs `:290` vs `:331` |
| F7 | Un **secondo** costruttore vuoto esiste nel progetto, in un service, e a differenza di `F4` ha un docblock sopra | `app/Services/AccountService.php:27` |
| F8 | Il controller base ha già `OA_DESC_MSG_NOT_FOUND = "Not found"`, ma è una descrizione **per la documentazione OpenAPI**, non il messaggio restituito al client | `app/Http/Controllers/Controller.php:39` |

### Dipendenze e breaking change

- **I messaggi sono un contratto di fatto**: se un client — o un test E2E — confronta la stringa
  `"Provider user role not found"`, tradurla la rompe. Va verificato prima (`D2`), non dopo.
- **`F5` è un breaking change se corretto**: togliere il corpo dal 204 cambia quello che il frontend
  riceve. Cambia in meglio, ma cambia.
- **`F7` sta in `app/Services/`**: toccarlo fa scattare la policy perf/leak dell'organizzazione, che
  su una modifica di service non ammette eccezioni. Per aggiungere una riga di commento è un costo
  sproporzionato — ed è la ragione per cui il punto è separato e ultimo.

## 3. Analisi

**Il rilievo sul messaggio duplicato è corretto e la sua correzione ovvia è insufficiente.** Una
`private const NOT_FOUND` nel file chiude il rilievo in un minuto e lascia in piedi `F2`: dodici
messaggi della stessa forma sparsi su cinque controller, ognuno col proprio literale. Peggio, li
rende **più difficili** da trovare dopo, perché la stringa non compare più nel codice. Le alternative
viste: (a) costante privata per file — chiude il segnale, non il problema; (b) costante condivisa nel
controller base — toglie la duplicazione ma congela l'inglese nel codice, contro `F3`; (c) chiavi di
traduzione, `__("errors.provider_user_role_not_found")`, che risolve duplicazione e lingua insieme;
(d) un helper `notFound($entity)` sul controller base, che restituisce la risposta 404 già formata e
lascia una sola riga per ogni caso. La (c) e la (d) si combinano, ed è quello che consiglio: l'helper
è il posto dove passare dalle traduzioni una volta sola.

Scartata la (b) da sola: sostituisce dodici literali con cinque costanti e lascia un'API che risponde
in inglese a un'interfaccia che parla italiano.

**Il costruttore vuoto è un rilievo che si chiude cancellando, non commentando.** Lo strumento offre
tre uscite — commentarlo, lanciare un'eccezione, completarlo — ma nessuna è quella giusta qui: un
costruttore vuoto senza parametri **non fa niente che PHP non faccia da sé**, e la quarta uscita è
toglierlo. `F7` mostra il caso in cui invece si commenta: nel service il docblock c'è già e dice a
cosa serviva. Se qualcuno lo ha lasciato come segnaposto per un'iniezione di dipendenze futura, è
un'intenzione che va scritta o cancellata, non lasciata come riga muta.

**`F5` — il 204 con il corpo.** Non era nella lista, ed è l'unica cosa di questa tranche che un
client vede sbagliata oggi: il metodo compone un messaggio `"Provider user role deleted"` e lo
spedisce con uno status che dichiara che non c'è contenuto. Chi legge il codice crede che il
messaggio arrivi. O si restituisce 200 con il corpo, o 204 senza: la seconda è più corretta per una
cancellazione, la prima è più compatibile con un frontend che oggi potrebbe già leggere quel campo.
Serve sapere quale (`D3`).

**`F6` — tre modi di cercare la stessa entità.** `find()` guarda anche i cancellati logicamente,
`update()` e `delete()` no. Può essere voluto — si consulta un record cancellato ma non lo si
modifica — e in quel caso è una **decisione che va scritta**, perché così com'è si legge come una
svista. Se non è voluto, è un difetto: `update()` su un record cancellato risponde «non trovato» e
`find()` sullo stesso risponde 200.

**Rapporto con le altre tranche.** `"Provider user role not found"` arrivava nella tua lista in mezzo
ai literali OpenAPI, ma non è un'annotazione: è un messaggio di runtime, l'utente lo legge, e la
correzione giusta passa dalle traduzioni — niente a che vedere con le costanti di
[v3](../../done/20260812-static-analysis-findings-v3/analysis.md). L'ho separato per questo. Il costruttore
vuoto è invece l'unico rilievo del lotto che si chiude **cancellando codice**.

## 4. Da decidere

> **Risposte del developer, 2026-08-13.** Sei su sei. `D2` ha prodotto due fatti che cambiano la
> forma del lavoro — `F9` e `F10` — e li scrivo qui perché senza di loro il § 3 resta più cupo di
> quanto la realtà giustifichi.

| # | Fatto emerso rispondendo | Prova |
|---|---|---|
| F9 | **Nessuno confronta i messaggi 404**: né il frontend, né Cypress, né un test. L'unico «not found» in `resources/js/` è un messaggio di console sul token CSRF | `grep -rn "not found" resources/js/ cypress/ tests/` |
| F10 | **Le chiavi di traduzione esistono già**, in entrambe le lingue: `provider_user_roles.not_found`, `provider.not_found`, `user.error.not_found`, `role.error.not_found` — dodici chiavi `*not_found*` in tutto. E in inglese `provider_user_roles.not_found` vale **esattamente** il literale scritto nel controller | `lang/it.json`, `lang/en.json` |

### Vincoli

- **D1** — solo `ProviderUserRoleController` o tutte e dodici le occorrenze? → **Tutte insieme.**
- **D2** — i messaggi 404 sono confrontati da qualcuno? → **No** (`F9`), quindi tradurli non è una
  regressione. Il developer ha chiesto di **aggiungere i test se mancavano**: fatto, `TPU01`.

### Conflitti

- **D3** — `delete()` a 200 con corpo o 204 senza? → **204 senza corpo.** Già fatto: `TPU04`.
- **D4** — il costruttore vuoto si cancella? → **Sì.**

### Ignoto

- **D5** — la differenza fra `withTrashed()` in lettura e la sua assenza in scrittura è voluta? →
  **Sì, è voluta**: la distinzione in scrittura non serve, per ora. Si consulta un record cancellato
  logicamente, non lo si modifica. Da **scrivere** dove sta il codice, perché così com'è si legge
  come una svista.
- **D6** — il costruttore vuoto in `AccountService` è stato segnalato? → **No, per ora.** Resta fuori
  da questo task.

### Cosa cambia, letto tutto insieme

`F10` ridimensiona il lavoro e insieme lo rende più fastidioso da guardare: le chiavi ci sono, sono
tradotte in due lingue, e **i controller le ignorano**. Non è un lavoro da progettare — è un lavoro
già fatto a metà e mai adottato. `TPU03` e `TPU06` passano da «creare un helper e le chiavi» a
«smettere di ignorare quelle che esistono».

## 5. Consigli

| Domanda | Raccomandazione | Esito |
|---|---|---|
| **D1** | Tutte e dodici, in un punto solo: l'helper condiviso costa quanto la costante privata. | **accolta** |
| **D2** | Un `grep` sui test E2E e sul frontend prima di toccare le stringhe. | **accolta ed estesa**: fatto il grep (`F9`) **e** aggiunti i test che mancavano |
| **D3** | 204 senza corpo. | **accolta** — già chiusa da `TPU04` |
| **D4** | Cancellarlo: un costruttore senza parametri né corpo non fa niente che PHP non faccia da sé. | **accolta** |
| **D5** | Serve la tua risposta; il mio sospetto è che sia una svista. | **sospetto sbagliato**: è voluta. Resta da scriverla |
| **D6** | Lasciarlo stare in questo task. | **accolta** |

Il piano: [action-plan.md](./action-plan.md).
