# Task chiusi

Un task chiuso **non si cancella**: si sposta qui da `todo/` con `mv`, e prende una riga in questa
tabella. Resta come storia della decisione — il piano dice cosa si è scelto e perché, e sei mesi dopo
è l'unica cosa che lo dice.

`docs/task/done/` è una **zona chiusa** in lettura: non si apre lavorando, solo quando si ricostruisce
un perché.

| Task | Obiettivo | Sigla | Chiuso il |
|---|---|---|---|
| [./20260812-static-analysis-findings-v1/](./20260812-static-analysis-findings-v1/) | Rilievi SonarQube su frontend Vue e test E2E | `TSA` | 2026-08-12 |
| [./20260812-local-environments/](./20260812-local-environments/) | Due ambienti locali, e la forma del flusso di test | `TLE` | 2026-08-12 |
| [./20260813-vulnerability-fixes/](./20260813-vulnerability-fixes/) | I difetti senza casella | `TVF` | 2026-08-13 |
| [./20260812-static-analysis-findings-v2/](./20260812-static-analysis-findings-v2/) | Complessità cognitiva: lista audit e middleware | `TCC` | 2026-08-13 |
| [./20260812-static-analysis-findings-v3/](./20260812-static-analysis-findings-v3/) | Literali duplicati nelle annotazioni OpenAPI | `TOA` | 2026-08-13 |
| [./20260812-static-analysis-findings-v4/](./20260812-static-analysis-findings-v4/) | `ProviderUserRoleController`: messaggio ripetuto e costruttore vuoto | `TPU` | 2026-08-13 |
| [./20260819-cypress-assertions/](./20260819-cypress-assertions/) | Rilievi Cypress sul quality gate: sciolti cancellando, il resto scartato | `TCY` | 2026-08-19 |
| [./20260819-route-literals/](./20260819-route-literals/) | Literali duplicati fra rotte, annotazioni OpenAPI e test | `TRL` | 2026-08-19 |
| [./20260819-sonar-high-findings/](./20260819-sonar-high-findings/) | Literali nei file di configurazione e costruttori vuoti | `TSH` | 2026-08-19 |
| [./20260819-frontend-complexity/](./20260819-frontend-complexity/) | Complessità cognitiva nel frontend: `usePassword` e i due `submit` | `TFC` | 2026-08-19 |
| [./20260819-translation-coverage/](./20260819-translation-coverage/) | Le traduzioni del frontend, e i nomi dei test in inglese | `TTC` | 2026-08-19 |
| [./20260819-audit-complexity/](./20260819-audit-complexity/) | La complessità del trait di audit, e l'ambiente dei test | `TAC` | 2026-08-19 |
| [./20260820-security-reliability/](./20260820-security-reliability/) | Sicurezza e affidabilità: il titolo della scheda, le etichette scollegate e i file di root nell'albero | `TSR` | 2026-08-21 |
| [./20260821-exceptions-and-waits/](./20260821-exceptions-and-waits/) | Eccezioni dedicate al posto di `RuntimeException`, un ternario annidato e due attese fisse nei test | `TEW` | 2026-08-21 |
| [./20260821-regex-and-counters/](./20260821-regex-and-counters/) | Regex generate al posto di liste scritte a mano, contatori con un nome, e la riflessione nei test sostituita da una variabile d'ambiente | `TRC` | 2026-08-21 |
| [./20260821-deprecated-string-api/](./20260821-deprecated-string-api/) | `fromCodePoint` e `slice` al posto di due API vecchie, con la verifica portata sui bordi | `TDS` | 2026-08-21 |

## `20260812-static-analysis-findings-v1` — com'è finita

Non tutti i punti sono stati fatti, ed è il caso normale: quello che conta è che ognuno abbia un esito
scritto. **Otto chiusi, sei scartati, uno spostato.**

**L'obiettivo è raggiunto**: nessuna password letterale resta in `database/seeders/` né in
`cypress/e2e/`, verificato con `grep`. Le credenziali E2E ora si **generano** — `TSA10`,
`scripts/prepare-e2e-credentials.sh` — invece di essere custodite, e `cypress.env.json` non è più
tracciato da git (`TSA14`).

