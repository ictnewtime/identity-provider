# Piano — la complessità del trait di audit

Sigla dichiarata dall'analisi: `TAC` — qui non si ridichiara.

Stato: **da approvare** · Data: 2026-08-19 · Analisi: [analysis.md](./analysis.md), che questo piano
**cita e non ripete**. `F…` e `D…` puntano lì. V — `auto`: lo stabilisce un comando · `man`: lo legge
una persona.

L'ordine non è negoziabile su una cosa: **la rete prima del rifacimento**. `logAudit` scrive la traccia
di chi ha fatto cosa, e un'azione tradotta male non si vede da nessuna parte.

## Onda 1 — la rete

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TAC01 | **fatto** (2026-08-19) | **`D1`: la rete, prima del rifacimento.** Dieci test su `logAudit()`, che non ne aveva nessuno. Il primo è il guardiano della console: **senza** la leva non si scrive niente — cioè il comportamento di tutta la suite fino a oggi — e questo è ora un caso **asserito**, non un ostacolo. Gli altri nove accendono l'audit (`F11`) e fissano: `created` coi valori precedenti vuoti, `updated` col prima e col dopo, `deleted`, `restored`, `force_deleted`, il rumore delle sessioni **che non si scrive**, lo stesso campo `token` **che si scrive**, l'attore utente, l'attore client Passport. Il file dichiara in testa di essere una **rete e non una specifica**: fotografa cosa il codice fa oggi, non cosa sarebbe bello facesse | `tests/Feature/Audit/CustomAuditableTest.php` (nuovo) | basso | auto | 10 verdi sul codice **non rifatto**. **Provata contro tre regressioni diverse**, una per famiglia: traducendo `restored` in `deleted` cade il test del ripristino; svuotando l'elenco dei campi di servizio cade quello del rumore; azzerando `Client::class` cade quello dell'attore. Ogni volta **un solo** test rosso, che è ciò che rende la rete diagnostica e non un allarme generico. Suite intera **92 verdi** |

## Onda 2 — i rifacimenti

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TAC02 | da approvare | **`logAudit` da 43 a sotto 25** (`F1`): cinque metodi privati con un nome — `shouldSkipAudit`, `resolveAction`, `resolveActor`, `resolveIpAddress`, `auditRow` — e il metodo pubblico che resta la loro sequenza. La tabella del § 3 dice cosa decide ognuno. `resolveAction` è quella che guadagna più di tutte: tre `elseif` annidati diventano una tabella di casi | `app/Traits/CustomAuditable.php` | **alto** — è la traccia di conformità di sei modelli | auto | i nove test di `TAC01` restano verdi **senza essere modificati**. Se cambia un'asserzione, non è un rifacimento |
| TAC03 | da approvare | **`F4`, trovato leggendo**: `if ($userType == Client::class) { } else { … }` ha il **corpo vuoto** e dice al rovescio «per i client non cercare la sessione». Si invertono condizione e ramo. Punto a sé perché è un difetto di leggibilità con un nome, non un dettaglio del rifacimento | `app/Traits/CustomAuditable.php` | basso | auto | nessun ramo vuoto resta nel file; il test sull'attore client di `TAC01` resta verde |
| TAC04 | da approvare | **`ParameterForm.submit` da 16 a sotto 15** (`F8`): la stessa forma già scelta per i suoi due gemelli in `TFC05` — un elenco di campi, un ciclo, una funzione privata nel file. Nessuna decisione nuova: si applica una decisione già presa | `resources/js/components/ParameterForm.vue` | basso | auto | confronto esaustivo fra la catena di `if` di prima e il ciclo di adesso su tutti i sottoinsiemi dei campi, come per `TFC05`, e l'elenco dei campi confrontato con quello degli `if` originali |
| TAC05 | da approvare | **`D3`** — la costante `PASSWORD_DI_PROVA` (`F9`) esce dal sorgente e diventa una variabile d'ambiente: il **nome** entra in `.env.test.backend.example` **senza valore**, il valore lo mette il developer in `.env.test.backend`, che git ignora. `DatabaseSeederTest` legge `env("SEED_ADMIN_PASSWORD")` e, se manca, **fallisce dicendo quale variabile e quale comando** — non con un errore oscuro dentro il seeder (`G-D2`) | `tests/Feature/DatabaseSeederTest.php`, `.env.test.backend.example` | basso | auto | `grep -rn "SeedProva" tests/` non trova più niente; con la variabile impostata i test del file restano verdi, e senza il messaggio dice cosa fare |
| TAC07 | da approvare | **[Analisi 2], lo script**: `scripts/test-backend.sh` con tre passi — prepara `.env.test.backend` dal modello **chiedendo solo le variabili dichiarate vuote** (e niente, se il file è già completo), costruisce l'immagine, esegue la suite passando gli argomenti ricevuti. La password entra nel container con **`-e`, non con `--env-file`** (`§ 3`): un file locale sbagliato non deve poter sovrascrivere `TEST_ALLOWED_DATABASES`, che è la guardia di `VDF11` | `scripts/test-backend.sh` (nuovo) | medio — è la via con cui la suite verrà eseguita | auto | `./scripts/test-backend.sh` esegue la suite intera; `./scripts/test-backend.sh --filter=Audit` ne esegue una parte; alla seconda esecuzione **non chiede niente**; e `TEST_ALLOWED_DATABASES` dentro il container resta quello dell'immagine |
| TAC08 | da approvare | **`docs/TEST.md` rovesciato** per la parte di backend: **prima** lo script — che è ciò che serve quasi sempre — e **poi** la via manuale, con scritto che va preparato `.env.test.backend` e che le variabili dichiarate vuote vanno riempite. Chi apre un documento di test cerca il comando, non il meccanismo | `docs/TEST.md` | basso | man | la prima cosa leggibile sotto «Test di backend» è un comando che funziona senza sapere altro |
| TAC09 | da approvare | **`G-D1`, le tre incoerenze sui nomi** (`G3`, `G4`): il modello dice `cp … .env.testing`, `.gitignore` prevede `.env.test.backend`, e il Dockerfile copia dentro l'immagine il **modello**. Con una credenziale in mezzo, questa confusione fa mettere il valore in un file che nessuno legge. Un nome solo — `.env.test.backend` — e una riga accanto al `COPY` del Dockerfile che dice **perché** lì c'è il modello e non il file locale | `.env.test.backend.example`, `Dockerfile.test.backend` | basso | man | nel repository esiste un solo nome per quel file, e il Dockerfile dice da dove arriva la password |

