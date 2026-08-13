# Analisi — rilievi di analisi statica su frontend e test E2E

**Identificatori**: `TSA` = task static-analysis-findings

Stato: da approvare · Data: 2026-08-12 · Tranche **v1** di 4 —
[v2](../../todo/20260812-static-analysis-findings-v2/analysis.md) · [v3](../20260812-static-analysis-findings-v3/analysis.md) · [v4](../20260812-static-analysis-findings-v4/analysis.md)

Questa tranche copre **frontend Vue e test E2E**. Le altre tre sono PHP e non condividono nessun
rilievo con questa: la sovrapposizione è su `D6` (quale strumento ha prodotto la lista) e su `TSA09`,
che porta nel repo la configurazione di lint **solo per JavaScript** — le tranche v2–v4 richiedono uno
strumento PHP che quel punto non installa.

## 1. Obiettivo

Chiudere cinque rilievi segnalati da uno strumento di analisi statica su `resources/js/` e `cypress/`,
distinguendo **ciò che è un difetto reale** da **ciò che è un falso positivo**, e lasciando scritto il
perché in entrambi i casi.

Perché adesso: uno dei cinque rilievi — la password in chiaro nei test — ha portato alla luce un
problema più grave dello stesso rilievo, e quello non può restare aperto (§ 2, `F6`).

## 2. Situazione attuale

I fatti, ognuno col suo `file:riga`. Nessuna ricostruzione a memoria.

| # | Fatto | Prova |
|---|---|---|
| F1 | Password letterale nei dati di test E2E, campo `password` dell'oggetto `newUser` | `cypress/e2e/user/crud-user.cy.js:11` |
| F2 | `parseFloat` senza `Number.` in un test di esempio | `cypress/e2e-bk/2-advanced-examples/assertions.cy.js:170` |
| F3 | `parseInt` senza `Number.` **e senza radice** | `resources/js/ui/LocalizedDatePicker.vue:13` |
| F4 | `autocomplete="new-password"` passato come **prop** a `<Password>`, con `pt` che commuta il `type` dell'input fra `text` e `password` | `resources/js/components/UserForm.vue:454` |
| F5 | Lo stesso campo, in un altro form, ha `autocomplete` **dentro `pt.pcInputText.root`** — due modi diversi per la stessa cosa | `resources/js/components/ProviderForm.vue:373-378` |
| F6 | `cypress.env.json` contiene credenziali reali (`adminUsername`/`adminPassword`, `nonAdminUsername`/`nonAdminPassword`) ed è **contemporaneamente** in `.gitignore` (ultima riga) **e tracciato da git** | `git ls-files cypress.env.json` → tracciato; `.gitignore:27`; ultimo commit che lo tocca: `50a59a7` |
| F7 | La pagina segnalata come priva di `<title>` ne ha **due** fonti: il guscio Blade e `@inertiaHead`, che rende il `<Head>` di Inertia | `resources/views/app.blade.php:7` e `:9`; `resources/js/Pages/Client/Unauthorized.vue:7` |
| F8 | Le chiavi di traduzione usate dai punti sopra **esistono** in entrambe le lingue | `lang/it.json:405`, `lang/en.json:403`; `lang/it.json:3`, `lang/en.json:3` |
| F9 | Nel repo **non c'è configurazione di lint JS**: nessun ESLint, nessun `sonar-project.properties`, nessuno script di lint in `package.json` | `ls -a` in radice; `package.json:5-16` |
| F10 | `cypress/e2e-bk/` contiene 20 file `.cy.js`: sono gli esempi che Cypress genera all'installazione, non test di questo prodotto | `find cypress/e2e-bk -name '*.cy.js' \| wc -l` |
| F11 | Il campo `password` di `UserForm.vue` non ha `autocomplete`: ce l'ha **solo** `password_confirmation` | unica occorrenza in `UserForm.vue` alla riga 454 |
| F12 | I test E2E oggi girano **solo in locale**: la pipeline non ha alcun job che li esegua, né alcun job di test | `.github/workflows/deploy-staging.yml`, `deploy-production.yml` — solo `sonar-scan` e `ansible-deploy` |
| F13 | `cypress.env.json` contiene le credenziali di **utenti che devono già esistere** nel database: il test di login le usa per accedere, non per crearle | `cypress/e2e/auth/login.cy.js:36-38` (`Cypress.env("adminUsername")`, `Cypress.env("adminPassword")`) |
| F14 | Il seeder crea l'utente `admin.admin` con una password **scritta nel codice**, in chiaro e versionata | `database/seeders/DatabaseSeeder.php:39` |
| F15 | La pipeline di deploy ha **già** un gestore di segreti: le variabili d'ambiente arrivano da Infisical | `ansible/builder/tasks/identity-provider.yml:3-8` (`infisical export … > .env`) |

