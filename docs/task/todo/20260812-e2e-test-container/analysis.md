# Analisi — un container dedicato per i test E2E

**Identificatori**: `TEC` = task e2e-test-container

Stato: da approvare · Data: 2026-08-12

## 1. Obiettivo

Rendere i test E2E **eseguibili in modo riproducibile** da un container proprio, invece che dalla
macchina di chi sviluppa.

Perché adesso, e questa è la ragione concreta: quattro punti chiusi nella tranche
[static-analysis-findings-v1](../20260812-static-analysis-findings-v1/action-plan.md) — `TSA10a`,
`TSA11`, `TSA12`, `TSA03` — sono implementati e **la loro verifica è un'esecuzione di Cypress**.
Finché quella non è ripetibile, quei punti restano una dichiarazione.

## 2. Situazione attuale

### Perché il container dell'applicazione non basta

| # | Fatto | Prova |
|---|---|---|
| F1 | Delle sette librerie che Cypress richiede — `libnss3`, `libgbm1`, `libxss1`, `libasound2`, `libxtst6`, `libgtk-3-0`, `libnotify4` — nell'immagine base **non ce n'è nessuna** | `docker run --rm php:8.2-fpm dpkg -s <pacchetto>`: sette assenze su sette |
| F2 | Mancano anche `xvfb` e `xauth`: nessun display virtuale su cui disegnare | stesso comando |
| F3 | Dentro il container nginx ascolta sulla **porta 80**; la 8001 è solo il mapping pubblicato sull'host | `docker/nginx/conf.d/default.conf:2`; `docker-compose.yml:21` |
| F4 | `baseUrl` è scritto fisso a `http://localhost:8001`, quindi **da dentro qualunque container non risolve** | `cypress.config.js:32` |
| F5 | Il `Dockerfile` esegue `npm install` senza distinguere le dipendenze di sviluppo: scarica il binario di Cypress — ~1,5 GB di cache — in un'immagine dove non può funzionare | `Dockerfile:49`; `du -sh ~/.cache/Cypress` |
| F6 | La versione di Node non è fissata: viene da `apt` della base, oggi 20.19 su Debian trixie, che soddisfa `engines` di Cypress **per coincidenza** | `Dockerfile:22`; `node_modules/cypress/package.json` → `^20.1.0 \|\| ^22.0.0 \|\| >=24.0.0` |

### Cosa c'è già e rende il lavoro corto

| # | Fatto | Prova |
|---|---|---|
| F7 | **`cypress/included:15.15.0` esiste**, ed è esattamente la versione dichiarata dal progetto: browser, librerie e binario già dentro | `docker manifest inspect cypress/included:15.15.0` risponde; `package.json` → `cypress: ^15.15.0` |
| F8 | I tre servizi del compose stanno già sulla stessa rete `database-network`, e il servizio dell'app si chiama `app` | `docker-compose.yml:2,23-24,44-45,54-55` |
| F9 | Il volume `./:/var/www` condivide l'intero repository fra host e container: `cypress.env.json` scritto da dentro si vede da fuori, e viceversa | `docker-compose.yml` (volumes del servizio `app`) |
| F10 | Lo script che prepara le credenziali richiede `php artisan`, quindi gira nel container dell'**applicazione**, non in quello di Cypress | `scripts/prepare-e2e-credentials.sh` |

### Dipendenze e breaking change

- **Aggiungere un servizio al compose non rompe niente**, se non parte per default: va dietro un
  profilo, altrimenti ogni `docker compose up` scarica e avvia un'immagine da oltre un giga.
- **Rendere `baseUrl` parametrico è un cambiamento di configurazione condivisa**: se il default resta
  `http://localhost:8001`, chi oggi lancia Cypress da fuori non si accorge di niente. È la forma da
  preferire.
- **Togliere Cypress dall'immagine di Laravel** (`F5`, `BDB22`) è un cambiamento del `Dockerfile` che
  tocca il deploy: da fare, ma non di straforo insieme al resto.

## 3. Analisi

**Container separato o librerie nell'immagine?** Le due strade non sono equivalenti. Installare le
sette librerie più `xvfb` nel `Dockerfile` le farebbe finire **anche nell'immagine di produzione** —
peso e superficie in più per una cosa che in produzione non serve mai — a meno di introdurre uno
stage separato, che è complessità aggiunta per ottenere quello che `cypress/included` dà già fatto.
E `F7` è il fatto che chiude la discussione: esiste l'immagine con **la versione esatta** che il
progetto dichiara, quindi non c'è nemmeno un disallineamento da governare. Alternative scartate:
(a) librerie nell'immagine dell'app — sopra; (b) build multi-stage dedicato — si costruirebbe a mano
ciò che esiste già; (c) lasciare tutto sulla macchina di chi sviluppa, che è lo stato di oggi e
funziona finché tutti hanno il Node giusto, cioè finché non arriva qualcuno che non ce l'ha.