**Cosa è stato scartato, e perché** — è la parte che non si ricostruisce dal codice:

| Punto | Perché |
|---|---|
| `TSA02` | rotazione delle credenziali: **non serve**, erano dummy e solo locali (`D2`) |
| `TSA05`, `TSA05b` | togliere `autocomplete="new-password"`: sarebbe stata una **regressione** — il motivo sta in [/docs/doc-code-guide-line.md](/docs/doc-code-guide-line.md) |
| `TSA06` | soppressione dei falsi positivi in SonarQube: la configurazione vive **fuori dal repo** |
| `TSA07` | cancellare `cypress/e2e-bk/`: si tiene (`D3`) |
| `TSA09` | ESLint nel repo: niente nuove dipendenze di sviluppo (`D5`) |

**`TSA13` è stato spostato**, non scartato: eseguire lo script in pipeline è ora `BPT03` in
[../backlog/20260812-pipeline-tests/](../backlog/20260812-pipeline-tests/action-plan.md), fermo per
decisione del developer.

**Quattro punti sono chiusi con la verifica delegata** — `TSA10a`, `TSA11`, `TSA12`, `TSA03`: la loro
prova è un'esecuzione di Cypress, che oggi non è ripetibile, ed è il `TEC04` di
[../todo/20260812-e2e-test-container/](../todo/20260812-e2e-test-container/action-plan.md). **Se
quella li smentisce, il task torna in `todo/`**: un task chiuso non è un task immune.

**Cosa resta aperto altrove**: `VDF04` (l'`autocomplete` manca su tre form) e `VDF08` (la password del
seeder è fuori dal codice, ma il valore vecchio resta nella storia git) in
[../vulnerability/vulnerability.md](../vulnerability/vulnerability.md); `BDB24` e `BDB25` — nessun
runner JS e `npm run build` non eseguibile in locale — in
[../backlog/backlog.md](../backlog/backlog.md).

## `20260812-local-environments` — com'è finita

**Dieci punti chiusi, uno scartato.** L'obiettivo è raggiunto: `idp_develop` e `idp_test` esistono e
non si toccano, e i due tipi di test hanno ambienti distinti — **backend su sqlite senza compose**,
**E2E su MariaDB con compose**. La verifica che conta è stata fatta: dopo un'esecuzione completa,
`idp_develop` è rimasto intatto.

**La decisione che vale più del task** (`D3`, `D4`): la linea non passa fra `Unit` e `Feature` come
avevo proposto, ma fra **backend** ed **E2E**, e cade dove cade il vincolo vero — sqlite non
sopravvive fra due richieste HTTP. Il costo accettato è che la ricerca `LIKE` sulle lettere accentate
**non è coperta dai test di backend**: `LIKE '%MARIÒ%'` su `Mariò` trova 0 righe su sqlite e 1 su
MariaDB, misurato. La vedono gli E2E.

**Cosa è emerso strada facendo, e che il task non cercava**:

| | |
|---|---|
| `TLE08` | l'attesa del database in `entrypoint.sh` cercava un nome scritto a mano, falliva 30 tentativi e **proseguiva lo stesso** |
| `TLE09`, `TLE10` | il seeder lasciava il database a metà alla seconda esecuzione; ora fallisce con un errore **gestito**, che dice come riseminare |
| `TLE05` | seminare all'avvio non bastava: la suite PHP fa `migrate:fresh` e distruggeva il seed. Misurato — `providers` e `users` a 0 |
| `TLE12` | seminando il quarto parametro si è scoperto che **nessuno lo legge**: difetto `VDF12`, che questo task **non chiude** |

**Un punto è chiuso senza verifica**, e va saputo: `TLE04` — la specifica Cypress sulla ricerca
accentata — **non è mai stata eseguita**. Cypress non è ancora nell'ambiente E2E
([e2e-test-container](../todo/20260812-e2e-test-container/action-plan.md)) e i selettori sono dedotti
da `crud-user.cy.js`. È un test scritto e mai visto girare: vale quanto una promessa finché non gira.

