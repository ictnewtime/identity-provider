# Piano d'azione — due ambienti locali e la forma del flusso di test

Sigla dichiarata dall'analisi: `TLE` — qui non si ridichiara.

Stato: **da approvare** · Data: 2026-08-12 · Analisi: [analysis.md](./analysis.md)

Fatti e alternative stanno nell'analisi: `F…` e `D…` puntano lì. V — `auto`: lo stabilisce un
comando · `man`: lo legge una persona.

> **Aggiornato il 2026-08-12 con le risposte del § 4.** `D3` e `D4` hanno diviso il lavoro in due
> ambienti invece di uno: **backend su sqlite senza compose**, **E2E su MariaDB con compose**. La
> divisione è **fatta e verificata** (`TLE03`); quello che resta è l'ambiente **develop**, che è in
> mano al developer.

## Onda 1 — l'ambiente develop

Va prima di tutto: finché il database di sviluppo non è quello dichiarato, ogni altra cosa poggia su
un ambiente che nessun documento descrive.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TLE01 | **approvato** (2026-08-12) — lo esegue il developer | **Creare `idp_develop` e puntarci l'applicazione.** Confluisce qui `TCC15` di [static-analysis-findings-v2](../20260812-static-analysis-findings-v2/action-plan.md), che copriva la sola rinomina. La procedura è in [/docs/setup.db.md](/docs/setup.db.md); **la esegue il developer** (`D1`), l'agente non tocca `.env` | `.env` e `docker-compose.yml` (servizio `app`) — a cura del developer | medio — finché il database non è popolato l'applicazione non parte | man | `show databases` elenca `idp_develop`; l'applicazione risponde su `/up` e l'accesso amministrativo funziona |
| TLE02 | **approvato** (2026-08-12) — lo esegue il developer | **`D2`** — cancellare `idp_local` e `idp_staging` (`F7`): 20 tabelle ciascuno, nessuno dei due corrisponde a un ambiente dichiarato. **Solo dopo** che `idp_develop` è popolato e l'applicazione risponde: prima sono l'unica copia dei dati con cui si è lavorato | nessuno (comandi SQL, eseguiti dal developer) | **alto se fatto prima di `TLE01`**, basso dopo | man | `show databases` elenca `idp_develop` e `idp_test`, e nient'altro |

## Onda 2 — due ambienti di test, uno per famiglia

`D3` e `D4` hanno spostato la linea: non fra `Unit` e `Feature`, ma fra **backend** ed **E2E**. È più
netta della divisione che avevo proposto e cade dove cade il vincolo vero — sqlite non sopravvive fra
due richieste HTTP.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TLE03 | **fatto** (2026-08-12) | **Backend su sqlite, senza compose**: `Dockerfile.test.backend` + `.env.test.backend.example`, si lancia con un `docker run`. **E2E su MariaDB con compose**: `Dockerfile.test.e2e` + `.env.test.e2e.example` + `docker-compose.test.yml`, con la ragione della divisione scritta **in testa a quel file** | i cinque file; `docs/setup.db.md`, `docs/SETUP.md` | medio | auto | entrambi eseguiti: 21 verdi e 1 rosso noto in tutti e due, e l'ambiente E2E stampa `CREATE DATABASE IF NOT EXISTS idp_test` |
| TLE04 | da approvare | Il test che rende visibile `F9`: ricerca `q` con una lettera accentata **maiuscola**. **Va nella famiglia E2E**, perché con `D3` il backend gira su sqlite e lì quel test fallirebbe per il motore, non per il codice. Oggi non esiste, e la sua assenza è il motivo per cui la divergenza è passata inosservata | `cypress/e2e/` | basso | auto | il test è verde sull'ambiente E2E |
| ~~TLE06~~ | **scartato** (2026-08-12) | Misurare la durata della suite nelle due configurazioni. **`D5`: le tempistiche non contano.** Il riferimento di oggi resta annotato nell'analisi — 4,3 s contro 0,6 s su 22 test — come dato, non come criterio | — | — | — | — |

| TLE08 | **fatto** (2026-08-12) | **L'attesa del database in `entrypoint.sh` cercava un nome scritto a mano.** Il servizio MariaDB è stato rinominato da `mariadb` a `idp_mariadb_2` e la rete in `idp_network`: la riga `/dev/tcp/mariadb/3306` ha smesso di trovarlo, ha fallito **30 tentativi e poi è proseguita lo stesso** — il modo peggiore di sbagliare, perché il container parte e l'errore si manifesta altrove. Ora legge `DB_HOST`/`DB_PORT` e, se scadono i tentativi, **lo dice** invece di tacere. Allineato anche `docker-compose.test.yml`, che aveva le stesse due stringhe stantie | `entrypoint.sh:22-40`, `docker-compose.test.yml` | medio — è il percorso di avvio dell'applicazione | auto | l'ambiente E2E stampa `Attendo idp_mariadb_2:3306` e prosegue: 21 verdi, 1 rosso noto |

## Onda 3 — la forma del flusso, e gli E2E

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TLE07 | da approvare | Riscrivere `docs/TEST.md` sul nuovo assetto: oggi descrive **un** ambiente di test e ne è rimasto uno solo dei due. Deve dire quale comando serve a quale famiglia, e perché il backend non ha un compose | `docs/TEST.md` | nullo | man | il documento descrive i due ambienti e non contraddice i file |
| TLE05 | da approvare | **`D6`, ridotto** — `DatabaseSeeder` va bene: non serve scriverne uno nuovo. Resta da **verificare che basti** per gli E2E e da passargli `SEED_ADMIN_PASSWORD`, che senza si ferma (difetto `VDF08`). Il campo è già in `.env.test.e2e.example`, vuoto | `.env.test.e2e.example`, entrypoint dell'ambiente E2E | basso | auto | un'esecuzione E2E su un `idp_test` appena creato passa senza preparazione manuale |

## Cosa questo piano non copre

- **Il container di Cypress** (`F8`): all'immagine dell'applicazione mancano tutte le librerie che gli
  servono, ed è [e2e-test-container](../20260812-e2e-test-container/action-plan.md). Qui si decide
  quale database useranno gli E2E e chi lo tiene vivo, non dove girano.
- **I test in pipeline**: [backlog/20260812-pipeline-tests](../../backlog/20260812-pipeline-tests/action-plan.md),
  fermo per decisione del developer.
- **`TCC05`** (`F11`, l'ambiguità di `->latest()`): resta nel suo task, ma **diventa eseguibile** solo
  se `TLE03` porta i test Feature su MariaDB. Su sqlite quel test non si può scrivere.
- **La scrittura non autorizzata sul database** che ho fatto per misurare `F9`: dichiarata
  nell'analisi. Non è un punto da fare, è una cosa da sapere.