### Dipendenze e breaking change

- **Nessun breaking change** per i punti da `TSA03` a `TSA06`: sono modifiche locali, senza contratto
  esposto e senza cambiamento di comportamento osservabile a parità di dati (§ 3 spiega l'unico caso
  in cui il comportamento cambia, ed è una correzione).
- **`TSA01` ha una dipendenza sul developer**: la rotazione delle credenziali e la riscrittura della
  storia git sono sue (R1, R2). L'agente prepara, non esegue.
- **`TSA02` dipende da `TSA01`**: spostare la password dei test in `cypress.env.json` mentre quel file
  è tracciato peggiorerebbe la situazione invece di migliorarla.
- `TSA07` introdurrebbe una **dipendenza di sviluppo nuova** (ESLint e plugin Vue): va motivata e
  approvata prima, non dopo.

## 3. Analisi

**`TSA01` — le credenziali in `cypress.env.json`.** È il rilievo che non era nella lista e conta più
di quelli che c'erano. Il file è ignorato *da ora in poi*, ma resta tracciato: `.gitignore` non
sgancia ciò che è già indicizzato, quindi ogni `git pull` continua a distribuirlo e la storia lo
conserva comunque. Alternative viste: (a) `git rm --cached` + `cypress.env.example.json` con i soli
nomi delle chiavi — sgancia il presente, la storia resta; (b) riscrittura della storia con
`filter-repo` — pulisce anche il passato, ma riscrive `master` e obbliga tutti a riclonare; (c) non
fare niente perché è un ambiente di staging. La (c) si scarta: sono credenziali di un utente
amministratore reale. La raccomandazione sceglie fra (a) e (b) nel § 5, ma **in ogni caso le
credenziali vanno ruotate**: quelle esposte restano esposte anche dopo la pulizia.

**`TSA02` — la password letterale nei test.** Presa da sola è il rilievo più debole dei cinque:
l'utente che quella costante crea nasce e muore dentro il test, e il valore serve a superare le regole
di complessità del form. Non è un segreto, è un dato di prova. Ma un letterale del genere si copia, e
la casella giusta esiste già — `cypress.env.json`, una volta che `TSA01` l'ha resa sicura. Scartata
l'idea di generarla a caso ad ogni esecuzione: un test che fallisce con una password diversa ogni
volta non è riproducibile.

**Le credenziali E2E vanno generate, non custodite — e questo cambia la forma di `TSA01`.** Finché i
test E2E girano solo in locale (`F12`), `cypress.env.json` è un file su una macchina. Quando entrano
in pipeline diventa un file che qualcuno deve **fornire** al processo di deploy, e a quel punto
esistono solo due strade: custodire delle credenziali da qualche parte, oppure **generarle a ogni
preparazione dell'ambiente**. La seconda è migliore per la ragione più semplice che ci sia: ciò che
non esiste prima dell'esecuzione non si può esporre. Non c'è un segreto da ruotare, da revocare o da
ritrovare in una storia git — è la differenza fra proteggere una credenziale e non averne una.