**`TLE06` scartato**: misurare la durata della suite nelle due configurazioni. Il developer ha detto
che le tempistiche non contano; il riferimento — 4,3 s contro 0,6 s su 22 test — resta nell'analisi
come dato e non come criterio.

**Cosa resta aperto altrove**: `VDF11` (la separazione impedisce il danno, ma il difetto resta finché
la config cache è lì), `VDF12` (il parametro inerte), `BDB28` (`MYSQL_DATABASE` inerte nel compose) e
`BDB30` (la tabella si chiama `patemeters`).

## `20260813-vulnerability-fixes` — com'è finita

**Cinque punti fatti, uno scartato, uno chiuso.** Il task è nato per una ragione sola: cinque difetti
del registro non avevano un punto in nessun piano, e **un difetto senza punto non lo corregge
nessuno**, perché nessuno lo incontra lavorando.

**Tre erano già corretti** e sono stati spuntati con la verifica, non rifatti: `VDF01`
(`cypress.env.json` sganciato da git), `VDF06` (il `parseInt` che andava a `NaN`), `VDF08` (nessun
letterale di password nei seeder). Riverificati con `grep` e `git ls-files` il giorno della chiusura,
non presi per buoni dalla scheda.

**Il punto con più valore è `TVF04`**, e la forma gliel'ha data il developer correggendo una mia
premessa. Avevo consigliato di sovrascrivere `migrate:fresh`; ma quel comando, fuori da `local`,
**chiede già conferma da console** — Laravel protegge chi lo digita. Quello che mancava era una
guardia per chi cancella **da codice**, dove nessuno chiede niente. Da qui
`App\Support\DestructiveDatabaseGuard`, una funzione che si chiama e non un comando che si
sostituisce; `tests/TestCase.php` ora la invoca invece di duplicarla, così la difesa non vive più
dentro `tests/`.

Provata nei due versi (`TVF05`, cinque test): rifiuta il database di sviluppo **prima** di qualunque
migrazione — 0 oggetti creati — e non grida su `:memory:` né su `idp_test`. Fallisce **chiusa**: senza
elenco di database consentiti non lascia passare, perché su un'operazione che distrugge dati «non lo
so» deve valere «no».

**Due punti non sono stati fatti, e per ragioni opposte**:

| | |
|---|---|
| `TVF06` | scartato: l'`autocomplete` sui campi password lo prende il developer — e poi `VDF04` è stato **chiuso come comportamento voluto**, non corretto. Dove l'attributo manca è voluto che il browser suggerisca le password salvate: serve ai test manuali |
| `TVF07` | chiuso senza lavoro: il parametro `jwt-exp-time-seconds`, seminato e mai letto da nessuna riga di `app/`, **il developer lo ha rimosso**. Era la seconda delle due uscite possibili, ed era quella giusta |

**Una regola asimmetrica è nata da qui**, ed è scritta in
[/docs/doc-code-guide-line.md](/docs/doc-code-guide-line.md): sull'`autocomplete` **non si aggiunge
dove manca, non si toglie dove c'è**. Le due metà hanno ragioni diverse, e chi «uniforma» in una
direzione o nell'altra sta disfacendo una decisione.

**Un errore mio, registrato**: avevo dichiarato in due documenti che il difetto del parametro inerte
era registrato come `VDF12`, e non lo era — l'inserimento non era andato a buon fine e non l'avevo
verificato. Per due giorni è sembrato coperto quando non lo era.

**Cosa resta nel registro**: `VDF10` (`TCC13`), `VDF03` (`TCC04`), `VDF07` (`TSD06`/`TSD08`), `VDF09`
(`TCC08`), `VDF05` (`TPU04`) — tutti con un punto in un altro task.

## `20260812-static-analysis-findings-v3` — com'è finita

**Nove punti su nove**, ed è l'unica tranche chiusa per intero senza scarti. I nove rilievi SonarQube
sui literali duplicati sono chiusi: i quattro percorsi e i quattro literali di descrizione compaiono
ora una volta sola, nella loro dichiarazione.

**La prova che il refactoring non ha fatto danni non poteva venire dai test**, perché nessun test
guarda la documentazione generata — ed era il rischio vero della tranche. Viene da
`./scripts/openapi-spec-diff.sh`: istantanea prima, rigenerazione dopo, `diff`. Esito: **specifico
identico**. Lo script è provato nei due versi — cambiando una `description` di una riga, la mostra.

