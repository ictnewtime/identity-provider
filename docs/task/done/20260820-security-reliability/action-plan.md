# Piano — sicurezza e affidabilità

Sigla dichiarata dall'analisi: `TSR` — qui non si ridichiara.

Stato: **da approvare** · Data: 2026-08-20 · Analisi: [analysis.md](./analysis.md), che questo piano
**cita e non ripete**. `F…` e `D…` puntano lì. V — `auto`: lo stabilisce un comando · `man`: lo legge
una persona.

Sei rilievi su sei file che non c'entrano niente l'uno con l'altro: **un punto per rilievo**, e nessuna
onda. L'ordine in tabella è per utilità, non per dipendenza — solo `TSR01` ha un effetto che si sente
subito, perché è l'unico che ha già fatto danno.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TSR01 | **fatto** (2026-08-20) | **`D1` — i container dei test scrivevano come root nell'albero di chi sviluppa.** Strada **(b)**, risposta del developer: `--user "$(id -u):$(id -g)"` nel `docker run` dello script e `user:` nel compose degli E2E, **senza toccare le immagini**. Nel compose le due variabili `TEST_UID`/`TEST_GID` **non hanno un predefinito**: `1000:1000` sarebbe un uid inventato, giusto su questa macchina e sbagliato sulla prossima — se mancano, il compose si ferma e dice cosa scrivere. Aggiunto anche `HOME=/tmp`: l'uid dell'host dentro il container non ha una casa e composer scrive nella sua. **Su GitHub Actions e Ansible non si fa niente** (`D1`): là l'albero è un checkout usa-e-getta. **Questo punto da solo non basta**, e non era previsto: vedi `TSR09` | `scripts/run-test-backend.sh`, `docker-compose.test.yml`, `docs/TEST.md` | basso | auto | la suite gira e **non scrive più niente** nell'albero (`find -newer`, zero file nuovi); il compose degli E2E parte con `uid=1000 gid=1000` (`docker compose run --rm --entrypoint id e2e`, che non tocca il database) e **rifiuta** di partire senza le due variabili |
| TSR02 | **fatto** (2026-08-20) | **`F2` — la regex dell'email**: `/\S+@\S+\.\S+/` → `/^[^\s@]+@[^\s@]+\.[^\s@]+$/`. Due difetti in una riga, non uno: il **backtracking** che il rilievo nomina, e l'**assenza di ancore** che non nomina — «scrivimi a: a@b.c, grazie» passava, e il backend la rifiutava dopo. `[^\s@]` al posto di `\S` toglie la sovrapposizione fra i quantificatori, quindi non resta niente su cui tornare indietro. **Quello che resta**: `a@b..c` il frontend lo accetta e il backend no — una regex non riproduce `filter_var`, e la direzione è quella innocua (l'errore arriva dal server invece che dal campo) | `resources/js/components/UserForm.vue` | basso — stringe una validazione | auto | `./scripts/check-email-validation.sh` confronta la regex **letta dal componente** con la regola del backend su 22 indirizzi: **9 divergenze prima, 1 dopo**, e nessuna nella direzione che blocca un indirizzo valido. Lo script esce con errore **solo** se il frontend diventa più severo del backend — provato mutando la regex in `/^[a-z]+@[a-z]+\.[a-z]+$/`: 5 severi, exit 1 |
| TSR03 | **fatto dal developer** (2026-08-20) — controllato | **`F3`, `F10` — il rilievo è chiuso: `for="user-select"` ora precede `<Select id="user-select">` e nel dialogo non restano due `for` uguali** (un'unica riga cambiata, `git diff --stat`). Ma la verifica che segue parlava di **fuoco**, e quella non passa: PrimeVue mette `id` sul **contenitore**, che è un `div`, e `<label for>` vale solo verso un elemento *labelable*. L'etichetta è quindi collegata a niente — prima al campo sbagliato, adesso a nessuno. È un difetto suo, `VDF25`, e si corregge in `TSR08`: fuori dal perimetro di questo rilievo, che di `for` duplicati non ne ha più | `resources/js/components/user/AddRolesDialog.vue` | basso | man | i due `for` non sono più uguali e ognuno nomina il campo che gli sta accanto — **verificato**; il fuoco è in `TSR08` |
| TSR04 | **scartato** (2026-08-20) — **già risolto dal developer** | L'`@import` dei font è stato **rimosso** da `resources/sass/app.scss`: verificato, `grep googleapis` non trova più niente. Il punto non ha più oggetto. **Cosa resta da guardare, e non è questo punto**: se il carattere Raleway serviva a qualcosa, ora **nessuno lo carica** — né il CSS né il `<head>` del blade. Se la resa cambia, il `<link>` va aggiunto | — | — | — | — |
| TSR05 | fatto | **`D2`+`D4`, e la risposta cambia la decisione: l'`Head` di Inertia non si tocca — funziona.** La causa era un'altra (vedi `TSR07`): `app.blade.php` aveva un `<title>` **non marcato `inertia`**, quindi il gestore di Inertia ne aggiungeva un secondo in fondo all'`head` e il browser onorava il primo. Due interventi: l'attributo `inertia` sul titolo del blade, e la funzione `title` in `createInertiaApp` — che serve perché il titolo marcato `inertia` viene **rimosso** dal gestore, e senza quella funzione le **undici** pagine senza `<Head>` restavano senza titolo (misurato, non temuto) | `resources/views/app.blade.php`, `resources/js/app.js` | basso | man | **in produzione serve `npm run build`**: il blade non va compilato, `app.js` sì, e finché il bundle è vecchio la seconda riga della correzione non c'è. Misurato in locale con un browser headless (il server di sviluppo serve i sorgenti): `/client/v1/unauthorized` → «Accesso Negato - Identity Provider», `/admin/users` e `/` → «Identity Provider», **un solo** `<title>` nell'`head` in tutti i casi |
| TSR07 | fatto | **`D4` — perché il titolo non arrivava. Non era nessuno dei due sospetti rimasti.** La pagina **montava** e `$t()` **risolveva**: «Accesso Negato» era già nel `body` del DOM renderizzato, quindi né traduzione tardiva né errore JavaScript. Il DOM mostrava **due** `<title>` nell'`head`: `<title>Identity Provider</title>` dal blade, riga 7, e `<title inertia="">Accesso Negato</title>` aggiunto in fondo da Inertia. Con due titoli il browser usa il **primo**, ed è la riga del blade. La correzione è in `TSR05`. Diagnosi fatta qui, senza developer: `chromium --headless --dump-dom` esegue il JavaScript e stampa il DOM vero | nessuno (diagnosi) | basso | man | il comando `chromium --headless --disable-gpu --no-sandbox --virtual-time-budget=6000 --dump-dom URL` stampa l'`head` e si contano i `<title>` |
| TSR08 | **fatto** (2026-08-20) | **`VDF25` — le nove etichette collegate a niente.** Due forme, perché i componenti sono di due specie. `MultiSelect`, `ToggleSwitch` e `Password`: da `id` a `inputId`, che porta l'id su un `input` vero — là il `for` funziona per davvero, fuoco compreso. I cinque `Select` non hanno un input nemmeno con `inputId` (finisce su uno `span`, e labelable non è): l'etichetta prende un `id` e il componente `aria-labelledby`, misurato — Vue riconosce la forma con i trattini come la prop e l'attributo arriva sullo `span` con `role="combobox"`, non sul `div`. Più la svista di `ForgotPassword.vue:54`, dove il `for` nominava un id inesistente. **Quello che resta e non si può fare**: su un `Select`, cliccare l'etichetta non porterà mai il fuoco — senza un `input` il browser non ha dove portarlo. Il nome accessibile adesso c'è, il clic no | `resources/js/components/user/AddRolesDialog.vue`, `ProviderUserRoleForm.vue`, `RoleForm.vue`, `UserForm.vue`, `ProviderForm.vue`, `resources/js/Pages/Auth/ForgotPassword.vue` | basso | auto | `./scripts/check-label-targets.sh`: **9** prima, **0** dopo. Il controllo è stato **mutato tre volte** per vedere se accusa ancora — `aria-labelledby` togliuto, `aria-labelledby` verso un'etichetta inesistente, `inputId` rimesso a `id` — e ha trovato il rilievo tutte tre le volte. E la sola delle nove che sta su una pagina pubblica l'ho vista nel DOM vero: `<label for="username">` sopra `<input id="username">` |
| TSR09 | **fatto** (2026-08-20) | **La seconda metà di `TSR01`, che il piano non aveva previsto: con il solo `--user` la suite passava da 96 verdi a 13 rossi.** Misurato: **255 scritture rifiutate** su `storage/logs/laravel.log`. E la causa non è quella che il piano immaginava — quel file non è di root, è di **www-data**, perché lo scrive il container dell'applicazione. Due proprietari legittimi per lo stesso file, e l'utente dell'host non è nessuno dei due. Rimedio: ciò che il test scrive **non tocca l'albero** — `LOG_CHANNEL=stderr` in `phpunit.xml`, la stessa scelta già fatta per la config cache deviata in `/tmp`. Più `cacheDirectory` fuori dall'albero: senza quell'attributo Pest inietta `vendor/pestphp/pest/.temp`, e disattivare la cache **non funziona** perché lo stesso plugin aggiunge l'argomento che la accende e la riga di comando vince sull'XML (`vendor/pestphp/pest/src/Plugins/Cache.php:47`, provato) | `phpunit.xml` | basso | auto | `./scripts/run-test-backend.sh` → **96 passed (182 assertions)**, nessun avviso; e `find . -newer` dopo l'esecuzione non trova **alcun** file nuovo nell'albero |
| TSR10 | **scartato** (2026-08-20) — **la strada che proponeva non esiste.** | Il punto diceva «cambiare l'utente del container dell'applicazione». **Non si può, e la prova è nei suoi stessi file**: `entrypoint.sh:2` fa `chown -R www-data:www-data storage bootstrap/cache` e genera le chiavi RSA; `docker/supervisor/supervisord.conf` dichiara `user=root` per php-fpm e per **nginx**, che apre la porta 80. Un `user:` sul servizio `app` fermerebbe l'ambiente di sviluppo al primo avvio. Il container **deve** partire come root: è l'utente dei *comandi che si lanciano dentro* a essere sbagliato, non il suo. Da qui i tre punti che seguono, che sono la stessa idea di `TSR01` applicata a chi scrive davvero | nessuno | — | — | il punto non si esegue: sostituito da `TSR12` `TSR13` `TSR14` |
| TSR11 | **fatto** (2026-08-20) | **`F1`, `F6` — il rilievo legge il Dockerfile, non il comando, e per questo `TSR01` non lo chiudeva.** Rilevato dal developer: SonarQube segnalava ancora i due `Dockerfile.test.*`, e aveva ragione — `--user` sta nello script e nel compose, e chi guarda l'immagine non li vede. Aggiunto `USER www-data` prima di `ENTRYPOINT` in entrambi. **Non tocca `TSR01`**: `--user` a runtime vince su `USER`, quindi chi lancia i test resta se stesso; `USER` governa il caso che restava scoperto, chi lancia l'immagine a mano. `www-data` esiste già nell'immagine `php` (uid 33): nessun utente creato, nessun uid inventato. La sua casa è `/var/www`, cioè l'albero montato, e per questo l'`HOME=/tmp` di `TSR01` serve anche a lui. **L'altra strada — segnare l'hotspot come rivisto in SonarQube — è stata scartata**: una motivazione da ricordare vale meno di un'immagine che dichiara il proprio utente | `Dockerfile.test.backend`, `Dockerfile.test.e2e` | basso | auto | tre misure. `./scripts/run-test-backend.sh` → **96 passed (182 assertions)**, nessun avviso e nessun file nuovo nell'albero; `docker run --entrypoint id` **senza** `--user` → `uid=33(www-data)` su entrambe le immagini, dove prima era `uid=0(root)`; e il compose degli E2E, ricostruito, continua a girare come l'host → `uid=1000`. Resta da guardare il report: è `TSR06` |
| TSR12 | **fatto** (2026-08-21) | **La sorgente viva è un comando lanciato *dentro* il container.** Prova raccolta oggi in diretta: `public/build/` aveva **12 file di root** scritti oggi alle 09:02 da un `docker exec idp_app_2 npm run build`. Intervento: `scripts/in-app.sh`, che fa `docker exec --user "$(id -u):$(id -g)" --env HOME=/tmp --workdir /var/www`, si rifiuta di partire se il container è spento, e vale per `npm`, `composer` e `artisan` insieme — l'utente sta scritto **una volta** | nuovo `scripts/in-app.sh` | basso | auto | **misurato prima e dopo**. Prima: `npm run build` si ferma con `exit=1` e `EACCES: permission denied, unlink '/var/www/public/build/assets/app-gf-CnHMW.css'` — vite non riesce a cancellare gli asset vecchi di root. Dopo il `chown` di recupero e lanciato dallo script: `exit=0`, «✓ built in 19.37s», e **13 file su 13** appartengono al developer |
| TSR13 | **fatto** (2026-08-21) | **Il supervisor avviava vite come root**, e vite è il processo che resta in piedi tutto il giorno e riscrive a ogni salvataggio. Ora `vite.conf` chiama `docker/supervisor/run-vite.sh`, che ricava l'utente **dal proprietario dell'albero montato** — `stat -c %u /var/www` — e fa `setpriv --reuid … --regid … --clear-groups npm run dev`, con `environment=HOME="/tmp"` perché npm cerca la cache in `$HOME` e senza quello proverebbe `/root/.npm` (misurato). **Niente variabili, niente `.env`**: su richiesta del developer il valore non si passa, si ricava — il `docker-compose.yml` non può calcolarlo (interpola variabili, non esegue comandi) e un numero scritto in un file è giusto su una macchina e sbagliato sulla successiva. Il proprietario di `/var/www` **è** il dato, perché quell'albero è la cartella del progetto sull'host. Scartate per strada: l'opzione `user` di supervisor (espande la variabile ma **rifiuta** un uid senza voce in `/etc/passwd` — `Invalid user id 1000`, misurato) e `www-data`, che è l'uid 33 e lascerebbe i file di un utente che non è il developer | `docker/supervisor/vite.conf`, nuovo `docker/supervisor/run-vite.sh` | medio — tocca l'ambiente di sviluppo | man | dopo `docker compose up -d`: lo script stampa «vite parte come uid=1000 gid=1000 (proprietario di /var/www)», `vite entered RUNNING state` con **0** uscite impreviste, `lang/php_en.json` e `public/hot` sono **del developer**, `:5173/resources/js/app.js` → 200 e `:8001` → 200. **Il primo tentativo era fallito**, ed è la parte che insegna: vite in ciclo di `exit status 1` con `EACCES … open 'lang/php_en.json'` — quel file l'aveva scritto la vite **vecchia, di root, dieci minuti prima**. Il `chown` di `TSR14` doveva includere anche `lang/` |
| TSR14 | **fatto dal developer** (2026-08-20) — controllato | I file **già** di root: nessuno dei punti precedenti li tocca, impediscono solo che ne nascano altri. Il developer ha lanciato `docker run --rm -v "$PWD":/w alpine chown -R "$(id -u):$(id -g)" /w/vendor /w/node_modules /w/public` | nessun file del prodotto — un comando sull'albero | basso | auto | **misurato dopo**: `vendor` **13951** file, `node_modules` **35561**, `public` **26**, tutti del developer e **zero di root** nei tre. `storage` (196) e `bootstrap/cache` (6) restano di `www-data`: li rifà l'entrypoint a ogni avvio, ed è giusto così |
| TSR15 | **fatto** (2026-08-21) | **Il clone nuovo, l'unico caso che riportava tutto all'inizio.** `vendor`, `node_modules` e `public/build` sono in `.gitignore` e il montaggio copre quelli dell'immagine: su un albero appena clonato **qualcuno** deve installarli dentro il container, e se è una persona come root si ricomincia da migliaia di file di root. Ora lo fa l'`entrypoint.sh`, che già gira a ogni avvio e già è root — quindi **lascia** i privilegi invece di prenderli: `setpriv --reuid=$(stat -c %u /var/www)`, lo stesso ricavo di `run-vite.sh`, più `HOME=/tmp` per la cache di composer e npm. Installa **solo se manca**: `vendor/autoload.php` e `node_modules/`. **`entrypoint.sh` è dentro l'immagine**, quindi nell'ambiente del developer prende effetto al primo `docker compose up -d --build` | `entrypoint.sh` | medio — tocca l'avvio dell'ambiente | man | **provato su un clone vero**, senza toccare l'ambiente di sviluppo: `git clone --no-hardlinks` in una cartella di lavoro, `docker run` con quell'albero montato e nessuna porta pubblicata. Esito: composer e npm partono da soli («composer install come uid 1000»), l'avvio prosegue fino all'attesa di MariaDB, e nel clone finiscono **53283 file del developer, 30 di `www-data`** — quelli di `storage` e `bootstrap/cache`, che il `chown` successivo passa a php-fpm — e **zero di root**. **Il primo tentativo era fallito**: avevo messo il blocco **dopo** il `chown`, e `package:discover` non riusciva a scrivere `storage/logs/laravel.log`; composer esce 1 e `set -e` fermava l'avvio. L'ordine giusto è prima, e il `chown` che segue copre anche ciò che l'installazione ha creato |
| TSR16 | **fatto** (2026-08-21) | **`lang/php_en.json` è generato e non più tracciato**: lo riscrive il plugin `laravel-vue-i18n/vite` a ogni avvio del server di sviluppo, e il developer l'aveva cancellato da git il 2026-08-20 perché un container root lo riscriveva e git non riusciva più ad aggiornarlo. Restava `??` in `git status`, cioè rumore permanente. La regola è **per lingua** — `/lang/php_*.json` — non per quel file: il plugin ne genera uno per ogni lingua trovata in `lang/`, oggi solo `php_en.json`, domani `php_it.json`, e una regola su un nome solo lascerebbe rientrare il rumore dalla porta accanto. È la seconda metà della decisione di `BDB32`; la prima l'ha presa il developer cancellandolo | `.gitignore` | basso | auto | `git check-ignore -v lang/php_en.json` risponde `.gitignore:38`; `git status --short` non elenca più niente sotto `lang/`; e le traduzioni **vere** restano tracciate — `git ls-files lang/` elenca `en.json` e `it.json`, e `git check-ignore` non le prende. Nessun `git rm --cached` serviva: il file non era più tracciato. Controllato anche chi lo legge: `resources/js/app.js:72` fa `import.meta.glob("../../lang/*.json")`, e su un albero appena clonato il file lo crea il plugin prima che il bundle si costruisca. Suite **96 passed** |
| TSR17 | **fatto** (2026-08-21) | **I quattro residui di root che `TSR14` non aveva coperto.** Guardati uno per uno prima di toccarli, e uno dei quattro non era quello che sembrava: i tre oggetti in `.git/objects/` del **2026-06-04** sono i **commit di uno stash** — «WIP on staging» — cioè **dati vivi**, uno dei 6 stash del developer, creato da root dentro il container. Il `chown` cambia il proprietario e non il contenuto, quindi resta l'operazione giusta, ma valeva saperlo prima di lanciarlo. Il quarto è `config/passport.php` del **2026-03-12**. `supervisord.pid` **resta di root di proposito**: lo riscrive supervisord a ogni avvio, è in `.gitignore` e nessuno lo riapre | nessun file del prodotto — un `chown` su quattro file | basso | auto | `chown` mirato sui quattro percorsi, non ricorsivo sull'albero. Dopo: `find . -user root` elenca **solo** `supervisord.pid`; `git status` funziona, `git stash list` elenca ancora **6** stash, `git fsck --connectivity-only` non riporta errori né oggetti mancanti; `config/passport.php` è intatto (`git diff` non lo elenca, 1525 byte); `:8001` → 200 e la suite **96 passed** |
| TSR06 | **chiuso dal developer** (2026-08-21) | La conferma dal report: i rilievi corretti non compaiono più e `TSR05` è marcato scartato. **Il report l'ha guardato il developer**, non l'agente: qui non c'è una misura mia | nessuno (verifica) | basso | man | il developer ha chiuso il punto dopo aver letto il report |

## Cosa questo piano non copre

- **Il Dockerfile di produzione**: non è nei rilievi, e `F6` dice perché — è `php:8.2-fpm` con
  `user = www-data` per i processi e i `chown` al posto giusto. Non si tocca.
- **Un titolo diverso per ogni pagina** (`F9`): `TSR05` ha chiuso la metà facile — ogni pagina **ha** un
  titolo, ma è lo stesso per tutte e undici. Dare a ciascuna il suo è un `<Head>` per pagina e una chiave
  di traduzione per pagina: **non è un rilievo**, va deciso a parte.
- **L'equivalenza fra le due validazioni dell'email**: `TSR02` le ha avvicinate da 9 divergenze a 1, non a
  zero, e nessuna lista di esempi potrebbe dimostrare l'equivalenza. Il giorno che serve davvero, la strada
  è una sola: non validare l'email due volte — chiedere al backend e mostrare la sua risposta.
- **Il clic sull'etichetta di un `Select`**: `TSR08` ha dato a quei cinque campi un nome accessibile, non il
  fuoco al clic — senza un `input` vero non c'è modo, e l'unica strada sarebbe un gestore `@click` per
  etichetta. Non è un rilievo e non è un difetto: è un pezzo di comodità che PrimeVue non offre.
- **I 3996 file già di proprietà di root**: `TSR01` impedisce che se ne creino altri, non sistema quelli
  che ci sono. È un `chown` su un albero di lavoro reale, e lo esegue il developer.

## Perf/leak — la dichiarazione della policy per `TSR05`, `TSR07`, `TSR08` e il controllo di `TSR03`

Policy dell'organizzazione, dichiarata voce per voce. `TSR05` ha toccato **due file di presentazione** —
`resources/views/app.blade.php` e `resources/js/app.js` — e **nessun service, nessuna API Resource,
nessuna query**. Il controllo di `TSR03` e `scripts/check-label-targets.sh` non eseguono l'applicazione: leggono file e non aprono connessioni. `TSR02` ha cambiato una regex in una funzione pura, e `TSR08` **attributi di template** in sei componenti — nessuna chiamata, nessun dato in più che parte o che arriva: gli id e i nomi delle etichette erano già nella pagina. `TSR01` e `TSR09` toccano **come si eseguono i test** — uno script, il compose dei test, `phpunit.xml` — e non entrano in nessuna richiesta servita: `phpunit.xml` non viene letto in esercizio e i due container non esistono fuori dai test. `TSR12` e `TSR13` cambiano **con quale utente** girano un comando e un processo del solo ambiente di sviluppo: nessuna query, nessuna API, nessun dato — e un utente meno potente non allarga nessun accesso. Nessun file di configurazione nuovo e nessun valore scritto da nessuna parte: `TSR13` **legge** l'uid dal proprietario dell'albero, quindi non c'è niente che possa restare indietro o divergere fra due macchine. `TSR11` cambia l'utente predefinito di due immagini **di test** — l'immagine di produzione non e' toccata, e la voce che conta qui e' che un utente meno potente non allarga nessun accesso ai dati. Le cinque voci, con il perché di ogni «non applicabile»:

| Voce | Esito | Perché |
|---|---|---|
| Query N+1 | non applicabile | nessuna query aggiunta né spostata: il titolo è una costante nel blade e una funzione pura in `app.js` |
| Data leakage | **verificato applicabile, e pulito** | il titolo finisce nell'`head` di **ogni** pagina, anche per un utente non autenticato. Quello che ci arriva è il nome dell'applicazione e la chiave di traduzione della pagina — «Accesso Negato»: niente identificatori, niente dati dell'utente, niente stato della sessione |
| Scope/tenant | non applicabile | il titolo non dipende da utente né da provider: la stessa stringa per tutti |
| Memory/streaming | non applicabile | due stringhe |
| Query non vincolate | non applicabile | nessuna query |

## `TSR10` — cosa era, perche' e' scartato, e cosa lo sostituisce

Il punto chiedeva di cambiare l'utente del container dell'applicazione, per la stessa ragione di
`TSR01`: i file di root nell'albero di chi sviluppa. **Non si puo', e la prova sta nei file di quel
container**, non in un'opinione:

| Cosa | Dove | Perche' impedisce `user:` |
|---|---|---|
| `chown -R www-data:www-data storage bootstrap/cache` | `entrypoint.sh:2` | un utente non privilegiato non cambia proprietario |
| generazione delle chiavi RSA con `chmod 600` | `entrypoint.sh` | scrive in `storage/app/keys`, di `www-data` |
| `user=root` per **nginx** | `docker/supervisor/supervisord.conf:16` | la porta 80 non si apre senza privilegi |
| `user=root` per il master di php-fpm | `supervisord.conf:8` | i worker girano poi come `www-data` (`php-fpm.d/www.conf:2`) |

Un `user:` sul servizio `app` fermerebbe l'ambiente al primo `docker compose up`. Il container **deve**
partire come root: quello sbagliato non e' il suo utente, e' l'utente dei **comandi che si lanciano
dentro**. Da qui la sostituzione, che e' la stessa idea di `TSR01` portata dove i file nascono davvero:

| Chi scrive, e quando | Prova | Punto |
|---|---|---|
| una **persona**, con un comando dentro il container: `docker exec idp_app_2 npm run build` | `public/build/` scritto il 2026-08-20 alle **16:17 da root**, dieci minuti dopo la creazione dell'immagine | `TSR12` |
| **vite**, che il supervisor tiene in piedi come root tutto il giorno | `docker/supervisor/vite.conf:4` → `user=root`; `node_modules/.vite` di root, datato 2026-07-06 | `TSR13` |
| il **primo** `install` su un albero appena clonato, che nessuno dei due copre | `vendor`, `node_modules`, `public/build` sono in `.gitignore`: un clone non li ha, e il montaggio copre quelli dell'immagine | `TSR15` |
| nessuno — sono i file **gia'** scritti | 4208 file, datati marzo e maggio 2026 | `TSR14`, **chiuso** |

### `TSR12` e `TSR13`: come riprodurre l'errore a mano, e la correzione che si applica

Scritto per chi non ha in testa il contesto. La regola di fondo e' una sola: **un file dell'albero che
appartiene a `root` non lo puoi ne' riscrivere ne' cancellare**, e l'errore che ottieni (`EACCES`) non
nomina la causa. Due processi diversi lo creano, e per questo sono due punti.

#### `TSR12` — l'errore lo provochi tu, con un comando

**Riprodurlo** (ogni passo e' un comando da incollare, dalla radice del progetto):

```sh
# 1. l'ambiente in piedi
docker compose up -d

# 2. il build come si lancia oggi. Dentro quel container si e' root
docker exec idp_app_2 npm run build

# 3. guarda chi possiede il risultato: dice `root root`
ls -l public/build/manifest.json

# 4. adesso fai la cosa normale: lo stesso build dal tuo terminale
npm run build
#    -> si ferma con EACCES su un file di public/build
#    la versione minima, se vuoi isolare i permessi dal resto:
#    touch public/build/manifest.json   -> «permesso negato»
#    rm public/build/manifest.json      -> «impossibile rimuovere»
```

**Tornare indietro** (serve root, e per questo si passa da un container):

```sh
docker run --rm -v "$PWD":/w alpine chown -R "$(id -u):$(id -g)" /w/public
```

**La correzione.** Non si cambia il container — quel container **deve** essere root, vedi la sezione qui
sopra. Si smette di scrivere `docker exec` a mano, mettendolo una volta sola in uno script,
`scripts/in-app.sh`:

```sh
#!/usr/bin/env bash
# Un comando dentro il container dell'applicazione, con l'utente di chi lancia lo script.
# Senza `--user` si eseguirebbe come root, e ogni file scritto nell'albero montato nascerebbe di root:
# poi non lo riscrivi e non lo cancelli piu' (difetto BDB32).
set -euo pipefail
exec docker exec --user "$(id -u):$(id -g)" idp_app_2 "$@"
```

Da quel momento il build si lancia cosi', e il risultato e' tuo:

```sh
./scripts/in-app.sh npm run build
ls -l public/build/manifest.json     # dice il tuo utente, non root
```

I documenti che oggi nominano `docker exec idp_app_2` passano dallo script. **Cosa questo non copre**: chi
digita il comando lungo a mano continua a creare file di root. Non c'e' modo di impedirlo — si puo' solo
fare in modo che il comando giusto sia piu' corto di quello sbagliato.

#### `TSR13` — l'errore arriva da solo, senza che tu lanci niente

**Riprodurlo**: qui il passo 2 non esiste, ed e' tutta la differenza.

```sh
# 1. avvia l'ambiente e non fare altro
docker compose up -d

# 2. aspetta una decina di secondi che vite si avvii, poi guarda chi possiede cio' che ha scritto
ls -ld node_modules/.vite public/hot

# 3. provaci come te: non si cancella
rm -rf node_modules/.vite
```

Vite lo avvia il supervisor, e in `docker/supervisor/vite.conf:4` c'e' scritto `user=root`. E' un
processo che **resta in piedi tutto il giorno** e riscrive a ogni salvataggio di un file `.vue`: per
questo e' il piu' importante dei due, anche se e' quello che tocca l'ambiente.

**La correzione, e perche' non e' quella ovvia.** Le prime due idee non funzionano, e la terza e' meglio
di entrambe.

*Idea 1: mettere l'uid dell'host nell'opzione `user`.* **Non funziona, provato il 2026-08-20**: supervisor
espande la variabile, ma poi rifiuta un uid che nell'immagine non ha una voce in `/etc/passwd`.

```ini
user=%(ENV_HOST_UID)s
; Error: Invalid user id 1000 in section 'program:vite'
```

*Idea 2: `setpriv` con l'uid passato dal `docker-compose.yml`.* Funziona, ed e' stato applicato per
mezz'ora il 2026-08-21. Ma l'uid va scritto da qualche parte — nel `.env`, o esportato prima di ogni
`docker compose up` — perche' **il compose non sa calcolare valori**: la sua interpolazione legge
variabili d'ambiente, non esegue comandi. Il developer ha chiesto di evitarlo, e aveva ragione: un numero
scritto in un file e' giusto su questa macchina e sbagliato sulla prossima, e un valore predefinito
sbagliato non da' un errore — da' file di un utente che non esiste.

*Idea 3, applicata: non passarlo, **ricavarlo**.* L'albero montato su `/var/www` **e'** la cartella del
progetto sull'host, e chi la possiede e' esattamente l'utente che deve poter riscrivere cio' che vite
genera. Quel dato e' dentro il container, gia' pronto, e non va configurato:

```sh
# docker/supervisor/run-vite.sh
UID_ALBERO=$(stat -c %u /var/www)
GID_ALBERO=$(stat -c %g /var/www)
exec setpriv --reuid="$UID_ALBERO" --regid="$GID_ALBERO" --clear-groups npm run dev
```

```ini
[program:vite]
command=/var/www/docker/supervisor/run-vite.sh
directory=/var/www
environment=HOME="/tmp"
```

`HOME` e' la riga che si dimentica: npm come uid 1000 cerca la cache in `$HOME`, e senza quello proverebbe
`/root/.npm`, che non e' sua — misurato, `npm config get cache` risponde `/root/.npm` senza `HOME` e
`/tmp/.npm` con `HOME=/tmp`. Niente variabili nel compose, niente nel `.env`, niente nel `.env.example`:
`docker compose up` resta il comando di sempre e funziona identico sulla macchina di un altro.

**Come si verifica che e' fatto**: dopo `docker compose up`, `ls -ld node_modules/.vite` dice il tuo
utente, l'applicazione risponde su `:8001` e una modifica a un file `.vue` si vede nel browser — cioe'
vite gira ancora.

### I tre punti in dettaglio: cosa cambia, dove, e cosa non risolve

**`TSR12` — un solo posto dove sta scritto l'utente.** Oggi il comando che si lancia a mano e'
`docker exec idp_app_2 npm run build`, e dentro quel container si e' root: qualunque cosa scriva nel
montaggio nasce di root. Non serve cambiare il container — serve non scrivere piu' quel comando. Un
`scripts/in-app.sh` di tre righe:

```sh
exec docker exec --user "$(id -u):$(id -g)" idp_app_2 "$@"
```

e i documenti che oggi nominano `docker exec idp_app_2` passano da lui. Vale per `npm`, per `composer` e
per `artisan` insieme, ed e' la stessa forma di `TSR01`: **l'utente sta scritto una volta**. Cosa **non**
risolve: chi digita il comando lungo a mano continua a lasciare file di root. Per questo e' uno script e
non una convenzione — una convenzione va ricordata, uno script si trova con `ls scripts/`.

**`TSR13` — il processo che sta in piedi tutto il giorno.** Il supervisor avvia vite come root
(`vite.conf:4`), e vite scrive nel montaggio a ogni cambio di file: `node_modules/.vite` e `public/`. Qui
non c'e' un comando da riscrivere, c'e' una **configurazione**:

```ini
[program:vite]
user=%(ENV_HOST_UID)s
```

con `HOST_UID` passato dal servizio `app` di `docker-compose.yml`. **Non `www-data`**: quello e' l'uid 33,
e lascerebbe i file illeggibili in scrittura al developer esattamente come sono adesso. Questa e' la forma
in cui «passare l'utente dal `docker-compose.yml`» funziona per davvero: una variabile che arriva a un
**processo**, non un `user:` sul container, che come dice qui sopra fermerebbe nginx. Rischio **medio**:
tocca l'avvio dell'ambiente di sviluppo, e se `HOST_UID` non arriva il supervisor non parte — quindi va
provato con un `docker compose up` e l'applicazione da guardare su `:8001`.

**`TSR15` — il clone nuovo, che e' il caso che riporta tutto all'inizio.** I tre alberi sono ignorati da
git, il montaggio copre quelli dell'immagine, e allora **qualcuno** deve installarli dentro il container:
se e' una persona come root, si ricomincia da 4208 file. L'intervento e' nell'`entrypoint.sh`, che gia'
gira a ogni avvio e gia' e' root — quindi puo' **lasciare** i privilegi invece di prenderli:

```sh
[ -d vendor ] || su -s /bin/sh www-data -c "composer install"   # oppure l'HOST_UID di TSR13
```

Il guadagno che conta e' secondario e piu' grande di quello dei permessi: **un clone nuovo parte con
`docker compose up` e basta**, mentre oggi non parte da solo. Cosa **non** risolve: la scelta di struttura
che sta sotto — l'immagine installa dipendenze che il montaggio butta via, 42 + 643 + 2 voci che nessuno
aprira' mai. Quella e' una decisione sul `Dockerfile`, non un punto di questo lotto.

**L'ordine.** `TSR14` viene prima di tutti e **e' fatto**: senza di lui vite non scrive in
`node_modules/.vite` e `TSR13` fallisce. Poi `TSR12`, che e' basso rischio e verifica automatica. `TSR13`
e `TSR15` toccano l'avvio dell'ambiente e conviene provarli quando c'e' tempo di guardare `:8001`.

### La domanda del developer: e se l'utente lo passasse `docker-compose.yml` al `Dockerfile`?

Domanda del 2026-08-20: «`vendor` viene generato dal flusso `docker-compose.yml` → `Dockerfile`, quindi
sarebbe meglio passare l'utente da la'». **Meta' si', e la meta' che vale e' la seconda** — ma non
attraverso il build, e la misura dice perche'.

**Il build scrive nell'immagine, e a runtime l'immagine non si vede.** `Dockerfile:46,49,83` esegue
`composer install --no-dev`, `npm install` e `npm run build` **dentro l'immagine**. Poi
`docker-compose.yml:17` monta `./:/var/www`, e quel montaggio **copre** `/var/www` dell'immagine: da
quel momento i file del build non esistono per nessuno. Prova, che e' un discriminante secco e non un
ragionamento:

| Dove | Quanti pacchetti in `vendor` | `pestphp` |
|---|---|---|
| **nell'immagine** (`docker run --entrypoint sh identity-provider2-app`) | 42 | **non c'e'** — il build e' `--no-dev` |
| **nell'albero dell'host**, che e' quello che gira | centinaia | **c'e'** |

Se l'ambiente usasse il `vendor` dell'immagine, la suite non avrebbe PHPUnit. Un `ARG HOST_UID` con
`USER` nel `Dockerfile` cambierebbe quindi il proprietario di file che **nessuno apre**, e non toccherebbe
un solo file dell'albero. Servirebbe in produzione, dove il montaggio non c'e'; la` pero' il rilievo dice
gia' che il problema non c'e' (`F6`: `user = www-data` per i processi, `chown` su `storage` e
`bootstrap/cache`), e un `vendor` di root che `www-data` puo' solo leggere e' la disposizione **piu'**
sicura, non meno.

**Quello che vale del suggerimento** e' il posto: la variabile la passa il `docker-compose.yml`. Ma la
riceve un **processo**, non il container — che come dice `TSR10` deve restare root per nginx e per il
`chown` dell'entrypoint. Sono i due punti qui sopra: `HOST_UID` letto dal `vite.conf` (`TSR13`) e
l'utente passato al comando che si lancia dentro (`TSR12`).

**E una correzione a me.** La prima versione di `TSR12` diceva che i 3979 file di root in `vendor/` li
scrive l'immagine `composer:2`, che ha root come predefinito. **Le date lo smentiscono**: quei file sono
del **2026-03-06**, **03-11** e **05-27**, mentre i pacchetti installati di recente — `vendor/pestphp`,
`vendor/psr` — sono di **`www-data`**. In `vendor/` non c'e' una sorgente accesa: c'e' un sedimento di
mesi, e lo sistema `TSR14`. La sorgente accesa e' altrove, ed e' datata: `public/build/` scritto **oggi
alle 16:17 da root**, dieci minuti dopo la creazione dell'immagine — cioe' un comando lanciato a mano
dentro un container che e' root.

### «Il `chown` non risolve, perche' il `Dockerfile` rifa' tutto» — concordo sulla conclusione, non sul meccanismo

Osservazione del developer del 2026-08-20, dopo aver lanciato il `chown` di `TSR14`: i file dovrebbero
tornare a root, «in particolare se ri-creo il progetto in un'altra cartella partendo dal repo GitHub».

**Il `chown` ha tenuto**, e questa e' la misura subito dopo: `vendor` **13951 file, tutti del developer**,
`node_modules` **35561**, `public` **26**, e **zero** file di root nei tre. `storage` e `bootstrap/cache`
sono di `www-data` (196 e 6), che e' un'altra storia e non un residuo.

**Perche' il build non puo' rifarlo root.** Un `RUN` gira a build time, e a build time **il bind mount
non esiste**: scrive dentro l'immagine. Che ci scriva davvero si vede — l'immagine ha `vendor` con **42**
voci, `node_modules` con **643**, `public/build` con **2**. Poi, a runtime, `./:/var/www` **copre** quella
roba: le copie dell'immagine diventano invisibili, e non «tornano» da nessuna parte. L'unico `chown` che
gira a ogni avvio e' quello dell'entrypoint (`entrypoint.sh:5`), e tocca **solo** `storage` e
`bootstrap/cache`, mettendoli a `www-data` — non a root, e non nei tre alberi in questione.

**Dove il developer ha ragione, ed e' la parte che conta: il clone nuovo.** I tre alberi sono in
`.gitignore` (`:19`, `:20`, `:25`), quindi un clone non li ha. Il montaggio copre quelli dell'immagine,
quindi l'applicazione non li trova. Quindi **qualcuno deve installarli dentro il container**, e il
container e' root: file di root, e il ciclo riparte. Non e' il `Dockerfile` a rifarlo — e' il primo
comando lanciato dentro. E' il punto `TSR15`.

Sotto c'e' una scelta di struttura che vale nominare: **l'immagine installa dipendenze che il montaggio
butta via**. In sviluppo il lavoro del build e' sprecato, 42 + 643 + 2 voci che nessuno aprira' mai, ed e'
lo stesso motivo per cui un clone nuovo non parte da solo. `TSR15` la corregge dal lato che serve: chi
installa non e' piu' una persona come root, e' l'avvio.