## Onda 3 — la conferma

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TAC10 | da approvare | **`G-D2` + `G8`** — un guardiano in `setUp()` di `DatabaseSeederTest`: se `SEED_ADMIN_PASSWORD` non è nell'ambiente, il file si ferma con *«manca `SEED_ADMIN_PASSWORD`: eseguire `./scripts/test-backend.sh`»*. **Non è un doppione di `TAC05`**: serve perché senza guardiano l'assenza della variabile rende il file **mezzo verde** — gli altri quattro test falliscono e `test_senza_la_password_il_seeder_si_ferma_e_non_scrive_niente` **passa**, dato che è lui a cancellarla. Un file a macchie dice «c'è un problema in quei quattro»; un file rosso con un messaggio dice cosa fare. Più il test che asserisce il messaggio: è il messaggio a valere, e un messaggio senza test si degrada al primo cambio di nome della variabile | `tests/Feature/DatabaseSeederTest.php` | basso | auto | con la variabile assente il file fallisce **subito** e il messaggio nomina la variabile e lo script; con la variabile presente i cinque test restano verdi. **Provato nei due versi**: togliendo la variabile dall'ambiente, il fallimento è uno e parlante, non quattro e muti |
| TAC06 | da approvare | La conferma dal report: i tre rilievi non compaiono più. Dice che i numeri sono a posto, e **niente sul comportamento** — quello lo dicono `TAC01` e il confronto di `TAC04` | nessuno (verifica) | basso | man | i tre rilievi `high` spariti dal report |

## Cosa questo piano non copre

- **L'indice mancante su `sessions`** (`F5`, `D4`): questo piano **misura** che quella query gira a ogni
  scrittura di ogni modello auditato, e la misura sta nella voce `VDF15`. **`D4`: non è prioritario** —
  è efficienza, e si valuta dopo i rilievi di SonarQube. Correggerla è `TTR10`, in
  [token-refresh](../20260813-token-refresh/action-plan.md).
- **L'errore inghiottito di `logAudit`** (`F6`, `D2`): **ha un task suo**, deciso il 2026-08-19 —
  [audit-silent-failure](../20260819-audit-silent-failure/analysis.md). Qui non si tocca.
- **Gli altri form con la catena di `if`**: dopo `TAC04` restano `RoleForm` e `UserForm`. Non sono nei
  rilievi, e non si toccano per simmetria.