**Il file generato NON è stato versionato**, ed è una decisione: `api-docs.json` è un artefatto, e
versionarlo darebbe un conflitto a ogni annotazione toccata da due persone. L'istantanea fuori
dall'albero costa meno e serve allo stesso scopo.

**La correzione ha evitato la trappola del rilievo.** `"Provider id"` era segnalato 3 volte in un
file, ma compariva **17 volte su 4 file** insieme agli altri due. Una costante *per file* avrebbe
chiuso i rilievi lasciando tre copie della stessa stringa — il segnale spento e il problema intatto.
Le tre descrizioni stanno nel controller base; i percorsi hanno **una** costante per due percorsi
(`self::OA_PATH` e `self::OA_PATH . "/{id}"`), così resta visibile che sono la stessa rotta.

**`TOA09` è nato da una domanda del developer** — «un controllo sui percorsi annotati è fattibile, se
i controller servono anche le rotte web?» — e la risposta è: sì, **in una direzione sola**. «Ogni
percorso documentato ha una rotta» è un invariante; «ogni rotta è documentata» non lo è, perché gli
stessi controller servono anche `admin/v1/…` e tre rotte `api/v1` non sono documentate di proposito.
Il controllo confronta due elenchi di stringhe e non guarda i controller, quindi la condivisione non
lo disturba.

**Quel test si è dovuto riscrivere a metà lavoro**, ed è la cosa più istruttiva della tranche:
leggeva i `path:` dal sorgente con una regex, e ha smesso di trovarli appena quei literali sono
diventati costanti — sarebbe diventato «zero percorsi, tutto a posto». L'ha scoperto **il guardiano
che gli avevo messo dentro** (`test_il_controllo_ha_qualcosa_da_controllare`). Ora legge lo specifico
generato, che porta i percorsi risolti ed è quello che i client leggono davvero.

**Cosa non copre**: la direzione inversa del controllo, per la ragione sopra; e il numero dei rilievi
SonarQube, che si rilegge solo con una scansione.

## `20260812-static-analysis-findings-v4` — com'è finita

**Sei punti su sei**, la seconda tranche chiusa per intero. Ma il risultato che conta non era nel
piano iniziale.

**La scoperta**: le chiavi di traduzione dei messaggi 404 **esistevano già**, in italiano e in
inglese — dodici chiavi `*not_found*` in `lang/`, e `provider_user_roles.not_found` in inglese valeva
**esattamente** il literale scritto a mano nel controller. Il lavoro non era progettare un helper e
delle chiavi: era **smettere di ignorare quelle che c'erano**. Qualcuno le aveva scritte, e nessun
controller le aveva mai adottate.

Il guadagno è verificabile con un test che prima era impossibile scrivere: **la stessa rotta risponde
in due lingue diverse**. Col literale non c'era modo.

**Due casi non sono stati forzati nell'helper, e la distinzione è nel codice**:

| | |
|---|---|
| `"Role id not found"` | lo stesso messaggio degli altri undici, **scritto male** — normalizzato sulla stessa chiave |
| `SessionController` | restituisce anche `valid`, che il chiamante legge: tradotto **senza** l'helper, che avrebbe dato il solo `message` e cambiato il contratto |

**I test di `TPU01` sono nati per diventare rossi**, ed è successo. Fotografavano i messaggi vecchi
dopo aver verificato che **nessuno li confrontava** — né il frontend, né Cypress, né un test — così
il cambiamento sarebbe stato visibile invece che silenzioso. Quando `TPU03` li ha cambiati sono
falliti, e sono stati **riscritti sul comportamento nuovo**, non aggiustati di nascosto. Il file
porta la storia del perché è stato scritto due volte.

**Altre due cose emerse scrivendo i test, che il piano non prevedeva**:

- il 404 di `update()` è **irraggiungibile con un corpo non valido**: `ProviderUserRoleRequest`
  richiede che i riferimenti esistano, e la validazione gira prima del metodo — con id inventati la
  risposta è 422;
