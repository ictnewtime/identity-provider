# Analisi — la complessità del trait di audit, e due rilievi minori

**Identificatori**: `TAC` = task audit-complexity

Stato: **chiuso** (2026-08-19) · Data: 2026-08-19

## 1. Obiettivo

Chiudere tre rilievi `high` di SonarQube che non hanno niente in comune fra loro, tranne la severità:

| Rilievo | Dove | Numero |
|---|---|---|
| Complessità cognitiva | `app/Traits/CustomAuditable.php`, `logAudit()` | **43**, soglia 25 |
| Complessità cognitiva | `resources/js/components/ParameterForm.vue`, `submit()` | **16**, soglia 15 |
| Credenziale sospetta | `tests/Feature/DatabaseSeederTest.php:25` | — |

Il primo è il lavoro vero: **95 righe** che decidono cosa finisce nella tabella degli audit, e girano a
ogni scrittura di **sei** modelli. Gli altri due sono piccoli e hanno già un precedente — il secondo è
identico ai due `submit` chiusi da `TFC05` oggi, il terzo è una costante in un test.

**La differenza rispetto a [frontend-complexity](../../done/20260819-frontend-complexity/action-plan.md),
chiuso stamattina**: là non c'era modo di eseguire il codice e i rifacimenti sono andati senza rete. Qui
il codice è PHP, **PHPUnit c'è**, e la rete si può stendere prima di toccare niente. Sarebbe la seconda
volta in una giornata che si rinuncia a una verifica che è disponibile.

## 2. Situazione attuale

| # | Fatto | Prova |
|---|---|---|
| F1 | `logAudit()` sono **95 righe** in un `try` unico, e fa **cinque** cose diverse: decide se saltare, traduce l'azione per i modelli con soft delete, ricava l'attore, ricava l'IP, scrive la riga | `app/Traits/CustomAuditable.php:29-124` |
| F2 | Gira su `created`, `updated` e `deleted` di **sei** modelli: `User`, `Provider`, `Role`, `Session`, `Parameter`, `ProviderUserRole` | `bootCustomAuditable()`; `grep -rln CustomAuditable app/Models/` |
| F3 | La parte più intricata è il riconoscimento dell'attore: `Auth::id()`, poi `provider_id` dagli attributi della richiesta, poi `oauth_client_id`, e se manca tutto **decodifica il bearer token a mano** per leggerne il claim `aud` | righe 73-88 |
| F4 | **C'è un `if` col corpo vuoto**: `if ($userType == Client::class) { } else { … }`. Vuole dire «per i client non cercare la sessione», ma lo dice al rovescio e lascia un ramo che non fa niente | riga ~99 |
| F5 | **Ogni audit interroga `sessions`**: `Session::where("user_id", …)->where("provider_id", …)->first()`, per ricavare l'IP. È la stessa query che `VDF15` dice essere **senza indice** — quindi il costo di quel difetto non è per richiesta autenticata: è **per ogni scrittura di ogni modello auditato** | riga ~100, e `VDF15` nel registro |
| F6 | Tutto il metodo è in un `try` che **inghiotte** qualunque errore: `catch (\Exception $e) { Log::error("CRASH AUDIT (ignorato): …") }`. È una scelta — l'audit non deve rompere l'applicazione — ma un audit che non viene scritto non si vede da nessuna parte tranne che nel log | righe 121-123 |
| F7 | **`logAudit()` non ha nessun test.** Esistono `AuditListTest`, `AuditSearchFilterTest`, `AuditSortOrderTest`: provano la **lettura** della lista, non la scrittura della riga | `grep -rln audit tests/` |
| F10 | **Sotto PHPUnit `logAudit()` non scrive niente, e questa è la ragione per cui `F7` è potuto accadere.** La prima riga del metodo è `if (app()->runningInConsole()) return;`, e in PHPUnit quel metodo restituisce **`true`** — misurato: dopo una `Provider::forceCreate()` la tabella `audits` ha **0 righe**. Il trait è **inerte in tutta la suite** | sonda eseguita il 2026-08-19: `runningInConsole: true`, `righe in audits: 0` |
| F11 | **La leva per provarlo esiste, ed è del framework**: `Application::runningInConsole()` memorizza `Env::get("APP_RUNNING_IN_CONSOLE") ?? (PHP_SAPI === "cli" …)` in una proprietà. Forzando quella proprietà a `false` in un test, l'audit **si scrive**: misurato, 1 riga con `event=created` e `auditable_type=App\Models\Provider` | `vendor/laravel/framework/.../Application.php:800-807`; seconda sonda del 2026-08-19 |
| F8 | `ParameterForm.submit()` ha la **stessa forma** dei due chiusi da `TFC05`: la catena di `if (backendErrors.X)` dentro il `catch` | `resources/js/components/ParameterForm.vue` |
| F9 | La costante segnalata nel test è una password **generata per il test e usata solo lì**: `setUp()` la mette in `SEED_ADMIN_PASSWORD`, il seeder la richiede non vuota e non ne controlla la forma. Nessun sistema reale la conosce | `DatabaseSeederTest.php:25,31-33`; `DatabaseSeeder.php:35-42` |