Ma la generazione casuale **da sola non funziona**, ed è il punto tecnico su cui il lavoro si gioca:
`F13` dice che quelle credenziali servono ad accedere a utenti che devono **già esistere**. Scrivere
una password casuale nel file e fermarsi lì produce un test di login che fallisce sempre. Quindi la
procedura ha due metà obbligatorie e inseparabili — genera i valori **e** li mette nel database — e la
seconda metà oggi non c'è: il seeder crea `admin.admin` con una password scritta nel codice (`F14`,
difetto `VDF08`), che è lo stesso difetto di `cypress.env.json` in un altro file.

Alternative viste per la pipeline: (a) generazione a ogni esecuzione, come in locale — nessun segreto
da custodire, ed è la stessa procedura documentata una volta sola; (b) credenziali E2E in **Infisical**,
che la pipeline già usa per il resto delle variabili (`F15`) — coerente con quello che c'è, ma
reintroduce un segreto di lunga durata per un utente che vive solo nei test; (c) segreti del repository
di CI, che è la (b) con un gestore in meno. La (a) si preferisce proprio perché in un ambiente di test
effimero non c'è motivo di avere un segreto persistente. Nota che (a) e (b) non si escludono: se un
giorno i test E2E dovessero girare contro un ambiente **non** effimero, la (b) diventa l'unica
possibile, perché lì gli utenti non si possono ricreare a ogni esecuzione.

Questo rende anche più forte `TSA03`: la password letterale dei dati di test non va spostata in
`cypress.env.json` come valore fisso, ma **generata dalla stessa procedura**. E il § 2 va corretto in
un punto — avevo scritto che generarla a caso rende il test non riproducibile: è vero per un valore
che cambia *dentro* l'esecuzione, non per uno generato **prima** e usato da tutti i test della stessa
esecuzione, che è il caso qui.

**Dove sta la procedura: in uno script, non nel documento.** I comandi vanno in
`scripts/prepare-e2e-credentials.sh`, accanto agli altri controlli del progetto; in
[`docs/SETUP.md`](/docs/SETUP.md) va **una riga** che lo invoca, subito dopo
`php artisan key:generate` e marcata obbligatoria — la chiave di Laravel e le credenziali E2E sono la
stessa categoria di cosa, si generano preparando l'ambiente e non si scrivono a mano.

La divisione non è estetica. Un blocco di venti righe dentro `SETUP.md` è una procedura che **si
legge e si copia a mano**: chi la esegue ne salta un pezzo, e il pezzo saltato non lo vede nessuno. Uno
script si esegue intero o fallisce, la pipeline può chiamare **lo stesso file** che chiama la persona
— che è l'unica garanzia che le due strade non divergano — e il documento resta corto abbastanza da
essere letto. Se la procedura vive nel documento, la pipeline finirà per averne una copia propria, e
da lì in poi saranno due.

**`TSA03` — `parseInt` sul primo giorno della settimana.** Qui il rilievo dello strumento è
cosmetico, ma il codice sotto ha **due difetti veri** che il rilievo non nomina. Primo: manca la
radice, e `parseInt` senza radice è la sorgente classica di sorprese. Secondo, ed è quello che conta:
`parseInt(trans("...") || 1)` mette il valore di ripiego **dentro** la chiamata, dove non protegge
niente. `trans()` di `laravel-vue-i18n` restituisce **la chiave stessa** quando la traduzione manca,
e una chiave non è mai una stringa vuota: il `|| 1` non scatta, `parseInt` riceve
`"primevue.first_day_of_week"` e produce `NaN`. Oggi non si vede perché la chiave esiste in entrambe
le lingue (F8) — è un difetto latente, che si sveglia alla prima lingua aggiunta senza quella riga.
La forma corretta mette il ripiego **fuori**: `Number.parseInt(…, 10) || 1`. Alternativa scartata:
lasciare il ripiego dov'è e limitarsi al prefisso `Number.` — chiude il rilievo e lascia il bug, che è
il modo peggiore di rispondere a un'analisi statica.