**Il nodo vero è l'indirizzo, non le dipendenze.** `F3` e `F4`: `localhost:8001` esiste solo
sull'host. Da un container sulla rete `database-network` l'applicazione si raggiunge come
**`http://app`**, sulla porta 80, perché quello è il nome del servizio (`F8`). Quindi il container di
Cypress da solo non basta: serve che `baseUrl` sia sovrascrivibile. Cypress legge le variabili
`CYPRESS_*`, quindi `CYPRESS_baseUrl=http://app` funziona senza toccare il file — ma conviene
comunque rendere il default esplicito nella configurazione, perché un valore che cambia solo per
variabile d'ambiente è un valore che nessuno trova quando smette di funzionare.

**Il rischio che non so risolvere in anticipo**: il volume monta anche `node_modules` dell'host
(`F9`), che contiene **la propria copia di Cypress** con un binario altrove. L'immagine
`cypress/included` porta il suo. Se le due si sovrappongono, Cypress può cercare un binario dove non
c'è. Le vie sono montare solo ciò che serve (`cypress/`, `cypress.config.js`, `cypress.env.json`)
oppure mascherare `node_modules` con un volume anonimo. **Non l'ho provato**, e nel piano è il primo
punto, non un dettaglio implementativo: è la cosa che decide se il resto funziona.

**Chi fa cosa fra i due container.** `F10`: le credenziali le prepara `php artisan`, quindi lo script
gira in `app`; i test girano in `cypress`. L'ordine è fisso — prima le credenziali, poi i test — e i
due comunicano attraverso `cypress.env.json` sul volume condiviso. È una dipendenza fragile e va
detta: **se un ambiente non condivide quel file fra i due container, la catena si spezza in
silenzio**, con un login che fallisce e nessuna indicazione del perché.

**Conseguenza a catena, ed è un guadagno**: con i test E2E in un container proprio, l'immagine di
Laravel non ha più nessun motivo di contenere Cypress. `F5` diventa una riga da togliere invece di un
compromesso da accettare.

**Confine con gli altri task.** Questo task **non tocca la pipeline**: rende i test eseguibili in
locale in modo riproducibile, il che è il prerequisito di `BPT07`/`BPT08` in
[backlog/20260812-pipeline-tests](../../backlog/20260812-pipeline-tests/action-plan.md) — ma quelli
restano fermi per decisione del developer. Qui non si scrive un solo file di workflow.

## 4. Da decidere

### Vincoli

- **D1** — il servizio Cypress va nel `docker-compose.yml` esistente **dietro un profilo**, o in un
  `docker-compose.e2e.yml` separato da comporre con `-f`? Il primo tiene tutto in un posto; il
  secondo tiene il file principale leggero e non fa mai scaricare un'immagine da un giga a chi non
  esegue E2E.
- **D3** — `node_modules`: si maschera con un volume anonimo, o si montano solo i percorsi che
  servono? Va **provato**, non dedotto (§ 3).

### Conflitti

- **D2** — `baseUrl`: si rende parametrico con default invariato (`http://localhost:8001`), o si
  tengono due configurazioni? La seconda è due verità che divergono, ma la prima tocca un file che
  oggi funziona per tutti.
- **D4** — togliere Cypress dall'immagine di Laravel (`F5`, `BDB22`) si fa **in questo task** o resta
  suo? Farlo qui chiude un difetto vero; ma tocca il `Dockerfile`, cioè il deploy, e questo task per
  il resto non lo tocca.

### Ignoto

- **D5** — il container di Cypress ha bisogno di raggiungere anche MariaDB o Mailpit, o gli basta
  `app`? Dipende dai test: se un domani ci fosse una verifica di email, servirebbe Mailpit. Oggi
  credo di no, ma non ho letto tutti i test.

## 5. Consigli

| Domanda | Raccomandazione |
|---|---|
| **D1** | **Profilo dentro il `docker-compose.yml`**, non un file separato. Un profilo non parte da solo, quindi il costo per chi non fa E2E è zero, e la topologia resta in un posto: `-f` in più è una riga che qualcuno dimenticherà. |
| **D2** | **Parametrico con default invariato**: `baseUrl: process.env.CYPRESS_BASE_URL ?? "http://localhost:8001"`. Chi lancia da fuori non si accorge di niente, e il valore resta scritto dove si va a cercarlo. |
| **D3** | Provare **prima** il volume anonimo su `node_modules`: è la soluzione più corta e la più comune. Se non basta, montare solo `cypress/`, `cypress.config.js` e `cypress.env.json`. |
| **D4** | **Farlo qui**, ma come punto separato e ultimo. È la conseguenza diretta di questo task — l'immagine porta Cypress solo perché non aveva alternative — e rimandarlo significa lasciare 1,5 GB in produzione con motivazione ormai decaduta. |
| **D5** | Verificarlo leggendo i tre file in `cypress/e2e/`. Se nessuno tocca la posta, il servizio Cypress va sulla rete e basta, senza dipendenze dichiarate oltre ad `app`. |

Il piano: [action-plan.md](./action-plan.md).