### Dipendenze e breaking change

- **Gli audit sono una funzione di conformità**: se il rifacimento sbagliasse l'azione — `deleted`
  invece di `restored`, o l'attore sbagliato — la lista continuerebbe a riempirsi e nessuno vedrebbe
  niente. È il difetto invisibile per eccellenza, e `F7` dice che oggi nulla lo intercetta.
- **`Session` è fra i modelli auditati** (`F2`), e `logAudit` interroga `sessions` (`F5`): scrivere una
  sessione produce un audit che legge le sessioni. Non è un ciclo — l'audit non scrive sessioni — ma
  raddoppia il peso di ogni login.
- **Il rilievo sul test non è un rischio di sicurezza**: nessun sistema reale conosce quel valore
  (`F9`). Resta che una stringa che *sembra* una credenziale in un sorgente è ciò che quella regola
  cerca, e la si può togliere senza perdere niente.

## 3. Analisi

### `logAudit`: cinque responsabilità, cinque nomi

Le 95 righe si leggono come una sequenza di blocchi già separati da commenti. Diventano metodi privati
con un nome, e il metodo pubblico resta la sequenza:

| Metodo | Cosa decide |
|---|---|
| `shouldSkipAudit($model, $action, $changedFields)` | console, e il rumore delle sessioni (`last_activity`, `updated_at`, `expires_at`) |
| `resolveAction($model, $originalAction, $changedFields)` | la traduzione per i modelli con soft delete: `deleted`, `restored`, `force_deleted` |
| `resolveActor()` | utente Laravel, oppure client Passport — compresa la lettura del claim `aud` dal bearer token |
| `resolveIpAddress($actor)` | l'IP della richiesta, o quello della sessione se l'attore è un utente |
| `auditRow(...)` | l'array da inserire |

La complessità non si sposta: si **divide**, e ogni pezzo diventa provabile da solo. `resolveAction` in
particolare è una tabella di casi che oggi vive in tre `elseif` annidati.

### Il guardiano della console, che è la vera ragione dell'assenza di test

`F10` cambia il punto di partenza: non è che nessuno **avesse scritto** i test di `logAudit` — è che
scriverli nel modo ovvio **non funziona**. Chi provasse «creo un modello e leggo `audits`» troverebbe
zero righe e concluderebbe che il trait non va, o che il test è sbagliato. Nessuna delle due.

E il guardiano dice anche un'altra cosa, che vale oltre i test: **nessuna operazione fatta da `artisan`
produce audit**. Un seeder, un comando di manutenzione, una modifica da `tinker` non lasciano traccia. È
probabilmente voluto — evitare il rumore delle migrazioni e dei seeder — ma non è scritto da nessuna
parte, e chi guarda la tabella degli audit per ricostruire una modifica non lo sa.

### La rete, che qui esiste

`logAudit` è statico e privato, ma **non serve chiamarlo**: si provoca l'evento. Serve però disattivare
il guardiano della console, e la leva è del framework (`F11`): la proprietà memorizzata
`isRunningInConsole` forzata a `false` nel test. **Provato: 1 riga, `event=created`.** Non è un trucco
sul codice di produzione — non lo si tocca — è la stessa leva che `APP_RUNNING_IN_CONSOLE` espone. Creare un `Role`,
aggiornarlo, cancellarlo, ripristinarlo, e leggere la riga in `audits` — è un test di caratterizzazione
che si scrive in mezz'ora e che vale per sempre. Va scritto **contro il codice attuale** e deve passare
prima di toccarlo: è lo stesso ordine di `TFC03`, che stamattina non si è potuto seguire.

Casi che meritano di stare nella tabella: creazione, aggiornamento normale, soft delete, ripristino,
`force_deleted`, aggiornamento di sola `last_activity` su una `Session` (**non** deve produrre niente),
attore utente, attore client Passport.