- `delete()` **e** `bulkDelete()` avevano lo stesso 204-con-corpo, ma nella scheda ce n'era uno solo.
  E correggendolo si è scoperto che Laravel **svuota da sé** il corpo dei 204: il difetto era nel
  codice che mente, non nella risposta.

**`TPU05` ha smentito un mio sospetto**: la differenza fra `withTrashed()` in lettura e la sua assenza
in scrittura **è voluta** — un'associazione cancellata logicamente si consulta, non si modifica. È
scritta in tre punti, uno per metodo, perché in uno solo gli altri due sarebbero rimasti a sembrare
dimenticanze.

**Cosa resta fuori**: il costruttore vuoto in `app/Services/AccountService.php` (`D6`, non segnalato
per ora) e la domanda che questa tranche lascia aperta — se dodici chiavi tradotte erano lì
inutilizzate, quante altre risposte dell'API sono ancora in inglese scritto a mano.

## `20260812-static-analysis-findings-v2` — com'è finita

**Sedici punti: quattordici fatti, uno scartato, uno spostato.** Con questa si chiudono **tutte e
quattro** le tranche del lotto del 2026-08-12.

**Il rilievo era due numeri di complessità; sotto c'erano quattro difetti che il rilievo non
nominava** — ed è la ragione per cui l'analisi è servita più della correzione:

| | |
|---|---|
| `VDF02` | la join verso l'attore ignorava `user_type` ed era interna: il registro **nascondeva righe** a seconda dell'ordinamento |
| `VDF03` | `per_page` senza tetto sulla tabella che cresce più in fretta |
| `VDF10` | il percorso d'errore del middleware falliva **proprio nel caso che doveva gestire** — 500 invece di 401, e un log che accusava il token |
| `VDF09` | un client Passport cancellato lascia gli audit senza attore: `Passport\Client` non ha soft delete |

**I test sono venuti prima, ed è quello che ha reso il resto possibile.** `TCC01` e `TCC02` — 24 test
sui sei rami del middleware e sui comportamenti della lista — sono stati scritti **prima** di toccare
il codice, e sono rimasti **invariati** durante tutta la scomposizione. È l'unica prova che il
comportamento non è cambiato. Uno di loro è nato rosso e ha scoperto `VDF10`: quel ramo d'errore non
era mai stato percorso.

**La scomposizione ha prodotto sei classi provabili da sole**, non metodi privati — era il requisito
posto dal developer. `app/Queries/Audit/` per la lista, `app/Auth/Idp/` per il middleware. Il
cambiamento che conta non è il numero di righe: il `try` non avvolge più quasi tutto, circonda la
sola decodifica — e da lì nasceva sia l'annidamento sia `VDF10`.

**Gli unit test asseriscono sull'SQL prodotto, senza database**: un test sui dati dice «le righe ci
sono», questi dicono **perché** ci sono. E hanno trovato un difetto nei test stessi — asserivano su
`"created_at"`, le virgolette di sqlite, e fallivano su MariaDB. Verdi su un motore, rossi sull'altro:
è la ragione per cui i due ambienti esistono.

**Due decisioni del developer hanno capovolto una mia raccomandazione**, e vale la pena averlo
scritto:

- `D2` — avevo consigliato di **togliere** `user.username` dalle colonne ordinabili. La risposta è
  stata **correggere la join**, ed era meglio: la mia raccomandazione poggiava anche su una premessa
  sbagliata — che gli audit di utenti cancellati sparissero — smentita da `SoftDeletes`;
- `D5` — avevo consigliato di rimandare la API Resource a un task suo. La risposta è stata farla qui,
  allargando lo scope, e il frontend è stato adeguato in un punto solo.

**Cosa non è stato verificato, ed è l'unica cosa che resta aperta**: il **numero** di complessità. Si
legge solo rilanciando SonarQube, e la verifica dei due punti di scomposizione è dichiarata `man` per
questo. Il codice è più corto e provabile a pezzi; che 18 e 16 siano scesi sotto 15 è **credibile, non
misurato**.

**Scartato** `TCC15` (spostato in `local-environments` come `TLE01`) e nessun punto abbandonato.