**`TSA04`/`TSA05` — l'`autocomplete` e il `type` che commuta.** I due form fanno la stessa cosa in due
modi (F4, F5), e la differenza spiega perché lo strumento segnala solo il primo: dentro `pt` l'attributo
finisce sull'input vero, come prop di `<Password>` finisce dove PrimeVue decide, e l'analizzatore vede
un `autocomplete` che non è su un elemento di modulo. **Questa è la parte da verificare nel DOM reale
prima di toccare il codice**: non ho ispezionato il rendering, e l'ipotesi vale quanto una verifica
mancata. Resta comunque vero, indipendentemente dal rilievo, che il pulsante «mostra password»
commuta il `type` a `text`: mentre è visibile l'input è un campo di testo che dichiara
`new-password`, combinazione che i browser non trattano in modo uniforme. E resta la mancanza di F11:
il campo principale non dichiara niente, quindi il browser è libero di offrire il salvataggio della
password dell'amministratore su un form che ne crea una per **un altro utente**. Quella è la parte
utile del rilievo, e nella lista non c'era.

**`TSA06` — il `<title>` mancante: falso positivo, provato.** F7 chiude la questione: il titolo c'è
staticamente nel guscio Blade e viene sostituito a runtime da `@inertiaHead`. Lo strumento analizza il
componente Vue in isolamento e non può vedere né il guscio né Inertia. Non si aggiunge un `<title>`
nel componente per compiacere l'analizzatore: sarebbe un secondo titolo, e il rilievo tornerebbe da
un'altra parte. Si annota e si sopprime alla fonte.

**`TSA07` — perché questi rilievi arrivano da fuori.** F9 dice che nel repo non c'è modo di
riprodurli: la lista è arrivata da uno strumento esterno, e senza configurazione versionata la
prossima lista sarà diversa a seconda di chi guarda. Metterlo nel repo è la sola cosa che rende
`TSA03`–`TSA06` non ripetibili fra sei mesi. È anche l'unico punto che aggiunge dipendenze, quindi è
l'unico che va deciso prima (§ 4).

**`TSA05` (il `parseFloat`) è in un file che forse non deve esistere.** F10: `cypress/e2e-bk/` sono i
20 file di esempio di Cypress. Correggere il lint dentro esempi generati è lavoro speso su codice che
nessuno esegue; l'alternativa è cancellare la cartella. Non lo decido io: `-bk` suggerisce che
qualcuno l'ha tenuta apposta.

## 4. Da decidere

> **Risposte del developer, 2026-08-12.** Otto domande su otto: il § 4 è chiuso. Le risposte stanno
> qui, dove erano le domande — non in coda — e i punti del piano sono stati aggiornati di conseguenza.

### Vincoli

- **D1** — `cypress.env.json` (`TSA01`): sganciarlo dall'indice, o pulire anche la storia?
  → **RISPOSTA: niente pulizia della storia.** Le credenziali erano **dummy, per il locale**. Restano
  da fare: un `cypress.env.example.json` senza password (fatto), e lo sgancio da git.
  **Conseguenza**: `VDF01` scende da *alto* a *basso* — non è più un difetto che consegna dati, ma un
  file che non dovrebbe essere tracciato. `BDB09` (riscrittura della storia) si chiude.
- **D2** — la rotazione delle credenziali esposte è un intervento fuori dal repo?
  → **RISPOSTA: la rotazione non serve.** In locale il file lo prepara chi sviluppa; in pipeline lo
  genera l'esecuzione stessa e serve solo ai test Cypress; in stage e produzione **non viene usato**.
  Non esiste un'identità di lunga durata da ruotare. `TSA02` si chiude come `scartato`.
- **D3** — `cypress/e2e-bk/`: si cancella o si tiene?
  → **RISPOSTA: per ora si tiene.** `TSA07` (cancellare) diventa `scartato`, `TSA08` (correggere il
  `parseFloat`) si attiva. Con `D5` che esclude ESLint, l'esclusione della cartella dai rilievi va
  fatta lato SonarQube: `BDB17`.
