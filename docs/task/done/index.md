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
