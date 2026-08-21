# Analisi — sei rilievi di sicurezza e affidabilità, e ciò che c'era sotto

**Identificatori**: `TSR` = task security-reliability

Stato: da approvare · Data: 2026-08-20

## 1. Obiettivo

Chiudere sei rilievi di SonarQube delle categorie **security** e **reliability**, su sei file diversi:
due Dockerfile, tre file del frontend e un foglio di stile.

Sono sei cose che non c'entrano niente l'una con l'altra, ed è la ragione per cui questa analisi non
prova a trattarle insieme: ogni voce ha una sua sezione nel § 3. Ma guardandole una per una, **tre delle
sei hanno rivelato qualcosa di più grande del rilievo** — e in un caso il rilievo è un falso positivo che
nasconde il difetto opposto (§ 3).

## 2. Situazione attuale

| # | Rilievo | Dove | Cosa dice il codice |
|---|---|---|---|
| F1 | l'immagine `php` gira come `root` | `Dockerfile.test.backend:20`, `Dockerfile.test.e2e:14` | `FROM php:8.2-cli` e nessun `USER`: i due container dei test girano come root **e montano l'albero di lavoro** |
| F2 | espressione regolare con backtracking super-lineare | `resources/js/components/UserForm.vue:104` | `const re = /\S+@\S+\.\S+/` — due quantificatori su classi che si sovrappongono, e **senza ancore** |
| F3 | manca una `<label>` associata al campo | `resources/js/components/user/AddRolesDialog.vue:267` | il `<Select id="user-select">` non ha label |
| F4 | `@import` in posizione non valida | `resources/sass/app.scss:8` | `@import url("…fonts.googleapis.com…")` **dopo** due `@use` |
| F5 | manca il `<title>` della pagina | `resources/js/Pages/Client/Unauthorized.vue` | la pagina ha `<Head :title="$t('client.unauthorized.title')" />`, che è **il modo di Inertia** per darle un titolo |
| F6 | (in produzione il problema non c'è) | `Dockerfile` | l'immagine di esercizio è `php:8.2-fpm` e configura `user = www-data` per i processi, con `chown` su `storage` e `bootstrap/cache` |

### Le tre cose trovate guardando

| # | Fatto | Prova |
|---|---|---|
| F7 | **Il container come root ha già fatto danno, e il danno è misurabile**: nell'albero di lavoro ci sono **3996 file di proprietà di `root`** (esclusi `node_modules` e `.git`) — fra cui `vendor/`, `public/build/`, `config/passport.php` e `lang/php_en.json`. Li scrivono i container: l'entrypoint dei test esegue `composer install` se `vendor/bin/phpunit` manca, e scrive come root nella cartella montata | `find . -user root -not -path "./node_modules/*" -not -path "./.git/*" \| wc -l` → `3996` |
| F8 | **Uno di quei file blocca `npm run build`**: `lang/php_en.json` è di root, e il plugin `laravel-vue-i18n` lo riscrive all'avvio del build → `EACCES: permission denied`. È il difetto già registrato come `BDB32`, di cui non si conosceva la causa: **è questa** | `npm run build` su un albero pulito, e `ls -l lang/php_en.json` |
| F9 | **`F5` è un falso positivo, e nasconde il contrario**: `Unauthorized.vue` è **l'unica** delle **12** pagine che dà un titolo. Le altre undici non lo danno, e prendono quello statico di `app.blade.php`, «Identity Provider». SonarQube ha segnalato la sola pagina che fa la cosa giusta, perché legge l'SFC come HTML e non conosce `<Head>` di Inertia | `grep -rn "Head :title" resources/js/Pages/` → **1**; `find resources/js/Pages -name "*.vue" \| wc -l` → **12** |
| F10 | **`F3` non è un problema di accessibilità: è una label che punta al campo sbagliato.** Alla riga 264 c'è `<label for="roles-multiselect">` **prima** del `<Select id="user-select">`, e la stessa `for="roles-multiselect"` si ripete alla riga 282 sul controllo giusto. Cliccando la prima label si porta il fuoco sul secondo campo | `AddRolesDialog.vue:264-287` |

### Dipendenze e breaking change

- **`F1` non si corregge nell'immagine, se non si vuole**: i due container si lanciano dai nostri script
  (`run-test-backend.sh`), e `docker run --user "$(id -u):$(id -g)"` risolve la causa **senza toccare i
  Dockerfile**. È la differenza fra cambiare l'immagine per tutti e cambiare come **noi** la usiamo.
- **`F2` cambia una validazione**: la regex di oggi accetta un'email **contenuta** in una stringa più
  lunga, perché non è ancorata. Correggerla la rende più stretta — è meglio, ma è un cambiamento di
  comportamento, e il backend valida comunque con la regola `email` di Laravel.
- **`F4` tocca il caricamento dei font**: spostare l'`@import` cambia **quando** il font arriva.

## 3. Analisi

### `F1`, `F7`, `F8` — la sola voce che ha già fatto danno

Il rilievo è marcato come «assicurati che sia sicuro qui», e per un'immagine di **test** la risposta
sarebbe «lo è: gira in locale e in CI, non espone niente». Ma il conto di `F7` cambia la domanda: 3996
file di root nell'albero di chi sviluppa **non** sono un rischio di sicurezza, sono un fastidio quotidiano
che ha già rotto una cosa (`F8`) e che si ripresenterà a ogni `composer install` dentro un container.

Due modi, e non sono equivalenti:

| Strada | Come | Effetto |
|---|---|---|
| **(a) `USER` nel Dockerfile** | `USER www-data` prima del `CMD` | l'immagine non gira più come root **per nessuno**. Ma `www-data` nel container ha un uid che non è quello di chi sviluppa, e i file scritti sarebbero di **quell'** uid: il problema cambia proprietario, non spariscde |
| **(b) `--user` al `docker run`** | `--user "$(id -u):$(id -g)"` negli script | i file scritti appartengono **a chi ha lanciato lo script**. Risolve `F7` e `F8` alla radice, in una riga per script, e non cambia l'immagine per nessun altro |

**(b)**, e i file già di root restano da sistemare a mano una volta — è un `chown` che il developer esegue,
non l'agente.

### `F2` — la regex

`/\S+@\S+\.\S+/` ha due difetti in una riga: **il backtracking** su input lunghi che non corrispondono, e
**l'assenza di ancore**, per cui `«scrivimi a: a@b.c, grazie»` passa la validazione. La forma corretta è
lineare e più stretta:

```js
/^[^\s@]+@[^\s@]+\.[^\s@]+$/
```

Ogni classe **esclude** `@` e lo spazio, quindi non c'è sovrapposizione fra i quantificatori e non c'è
niente su cui tornare indietro. È anche l'unico modo di far combaciare la validazione del frontend con
quella del backend, che rifiuta ciò che questa accetta.

### `F3`, `F10` — la label sbagliata

Non è «aggiungere una label»: è che **la label c'è e punta altrove**. Due `<label for="roles-multiselect">`
nello stesso dialogo, e il campo dell'utente senza. Chi clicca sulla prima etichetta vede il fuoco
saltare al campo dei ruoli. Si corregge la prima in `for="user-select"`.

### `F4` — l'`@import` dei font

In Sass gli `@use` vanno in cima; in CSS gli `@import` pure. Le due regole insieme rendono quella
posizione impossibile: finché `@use` precede, l'`@import` è fuori posto. Due uscite: portarlo **davvero**
in cima (prima degli `@use`, che Sass non consente) — quindi non si può — oppure **togliere il font dal
CSS** e metterlo nel `<head>` di `app.blade.php` con un `<link>`. La seconda è anche più veloce: un
`@import` dentro un CSS è una richiesta che il browser scopre **dopo** aver scaricato il foglio.

### Il titolo che non arrivava — due `<title>` nell'`head`

Il developer ha provato la pagina: la scheda resta «Identity Provider». Prima di decidere se togliere
`<Head>` conviene sapere perché non funziona, perché **la sostituzione ovvia non funzionerebbe nemmeno**:
un `<title>` scritto dentro il `<template>` di un componente finisce nel `body`, e il browser lo ignora
per il titolo della scheda. Se `<Head>` non arriva al `<head>`, un `<title>` scritto a mano ci arriva
ancora meno.

**Escluso, misurando:**

| Sospetto | Verifica | Esito |
|---|---|---|
| il bundle servito è vecchio e non contiene la pagina | `public/build/manifest.json` è di oggi; `client.unauthorized.title` **c'è** negli asset | escluso — e comunque non è il bundle a essere servito, vedi sotto |
| manca `@inertiaHead` nel blade | `resources/views/app.blade.php:9` | c'è |
| la chiave di traduzione non esiste | `lang/it.json` → «Accesso Negato» | c'è |
| la rotta non passa da Inertia | `routes/web.php:165` → `Inertia::render("Client/Unauthorized")` | passa |
| gli asset non si caricano | `public/hot` esiste e contiene `http://localhost:5173`, e quel server **risponde 200**: la pagina carica il codice **dal server di sviluppo**, cioè dai sorgenti | si caricano |

**Non era nessuno dei due sospetti che restavano.** Li ho verificati con un browser headless —
`chromium --headless --dump-dom` esegue il JavaScript e stampa il DOM vero — e sono caduti entrambi:
«Accesso Negato» **era gia' nel `body`**, quindi la pagina montava e `$t()` risolveva. Nessuna traduzione
tardiva, nessun errore JavaScript.

La causa e' nell'`head`, e sono **due** `<title>`:

```
<title>Identity Provider</title>                    <- app.blade.php:7
<title inertia="">Accesso Negato</title>            <- aggiunto da Inertia, in fondo all'head
```

Il gestore dell'`head` di Inertia governa **solo** gli elementi marcati con l'attributo `inertia`: il
titolo del blade non lo era, quindi non veniva sostituito ma **affiancato**. Con due titoli nell'`head`
il browser usa il **primo**, ed e' quello del blade. Da qui la scheda immobile.

**La correzione e' di due righe, e la seconda non e' facoltativa.** L'attributo `inertia` sul titolo del
blade fa sostituire il titolo invece di affiancarlo. Ma quel titolo diventa cosi' proprieta' di Inertia, e
il gestore lo **rimuove** quando la pagina non ne fornisce uno: le undici pagine senza `<Head>` passano da
un titolo sbagliato a **nessun titolo**. Misurato, non temuto: con il solo attributo, `/admin/users` non
aveva piu' alcun `<title>` nell'`head`. Serve percio' anche la funzione `title` in `createInertiaApp`, che
Inertia interroga a ogni pagina — anche con la stringa vuota, che e' il caso delle pagine senza `<Head>`.

Cosi' `F9` — «le altre undici pagine non hanno titolo» — e' **meta' chiuso** da questo stesso intervento:
ognuna ha un titolo, ma ancora lo stesso per tutte. Dare a ciascuna il suo resta un lavoro suo.

### `F1`, `F6` — perche' `--user` non spegne il rilievo

La risposta a `D1` — strada (b), l'utente si passa a chi lancia, **senza toccare le immagini** — risolve
il problema reale: i file scritti nell'albero. Ma **non chiude il rilievo**, e il developer lo ha
verificato il 2026-08-20: SonarQube continua a segnalare i due Dockerfile. Ha ragione lo strumento —
`--user` sta in `scripts/run-test-backend.sh` e in `docker-compose.test.yml`, e chi guarda l'immagine non
li vede. L'unica cosa che l'immagine dichiara di se stessa e' l'istruzione `USER`, che non c'e'.

Le due cose non sono in conflitto e non si sostituiscono: `--user` a runtime **vince** su `USER`, quindi
aggiungere l'istruzione non cambia una virgola di come girano i test oggi. Cambia il caso di chi lancia
l'immagine a mano, che adesso e' root. Misurato su una copia usa-e-getta: con `USER www-data` la suite da'
`96 passed` in entrambi i modi, con e senza `--user`.

Resta che e' un *security hotspot* — «assicurati che sia sicuro», non «e' sbagliato» — e la seconda
strada era segnarlo come rivisto in SonarQube con la motivazione. **Scelta la prima il 2026-08-20**
(`TSR11`, applicato): un'immagine che dichiara il proprio utente non ha bisogno che qualcuno, l'anno
prossimo, si ricordi perche' quella motivazione era valida.

### `F3`, `F10` — la label corretta, e cosa si è visto correggendola

Il developer ha corretto il `for` il 2026-08-20 e il rilievo è chiuso. Ma **verificandolo** è venuto
fuori che il rilievo guardava il sintomo piu' piccolo: `<label for="X">` vale solo verso un elemento
*labelable* — `input`, `select`, `textarea`, `button` — e i componenti PrimeVue mettono l'attributo `id`
sul **contenitore**, un `div`. L'etichetta era collegata al campo sbagliato; adesso è collegata a
**niente**.

Misurato rendendo i componenti fuori dall'applicazione, con `vue/server-renderer` e `primevue/config`:
con `id` l'attributo finisce sul `div`, con `inputId` su un `input` vero. `Select` fa eccezione due
volte — non ha un `input` e nemmeno `inputId` lo rende labelable (finisce su uno `span`) — e la' l'unica
associazione possibile e' `aria-labelledby`.

Il conto: **nove** etichette, in sette file, e non sono la maggioranza — il resto del progetto usa gia'
`inputId` e funziona. Fra le nove ce n'e' una di natura diversa: `ForgotPassword.vue:54` punta a un id
che nel file **non esiste**. E' il difetto `VDF25`, e il punto che lo chiude e' `TSR08`; il conto lo
rifa' `./scripts/check-label-targets.sh`.

### `F5`, `F9` — il rilievo che segnala l'unica pagina giusta

Da chiudere **scartando il rilievo**, non toccando la pagina: `<Head :title>` è il modo di Inertia, e
`Unauthorized.vue` lo usa. Ma `F9` dice che **le altre undici pagine non hanno titolo**: in un pannello
amministrativo ogni scheda del browser recita «Identity Provider», e chi ne apre tre non sa quale è
quale. È un lavoro diverso da questo rilievo, e più utile.

### Codice cancellato

L'`@import` da `app.scss`, se si scegli la seconda uscita di `F4`.

## 4. Da decidere

### Vincoli

- ~~**`D1`**~~ — **risposta del 2026-08-20: strada (b).** I file appartengono **a chi ha lanciato** lo
  script o il compose: l'utente del developer in locale, un utente di servizio in remoto. **Su
  GitHub Actions e Ansible non si fa niente**: là l'albero è di un checkout usa-e-getta, e l'uid di chi
  scrive non interessa a nessuno.
- ~~**`D2`**~~ — **risposta: si toglie l'`Head` di Inertia e si usa un titolo. `D4` ha però cambiato la
  risposta, e va detto invece di eseguirla alla lettera**: l'`Head` non era rotto, era un `<title>` non
  marcato `inertia` nel blade (§ «Il titolo che non arrivava»). Corretto quello, `<Head>` funziona, e
  **toglierlo peggiorerebbe**: sarebbe l'unico modo che ha una pagina di dire il proprio titolo, e
  `F9` chiede l'opposto — che tutte lo dicano. Quindi `Unauthorized.vue` resta com'è, il rilievo `F5`
  si scarta come previsto, e il titolo si dà **anche** dal server con la funzione `title`, che copre le
  pagine senza `<Head>`. Se il developer vuole comunque togliere `<Head>`, va detto qui: è una
  decisione, non un residuo.

### Conflitti

- ~~**`D3`**~~ — **risolta dal developer il 2026-08-20: l'`@import` è stato rimosso.** Verificato:
  `grep googleapis resources/sass/app.scss` non trova più niente. Il punto `TSR04` decade, e resta da
  guardare **una** cosa: se il carattere Raleway serve ancora, ora nessuno lo carica.

### Ignoto

- ~~**`D4`**~~ — **risolta il 2026-08-20 misurando: erano due `<title>` nell'`head`.** Il titolo del
  blade non era marcato `inertia`, quindi Inertia gliene affiancava un secondo e il browser onorava il
  primo. Corretto e verificato in `TSR05`; la diagnosi e' `TSR07`.