- **D8** — utenti E2E nuovi o riuso di quelli attuali?
  → **RISPOSTA: nuovi**, coerente con `D2`: utenti dedicati, creati dalla procedura, senza identità
  persistente da proteggere. `cypress.env.example.json` porta `e2e.admin` ed `e2e.user`.

### Conflitti

- **D4** — `TSA04` e l'`autocomplete` sui campi password. **La domanda era posta male e questa è la
  versione corretta**, con i file e le righe.

  **Dove sta l'attributo, in tutto il progetto — due posti soli:**

  | File:riga | Campo | Forma |
  |---|---|---|
  | `resources/js/components/UserForm.vue:454` | `password_confirmation` | `autocomplete="new-password"` come **prop** di `<Password>` |
  | `resources/js/components/ProviderForm.vue:376` | `secret_key` | `autocomplete: 'new-password'` **dentro `pt.pcInputText.root`** |

  **Dove non sta**: `UserForm.vue:312` (il campo `password`, cioè quello principale — `F11`),
  `Login.vue:100`, `ResetPassword.vue:73` e `:144`, `ForcePasswordChange.vue:78`, `:115`, `:184`.
  Sette componenti `<Password>` su nove non hanno niente: **l'assenza è già la norma del progetto**, e
  le due righe qui sopra sono le eccezioni.

  **Cosa fa il pulsante «mostra password»**: `togglePasswordVisibility` (`UserForm.vue:73`) commuta
  `formItems.password.visible`, e il `pt` riscrive il `type` dell'input fra `password` e `text`
  (`UserForm.vue:324` e `:461`, `ProviderForm.vue:375`). **Non c'è conflitto con l'`autocomplete`**:
  sono due attributi indipendenti sullo stesso input. Toglierne uno non tocca il pulsante — la
  domanda originale suggeriva un'alternativa che non esiste.

  → **PRIMA RISPOSTA: la password non deve avere `autocomplete`.** Allinea le due eccezioni ai sette
  componenti che già non ce l'hanno.

  → **RISPOSTA DEFINITIVA, stessa giornata: l'attributo resta.** Il developer ha accolto la riserva
  qui sotto e l'ha estesa anche a `UserForm.vue:454`: `new-password` distingue i campi della password
  **nuova** da quello della password **attuale** in un form che ne ha più di uno, ed è ciò che impedisce
  al browser di precompilare la credenziale salvata dove va scritta una password diversa. Toglierlo
  sarebbe una regressione vera. `TSA05` e `TSA05b` sono entrambi **scartati**, e la ragione è scritta
  dove chi tocca quel codice la trova: [doc-code-guide-line.md](/docs/doc-code-guide-line.md). Il
  rilievo di SonarQube resta, ed è un falso positivo da sopprimere alla fonte come `TSA06`.

  **Una riserva, e la lascio scritta perché la decisione sia informata**: togliere l'attributo dà
  *coerenza*, non *soppressione*. `autocomplete="new-password"` è il modo documentato per dire al
  browser «non riempire questo campo con una credenziale salvata»; senza attributo il browser applica
  le proprie euristiche, che sul form di creazione di un altro utente possono comunque proporre il
  salvataggio (`VDF04`). E su `ProviderForm.vue:376` il campo **non è una password**: è la
  `secret_key` di un provider, resa con `<Password>` solo per mascherarla — lì l'attributo serviva
  probabilmente a impedire che il browser ci scrivesse dentro la password dell'amministratore.
  Toglierlo lì può essere una regressione vera, ed è per questo che nel piano è un punto separato
  (`TSA05b`) invece di essere accorpato.
- **D5** — ESLint come dipendenza di sviluppo: giustificato?
  → **RISPOSTA: niente ESLint per ora.** `TSA09` si chiude come `scartato`. I rilievi restano
  governati da SonarQube in pipeline (`D6`), e in locale non si riproducono: resta `BDB02`.

