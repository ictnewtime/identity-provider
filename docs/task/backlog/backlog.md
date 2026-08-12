# Dubbi e proposte non ancora decise

**Identificatori**: `BDB` = registro dei dubbi

Aggiornato il 2026-08-12 (`date +%F`). **Non si contano e non scadono**: una proposta che sta ferma
sei mesi non diventa urgente, e nemmeno sbagliata.

Ogni voce porta **come si scioglie** — il comando, il file da guardare o la persona che decide.
Un dubbio senza modo di scioglierlo resta un dubbio per sempre, e questa lista serve a te per quando
hai tempo, non a me per averlo scritto.

**Le domande già dentro un task non si copiano qui.** Le sezioni § 4 delle quattro analisi in
`todo/` sono la loro sede: duplicarle darebbe due verità che divergono, e la seconda divergenza non
la nota nessuno. Qui sta ciò che **nessun piano copre** — le voci che i quattro piani hanno
esplicitamente lasciato fuori, e i buchi della procedura.

## Da decidere, con conseguenze sul codice

| ID | Dubbio | Come si scioglie |
|---|---|---|
| ~~`BDB01`~~ | **Quale strumento ha prodotto i rilievi.** — **Sciolto il 2026-08-12**: è **SonarQube**, in pipeline, con `-Dsonar.qualitygate.wait=true` (`.github/workflows/deploy-staging.yml:38` e l'omologo di produzione). Quindi il cancello **blocca già oggi** i deploy, e i rilievi delle tranche `v1`–`v4` non sono un parere: fermano un rilascio. Restano da trovare le regole attive e il modo di sopprimere un falso positivo (`TSA06`) | ✅ risolto guardando i workflow. Il seguito è in `BDB17` |
| `BDB02` | **Nessun rilievo si riproduce in locale — ne' JS ne' PHP.** `D5` ha escluso ESLint (`TSA09` scartato) e SonarQube gira solo in pipeline: chi scrive il codice scopre un rilievo **dal quality gate**, cioe' al momento del rilascio. E' il costo accettato con `D5`, ed e' scritto qui perche' sia una scelta e non una sorpresa | decidere fra PHPStan/Larastan nel repo, uno scanner Sonar locale, o niente. Verifica: un comando che rieseguito trova gli stessi rilievi |
| `BDB17` | **`-Dsonar.sources=.`** analizza la radice intera. Spiegherebbe perché arrivano rilievi da `cypress/e2e-bk/` (esempi generati) e potrebbe includere `vendor/` e `node_modules/` — cioè far pagare il quality gate su codice non nostro | guardare se esiste un `sonar-project.properties` o delle esclusioni lato server. Verifica: il numero di file analizzati nel report Sonar |
| `BDB18` | **Nessuno smoke test automatico dopo il deploy**: la §2 di `docs/post-deploy.md` è una checklist che esegue una persona. È **qui** che il problema vive davvero — la macchina finale ha un IP e non un nome di dominio, e il container che costruisce l'immagine non la raggiunge. I test di `TSD` girano prima e altrove, e non lo risolvono | decidere chi esegue lo smoke: un task Ansible dopo il deploy, un monitoraggio esterno, o niente. Verifica: un deploy con Swagger rotto viene segnalato senza che nessuno apra una pagina |
| `BDB19` | **`storage` non è persistito** (nessun volume nel deploy Ansible): le chiavi Passport e quelle RS256 del master token si rigenerano a ogni release, e il JWKS del master token **cambia** — i provider esterni che lo cachano falliscono la verifica. È il vero motivo della §1 obbligatoria del post-deploy. `TSD06` mette al riparo solo il JSON di Swagger | `docs/post-deploy.md` §4 propone già le due soluzioni. Verifica: due deploy consecutivi lasciano invariata `storage/app/keys/public.key` |
| `BDB03` | **API Resource per gli audit.** Oggi la risposta restituisce i modelli interi, `user` compreso (`AuditController.php:71-79`). Non è un leak attuale — la legge un amministratore — ma è la forma che lo rende possibile al primo ruolo nuovo. Introdurla **rompe la tabella Vue** | task suo, da fare col frontend insieme. Verifica: `app/Http/Resources/` contiene la risorsa e il controller non restituisce più modelli nudi |
| `BDB04` | **Gli audit hanno un tenant?** La rotta `routes/web.php:144` non filtra per provider né organizzazione. Se un amministratore è globale per definizione va **scritto**, non lasciato implicito nell'assenza di un `where` | lo decidi tu. Verifica: o un `where` di scope, o una riga nei requisiti che dichiara la scelta |
| `BDB05` | **Ordinare su una relazione polimorfa.** `TCC03` toglie `user.username` dalle colonne ordinabili: ferma il difetto `VDF02`, non lo ripara. La soluzione vera deve ordinare su due tabelle diverse (`users.username` e il nome del client Passport) e non ne ho una buona | progettazione. Verifica: la colonna torna ordinabile e un test copre entrambi i tipi di attore |
| `BDB06` | **I percorsi delle rotte sono dichiarati due volte** — in `routes/web.php` e nelle annotazioni `OA\*`. Rinominare una rotta lascia la documentazione che punta alla vecchia, e niente se ne accorge. I rilievi sui literali duplicati hanno sfiorato questa duplicazione senza nominarla | un controllo che verifichi che ogni percorso annotato corrisponda a una rotta esistente. Verifica: lo script fallisce se si rinomina una rotta senza toccare l'annotazione |
| `BDB07` | **`withTrashed()` incoerente** in `ProviderUserRoleController`: `find()` (`:219`) trova i record cancellati logicamente, `update()` (`:290`) e `delete()` (`:331`) no. Può essere voluto — si consulta un cancellato ma non lo si modifica — o una svista | lo sai tu. Verifica: se voluto, un commento che lo dice; se no, diventa un difetto e apre un punto |
| `BDB08` | **Costruttore vuoto in `app/Services/AccountService.php:27`**, gemello di quello di `TPU02`. Non l'ho messo in nessun piano: toccare un service fa scattare il controllo perf/leak completo, che per cancellare una riga non si giustifica | agganciarlo al prossimo task che tocca quel service per motivi veri |
| ~~`BDB09`~~ | **Riscrivere la storia git** per `cypress.env.json`. — **Chiuso il 2026-08-12** (`D1`): non si fa. Le credenziali erano dummy e usate solo in locale, quindi non c'è niente nel passato da ripulire | ✅ deciso dal developer |
| ~~`BDB10`~~ | **`cypress/e2e-bk/`** — cancellare o tenere. — **Deciso il 2026-08-12** (`D3`): **si tiene**, per ora. `TSA07` è scartato e `TSA08` si attiva. Il seguito è escluderla lato SonarQube, che è `BDB17` | ✅ deciso dal developer |
| `BDB20` | **Credenziali E2E contro un ambiente non effimero.** `TSA10`–`TSA13` le generano a ogni esecuzione: funziona perché gli utenti si possono ricreare. Se un giorno i test E2E dovessero girare contro un ambiente stabile — dove non si semina — servirebbe un segreto di lunga durata, e la sede naturale è **Infisical**, che la pipeline già usa (`ansible/builder/tasks/identity-provider.yml:3-8`) | si scioglie quando si decide dove girano i test E2E in pipeline. Verifica: se l'ambiente è effimero, questa voce si chiude senza fare niente |
| `BDB21` | **`cypress.env.example.json` (`TSA01`) e `scripts/prepare-e2e-credentials.sh` (`TSA10`) sono due elenchi delle stesse chiavi.** Aggiungerne una in un posto solo passa inosservato: il file di esempio è pensato per essere letto, lo script per essere eseguito, e il secondo vince in silenzio | un controllo che confronti le chiavi dei due, o l'eliminazione del file di esempio a favore del solo script. Verifica: aggiungendo una chiave a uno dei due, qualcosa fallisce |
| `BDB11` | **Il messaggio `"Role id not found"`** (`app/Http/Controllers/`) è lo stesso messaggio degli altri undici scritto male. Entra in `TPU06` solo se decidi di estendere la correzione a tutto lo strato | ricade su `D1` di `v4` |

## Buchi della procedura, non del prodotto

Emersi lavorando, e senza casella: `docs/ai/full/` non esiste in questo repo, quindi `full/backlog.md`
— dove R7 manderebbe queste righe — non c'è. Restano qui finché non c'è.

| ID | Dubbio | Come si scioglie |
|---|---|---|
| `BDB12` | **`docs/todo-manual.md` non esiste.** Serviva a `TSA02`, ora scartato — quindi al momento nessun punto e' senza casella. Il buco resta: il primo intervento fuori dal repo non avra' dove andare. `TSA14` (`git rm --cached`, che esegue il developer) e' il candidato piu' prossimo | crearlo, o dichiarare che in questo progetto gli interventi manuali stanno nei piani |
| `BDB13` | **`.claude/rules/` non esiste**, ma `docs/ai/index.md` lo dà per presente (R8): le 17 regole vivono solo come prosa nell'indice e in `CLAUDE.md`. Nessuna è applicata da un meccanismo | decidere se le regole diventano file in `.claude/rules/` o restano documentazione |
| `BDB14` | **Il cancello perf/leak non blocca.** `scripts/check-perf-gate.sh` scrive lo stato in `.claude/.perf-gate` e presuppone hook Claude Code non configurati qui: la policy dell'organizzazione che dice «nessuna eccezione» oggi è affidata alla sola disciplina | configurare gli hook, oppure dichiarare esplicitamente che il controllo è manuale — la via peggiore è lasciare uno script che sembra presidiare e non presidia |
| `BDB15` | **`./scripts/check-links.sh` segnala 28 link rotti**, tutti in `docs/ai/abstract/` e tutti verso l'export mancante (`full/`, `dev-guide/`, `*-custom.md`). Finché restano, il controllo è rumore: nessuno lo leggerà per trovare il ventinovesimo, che sarà vero | o si importano le parti mancanti, o si potano i link, o il controllo esclude `docs/ai/` dichiarandolo |
| `BDB16` | **I gemelli `*-custom.md` mancano tutti** — `testing-custom`, `perf-leak-custom`, `requirements-custom`. Sono i file che traducono le regole generiche su **questo** progetto, e `perf-leak.md` dice espressamente che vanno letti **prima** della checklist | scriverli, a partire da `perf-leak-custom.md`, che è quello che serve a ogni modifica di service |