### I due rilievi minori

`ParameterForm.submit` si chiude con la forma già scelta ieri per i suoi due gemelli (`D2` di `TFC`):
un elenco di campi, un ciclo, una funzione nel file. Nessuna decisione nuova.

La costante del test si toglie **generandola**: `Str::password()` produce un valore valido a ogni
esecuzione, il seeder chiede solo che non sia vuoto (`F9`), e nel sorgente non resta nessuna stringa che
somigli a una credenziale. Non è aggirare la regola cambiando nome alla costante: è togliere il dato.

### Codice cancellato

Il ramo vuoto di `F4`. E la costante di `F9`.

## 4. Da decidere

### Vincoli

- ~~**`D1`**~~ — **risposta del 2026-08-19: sì, prima.** E cercando come farlo è venuto fuori `F10`: sotto
  PHPUnit il trait è **inerte**, quindi i test vanno scritti disattivando il guardiano della console
  (`F11`). Mezz'ora era la stima di prima di saperlo.
- **`D5`, nuova** — il guardiano della console significa che **nessuna operazione da `artisan` produce
  audit** (§ 3). È voluto? Se sì va scritto in `project-analysis.md`; se no, è un buco nella traccia che
  nessun rilievo nomina.
- ~~**`D3`**~~ — **risposta del 2026-08-19: né generarla né scartarla — la password va nell'ambiente.**
  Il nome della variabile entra in `.env.test.backend.example` **senza valore**; il valore lo mette il
  developer in `.env.test.backend`, che è ignorato da git. E poiché a quel punto la suite non parte più
  da un albero appena clonato, servono **due cose in più**: uno script che prepari il file e lanci i
  test, e un `docs/TEST.md` che dica per primo che basta quello script. È un lavoro che esce dal
  perimetro dei rilievi di SonarQube, e ha una sua analisi: **[Analisi 2](#analisi-2--le-credenziali-dei-test-e-il-comando-che-le-prepara)**.

### Conflitti

- ~~**`D2`**~~ — **risposta: si lascia qui, e si apre un task suo.** L'errore inghiottito non si
  corregge di slancio dentro un rifacimento di complessità: ha bisogno di una decisione propria — cosa
  deve accadere quando un audit non si scrive — e quella decisione vale anche per `VDF07`, che è la
  stessa abitudine sul deploy.

### Ignoto

- ~~**`D4`**~~ — **risposta: al momento non è prioritario.** È efficienza, e si valuta **dopo** i rilievi
  di SonarQube. La misura resta scritta in `VDF15`, così quando quel momento arriva non va rifatta.

## 5. Consigli

- **`D1` → sì, e prima** — risposto. Con un'aggiunta che cambia la stima: la rete c'è ma **va montata**,
  perché sotto PHPUnit il trait non scrive (`F10`). La leva è del framework e l'ho provata (`F11`), quindi
  la strada è aperta; il primo test costerà più dei successivi.
- **`D5` → scriverlo, qualunque sia la risposta.** Che `artisan` non produca audit è una scelta
  difendibile e oggi invisibile: sta nel codice come una riga di guardia, e chi legge la tabella degli
  audit non ha modo di sapere che esiste.
- ~~**`D3` → generarla**~~ — **il developer ha scelto l'ambiente, ed è più coerente col resto del
  repository**: le credenziali di questo progetto stanno già fuori dal codice — `SEED_ADMIN_PASSWORD` per
  il seeder (`VDF08`), `cypress.env.json` per gli E2E (`VDF01`). Una password generata dal test sarebbe
  stata una terza strada per la stessa cosa. Il prezzo è che la suite **smette di partire da sola su un
  albero appena clonato**, e va pagato con uno script e con un documento: [Analisi 2](#analisi-2--le-credenziali-dei-test-e-il-comando-che-le-prepara).
- **`D2` → lasciarlo qui e aprirgli un task**, che è la risposta data. Va con `VDF07`: la stessa
  abitudine sul deploy. Se si decide di cambiarlo, si cambia nei due posti con lo stesso criterio.
- ~~**`D4` → alza la priorità**~~ — **il developer ha deciso di rimandare**, e la ragione regge: la
  coppia `(user_id, provider_id)` ha comunque l'indice della chiave esterna, quindi non è una scansione.
  La misura è nella voce `VDF15`; **`D1` di questo task resta l'unica domanda senza risposta**.

---

# Analisi 2 — le credenziali dei test, e il comando che le prepara

Analisi **a sé**, nello stesso file perché nasce da `D3` di sopra, ma con un oggetto diverso: qui non si
parla di complessità cognitiva. Si parla di come si eseguono i test dopo che una credenziale è uscita dal
codice.

## 1. Obiettivo

Togliere dal sorgente la password di prova del seeder — è il rilievo `high` di
`tests/Feature/DatabaseSeederTest.php:25` — **senza che eseguire la suite diventi un rito da ricordare**.

Le due cose vanno insieme, e la seconda è quella che si dimentica. Oggi la suite parte con un comando su
un albero appena clonato:

```sh
docker build -f Dockerfile.test.backend -t idp-test-backend .
docker run --rm -v "$PWD":/var/www idp-test-backend
```

Se la password va nell'ambiente, quel `docker run` non basta più: manca un valore che nessun file
versionato può contenere. Alla fine deve essere vero che **un comando solo** rimette la suite in piedi, e
che chi non lo conosce lo trova scritto **per primo** in `docs/TEST.md`.

## 2. Situazione attuale

| # | Fatto | Prova |
|---|---|---|
| G1 | `.env.test.backend.example` esiste ed è **versionato**, con dentro solo valori innocui: connessione sqlite, elenco dei database consentiti, indirizzi IP e user agent di prova | `.env.test.backend.example` |
| G2 | `.env.test.backend` è in `.gitignore` (riga 9) — quindi è già previsto come file **locale e non versionato** | `.gitignore:9` |
| G3 | Ma **nessuno lo usa**: `Dockerfile.test.backend:39` copia dentro l'immagine il **modello**, non il file locale — `COPY .env.test.backend.example /opt/test/.env` | `Dockerfile.test.backend:39` |
| G4 | E il modello dice di copiarlo in un terzo nome: `cp .env.test.backend.example .env.testing`, che non è quello ignorato da git | intestazione di `.env.test.backend.example` |
| G5 | La password di prova oggi è una costante PHP, e serve a una cosa sola: `setUp()` la mette in `SEED_ADMIN_PASSWORD` (in tre posti: `putenv`, `$_ENV`, `$_SERVER`) perché il seeder la pretende non vuota | `DatabaseSeederTest.php:25,31-33` |
| G6 | Il seeder **non controlla la forma** della password: se è vuota si ferma con un messaggio che spiega cosa fare, altrimenti la usa | `DatabaseSeeder.php:35-44` |
| G7 | Le variabili dell'ambiente del processo **vincono** su `phpunit.xml` e sul file `.env` — verificato il 2026-08-12 e scritto nel modello. È il meccanismo su cui lo script si appoggia: passare `-e` a `docker run` è più forte di qualunque file | intestazione di `.env.test.backend.example` |

**Le tre incoerenze di `G3` e `G4` sono la parte interessante**: esiste un file locale previsto
(`.env.test.backend`), il Dockerfile ne copia un altro (il modello), e il modello suggerisce un terzo
nome (`.env.testing`). Finché tutti i valori erano innocui nessuno se ne accorgeva. Con una credenziale
in mezzo, la confusione diventa un errore: si mette il valore in un file che non viene letto.

## 3. Analisi

### Cosa fanno gli script — e perché sono **due**

**`scripts/setup-env-for-test-backend.sh`** prepara e basta: da `.env.test.backend.example` produce
`.env.test.backend`, e per ogni variabile **dichiarata senza valore** ne **genera** una — a meno che non
la trovi già, nell'ambiente del processo o nel file.

**Generata e non chiesta** (decisione del developer, 2026-08-19): sono credenziali di prova, servono a
esistere. Il seeder pretende una password e non ne inventa una (`G6`), ma *quale* sia non interessa a
nessuno. Chiederla avrebbe messo un uomo in mezzo a ogni ambiente nuovo, e avrebbe reso lo script
inutilizzabile dove un terminale non c'è — cioè in CI. Se il file è già completo non si genera niente:
due esecuzioni di fila lavorano sullo stesso ambiente.

**`scripts/run-test-backend.sh`** chiama il primo, poi **costruisce** l'immagine e **esegue** la suite
passando gli argomenti che riceve, così `./scripts/run-test-backend.sh --filter=Audit` funziona come il
`docker run` a mano.

Due e non uno perché servono a **due momenti diversi**: chi lancia i test a mano — con `docker build` e
`docker run`, per capire cosa succede — ha bisogno di preparare l'ambiente e **non** vuole che qualcuno
gli costruisca un'immagine per conto suo. Un solo script con tre passi obbligherebbe a quello.

### Come la password arriva dentro il container

Due strade, e la differenza conta:

| Strada | Come | Perché |
|---|---|---|
| **(a) `--env-file`** | `docker run --env-file .env.test.backend …` | una riga, e passa **tutto** il file. Ma sovrascrive anche le variabili che l'immagine ha già, e un file locale sbagliato cambia in silenzio il comportamento della suite |
| **(b) le sole variabili che mancano** | `docker run -e SEED_ADMIN_PASSWORD="…" …` | passa **solo** ciò che il modello dichiara vuoto. L'immagine resta la fonte di tutto il resto, e il file locale non può cambiare, per esempio, l'elenco dei database consentiti — che è il guardiano di `VDF11` |

**(b)**, e non per prudenza generica: `TEST_ALLOWED_DATABASES` è ciò che impedisce alla suite di
cancellare `idp_develop`. Un `--env-file` locale che lo sovrascriva per errore disarma quella guardia
senza che nulla lo segnali.

### Cosa cambia nel test

`DatabaseSeederTest` smette di avere una costante e legge `env("SEED_ADMIN_PASSWORD")`. E qui c'è un
punto da non sbagliare: se la variabile **manca**, il test non deve fallire con un errore oscuro dentro il
seeder — deve dire *«manca `SEED_ADMIN_PASSWORD`: eseguire `./scripts/run-test-backend.sh`»*. Un test che
salta con un motivo è utile; uno che esplode sposta il lavoro su chi legge lo stack trace.

### Cosa cambia in `docs/TEST.md`

Oggi il documento apre con la tabella delle due famiglie e poi dà i comandi a mano. Va rovesciato per la
parte di backend: **prima lo script**, che è ciò che serve nel 99% dei casi; poi, come seconda voce, la
via manuale — con detto chiaramente che va preparato `.env.test.backend` e che le variabili dichiarate
vuote vanno riempite. Chi legge un documento di test cerca il comando, non il meccanismo: il meccanismo
si legge se il comando non è bastato.

### Cosa questa analisi non risolve

Le incoerenze `G3`/`G4` restano da sciogliere quando lo script esiste: se il Dockerfile continua a
copiare il **modello** dentro l'immagine, la password non arriva per quella strada — arriva con `-e`. Va
scritto nel Dockerfile accanto al `COPY`, o al primo dubbio qualcuno metterà il valore nel modello
versionato. Che è esattamente il difetto da cui si è partiti.

## 4. Da decidere

- ~~**`G-D1`**~~ — **risposta del 2026-08-19: resta `.env.test.backend`**, il nome che `.gitignore`
  prevedeva già. Il modello e il Dockerfile si allineano a quello (`TAC09`).
- ~~**`G-D2`**~~ — **risposta: fallisce.** E scrivendo il punto è venuto fuori che «fallire» non è
  automatico: `G8` qui sotto.

### Un fatto trovato scrivendo la risposta a `G-D2`

| # | Fatto | Prova |
|---|---|---|
| G8 | **Senza la variabile, il file di test non diventa rosso: diventa a macchie.** `DatabaseSeederTest` ha già un test che si chiama `test_senza_la_password_il_seeder_si_ferma_e_non_scrive_niente`, e quel test **cancella** la variabile di proposito. Quindi se l'ambiente non la contiene, gli altri quattro test del file falliscono e **quello passa** — un file mezzo verde, che si legge come «qualcosa non va in quei quattro» invece di «manca una variabile» | `DatabaseSeederTest.php:112-128`, e `setUp()` alle righe 27-34 |

Da cui il punto `TAC10`: il file deve **dichiarare** il suo prerequisito una volta, in `setUp()`, con un
messaggio che nomina la variabile **e il comando che la prepara**. Un file mezzo verde è peggio di un
file rosso: il rosso dice che c'è un problema, le macchie dicono che il problema è altrove.

## 5. Consigli

- **`G-D1` → `.env.test.backend`** — risposto, ed è il nome che il repository aveva già scelto.
- **`G-D2` → fallire** — risposto, con un messaggio che dice quale variabile manca e quale comando la
  prepara. È lo stesso criterio con cui il seeder tratta la propria password mancante (`G6`): si ferma e
  spiega. **Ma va scritto un guardiano**, perché per `G8` il fallimento da solo arriverebbe a macchie —
  ed è il punto `TAC10`.