### Ignoto

- **D6** — quale strumento ha prodotto i rilievi?
  → **RISPOSTA: SonarQube.** Confermato anche dai workflow: `-Dsonar.qualitygate.wait=true` in
  `.github/workflows/deploy-staging.yml:38`. Quindi `TSA06` (il falso positivo sul `<title>`) si
  chiude con un `NOSONAR` motivato o un'esclusione nel progetto Sonar, non nel codice Vue. Scioglie
  `BDB01`.
- **D7** — dove finisce nel DOM l'`autocomplete` passato come prop a `<Password>`?
  → **RISPOSTA: con PrimeVue probabilmente non è corretto metterlo sul componente `<Password>`.**
  Coincide con quanto si legge dal codice: `ProviderForm.vue:376` lo mette dentro
  `pt.pcInputText.root`, cioè lo instrada esplicitamente sull'input interno, mentre
  `UserForm.vue:454` lo passa come prop — ed è l'unica delle due che SonarQube segnala. Con `D4` che
  decide di toglierlo, la verifica nel DOM **non serve più** per `TSA05`: si rimuove e basta. Resterà
  da farla solo se un giorno si decidesse di rimetterlo.

## 5. Consigli

Le raccomandazioni **precedono** le risposte del § 4 e restano come sono: dove il developer ha deciso
diversamente, vale la sua risposta, e la riga qui sotto dice cosa avevo consigliato e perché — serve a
chi rilegge la decisione fra sei mesi, non a rimetterla in discussione.

| Domanda | Raccomandazione | Esito |
|---|---|---|
| **D1** | `git rm --cached` **subito**, con un `cypress.env.example.json` che porta le sole chiavi. La riscrittura della storia solo se il repo è accessibile fuori dal team: costa a tutti e non sostituisce comunque la rotazione. | **accolta in parte**: sganciare sì, storia no — le credenziali erano dummy |
| **D2** | Sì, fuori dal repo, e **prima** del resto: finché quelle credenziali sono valide, tutto il lavoro sui file non cambia niente. | **superata**: non c'è un'identità di lunga durata da ruotare |
| **D3** | Cancellare `cypress/e2e-bk/`. Sono esempi generati (F10): 20 file che nessuno esegue producono rilievi che qualcuno legge. Se servono come riferimento, stanno nella documentazione di Cypress. | **non accolta**: si tiene, e si esclude lato Sonar |
| **D4** | Tenere il pulsante. La correzione utile non è togliere la commutazione, è dichiarare `autocomplete` **su tutti e due** i campi (F11) e nella stessa forma nei due form (F5). | **accolta a valle di un ripensamento**: la prima risposta toglieva l'attributo, la definitiva lo tiene. Resta non fatta solo la metà additiva — dichiararlo anche su `UserForm.vue:312` — che è il difetto `VDF04`, ancora aperto |
| **D5** | Sì, ma **minimo**: ESLint con il plugin Vue e le sole regole che coprono questi rilievi. Un lint che al primo giro segnala trecento cose si disattiva alla settimana successiva. | **non accolta**: niente ESLint; resta `BDB02` (nessuna riproduzione locale dei rilievi) |
| **D6** | Se è SonarQube, `TSA06` si chiude con un `NOSONAR` motivato o un'esclusione nel progetto Sonar, non nel codice Vue. | **confermata**: è SonarQube, e il quality gate **ferma già oggi un rilascio** (`.github/workflows/deploy-staging.yml:38`) |
| **D7** | Verificarlo per primo, ispezionando l'input renderizzato nel browser. | **decaduta**: con `D4` che toglie l'attributo, non c'è più niente da ispezionare |
| **D8** | **Utenti nuovi e dedicati**, `e2e.admin` e `e2e.user`. Riusare persone reali mescola la loro traccia di audit con quella dei test, e su un sistema che tiene un registro di audit è un prezzo che non vale il minuto risparmiato. | **accolta** |

Il piano: [action-plan.md](./action-plan.md).
