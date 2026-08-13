# Analisi — literali duplicati nelle annotazioni OpenAPI

**Identificatori**: `TOA` = task openapi-annotations

Stato: da approvare · Data: 2026-08-12 · Tranche **v3** di 4 —
[v1](../../done/20260812-static-analysis-findings-v1/analysis.md) · [v2](../../todo/20260812-static-analysis-findings-v2/analysis.md) · [v4](../20260812-static-analysis-findings-v4/analysis.md)

## 1. Obiettivo

Chiudere nove rilievi «definisci una costante invece di duplicare questo literale» nelle annotazioni
`OA\*` di quattro controller, **estendendo la convenzione che il progetto ha già** invece di
inventarne una seconda.

Perché adesso: presi uno per uno sono rilievi di stile. Presi insieme dicono una cosa che conta —
il percorso di una rotta è scritto **due volte**, nel file delle rotte e nell'annotazione, e le due
copie possono divergere senza che niente se ne accorga (§ 3).

## 2. Situazione attuale

Il conteggio per file, da `grep -c` sui literali segnalati:

| # | Literale | Dove, e quante volte | Totale |
|---|---|---|---|
| F1 | `"/api/v1/providers/{id}"` | `ProviderController.php` ×3 | 3 |
| F2 | `"/api/v1/provider-user-roles/{id}"` | `ProviderUserRoleController.php` ×3 | 3 |
| F3 | `"/api/v1/roles/{id}"` | `RoleController.php` ×3 | 3 |
| F4 | `"/api/v1/users/{id}"` | `UserController.php` ×3 | 3 |
| F5 | `"Provider User Roles"` (tag) | `ProviderUserRoleController.php` ×5 | 5 |
| F6 | `"Provider id"` | `ProviderController.php` ×3 · `ProviderUserRoleController.php` ×2 · `RoleController.php` ×2 | **7, su 3 file** |
| F7 | `"Role id"` | `RoleController.php` ×3 · `ProviderUserRoleController.php` ×2 | **5, su 2 file** |
| F8 | `"User id"` | `UserController.php` ×3 · `ProviderUserRoleController.php` ×2 | **5, su 2 file** |

E il fatto che decide come si risolve:

| # | Fatto | Prova |
|---|---|---|
| F9 | Il controller base **ha già** un blocco di costanti condivise per le annotazioni, con il prefisso `OA_`, e i controller le usano come `self::OA_DESC_MSG_SECURITY_ADMIN` | `app/Http/Controllers/Controller.php:34-45`; uso in `ProviderUserRoleController.php:24` |
| F10 | Gli stessi percorsi esistono, senza il prefisso `/api/v1`, nel file delle rotte: sono **due dichiarazioni indipendenti** della stessa cosa | `routes/web.php` (blocco delle rotte `api/v1`) |
| F11 | Le descrizioni dei parametri (`"Provider id"`, `"Role id"`, `"User id"`) sono **inglese rivolto all'utente finale** della documentazione API, non identificatori | i tre literali di `F6`–`F8` |

### Dipendenze e breaking change

- **Nessun breaking change sul comportamento**: le annotazioni non sono eseguite, sono lette da
  `zircote/swagger-php` per generare lo specifico OpenAPI (`composer.json`, `darkaonline/l5-swagger`).
- **Il rischio reale è un altro**: una costante scritta male non rompe un test, rompe la
  **documentazione generata**, che nessun test guarda. Serve un modo di verificarlo (`D3`).
- Le costanti in un attributo PHP devono essere **espressioni costanti**: `self::X` e
  `self::X . "/{id}"` sono ammesse, una chiamata di funzione no. Vincola la forma della soluzione.

## 3. Analisi

**La convenzione esiste già, e questo cambia la risposta giusta.** `F9` è il fatto che ho cercato
prima di proporre qualsiasi cosa: `Controller.php` tiene un blocco `OA_DESC_MSG_*` di messaggi
condivisi, e i controller vi accedono con `self::`. Quindi non c'è una decisione di stile da prendere
— c'è un posto dove queste costanti vanno, ed è lo stesso. Alternativa scartata: una `private const`
per file, che è la lettura letterale del rilievo.

E la lettura letterale è **sbagliata proprio sui casi che contano di più**. `F6`, `F7` e `F8` non
sono duplicazioni dentro un file: `"Provider id"` compare sette volte su **tre** file. Una `private
const` per file chiude i tre rilievi e lascia **tre copie** della stessa stringa, una per controller
— cioè fa sparire il segnale lasciando intatto il problema che il segnale indicava. È il modo più
comune di «risolvere» un rilievo di duplicazione, ed è il motivo per cui vale la pena distinguere
`F1`–`F5` (locali, una costante di classe basta) da `F6`–`F8` (condivisi, vanno nel controller base).

**I percorsi meritano una riga in più.** `F1`–`F4` sono percorsi di rotta, e `F10` dice che gli stessi
percorsi sono già dichiarati in `routes/web.php`. Estrarre una costante nel controller toglie la
duplicazione **dentro** il file e lascia in piedi quella che fa più danno: rinominare una rotta e
lasciare la documentazione che punta alla vecchia. La soluzione completa sarebbe generare il percorso
dell'annotazione dal nome della rotta, ma le annotazioni PHP accettano solo espressioni costanti
(§ 2) e una chiamata a `route()` lì non si può scrivere. Quindi: costante per la duplicazione
interna, e la divergenza rotte/annotazioni resta **aperta e dichiarata**, non risolta di nascosto
(`D2`).

**Una forma che riduce le costanti da inventare.** I quattro percorsi hanno tutti la stessa struttura
`<collezione>` e `<collezione>/{id}`. Una costante per la collezione più la concatenazione costante
`self::PATH . "/{id}"` copre entrambi gli usi con una sola dichiarazione per controller, e mantiene
visibile che sono la stessa rotta. È ammessa in un attributo.

**Il tag di `F5` è un caso a sé, ed è il più facile**: `"Provider User Roles"` cinque volte nello
stesso file, sempre nello stesso ruolo. Una costante di classe, nessuna decisione.

**Cosa non risolvo qui.** `"Provider user role not found"`, che nella lista arrivava insieme a questi,
**non è un'annotazione**: è un messaggio restituito a runtime da tre metodi diversi. Ha conseguenze
diverse (l'utente lo legge, e non è tradotto) e sta in [v4](../20260812-static-analysis-findings-v4/analysis.md).

**Rapporto con le altre tranche.** Nessun rilievo condiviso. La sovrapposizione è a monte: quale
strumento produce questi rilievi (`D6` di `v1`), e il fatto che `TSA09` porti nel repo la
configurazione di lint **solo per JavaScript** mentre tutta questa tranche è PHP.

## 4. Da decidere

> **Risposte del developer, 2026-08-13.** Tre su tre, e la seconda ha richiesto di guardare le rotte
> vere prima di rispondere: i fatti stanno qui sotto, in `F12`–`F15`.

### Vincoli

- **D1** — le costanti condivise vanno nel controller base o in una classe dedicata? → **Nel
  controller base**: va bene far crescere `Controller.php`.

### Conflitti

- **D2** — la doppia dichiarazione dei percorsi: si accetta, o si controlla che ogni percorso
  annotato corrisponda a una rotta esistente? → **«Spiega meglio; se fattibile con uno script o un
  test sarebbe utile, ma Swagger passa dalle API e i controller servono anche le rotte web.»**

  **È fattibile, e l'obiezione sui controller condivisi non lo impedisce** — perché il controllo va
  fatto **in una direzione sola**. I fatti, verificati oggi:

  | # | Fatto | Prova |
  |---|---|---|
  | F12 | Gli stessi controller sono raggiunti da **due gruppi di rotte**: `admin/v1/…` (interne, usate dall'interfaccia Inertia) e `api/v1/…` (esterne, quelle documentate) | `php artisan route:list`: 108 rotte, 13 sotto `api/` |
  | F13 | Gli **8 percorsi annotati** corrispondono tutti a una rotta `api/v1/…` esistente | confronto fra `route:list --json` e i `path:` delle annotazioni: **nessun percorso orfano** |
  | F14 | Tre rotte `api/v1` **non** sono documentate: `provider-user-roles/has-relation`, `sessions/check`, `token/exchange` | stesso confronto, direzione inversa |
  | F15 | Il file generato **non è versionabile com'è**: `storage/api-docs/.gitignore` contiene `*`, quindi `api-docs.json` è escluso per costruzione | `git check-ignore -v storage/api-docs/api-docs.json` |

  **Perché una direzione sola.** «Ogni percorso annotato ha una rotta» è un invariante vero e utile:
  un'annotazione che punta a un percorso inesistente è documentazione che mente, e succede appena
  qualcuno rinomina una rotta. L'inverso — «ogni rotta è documentata» — **non** è un invariante:
  `F12` e `F14` mostrano che ci sono rotte interne e rotte esterne non documentate di proposito.
  Controllare anche quello produrrebbe rumore su una scelta legittima, e un controllo che segnala il
  corretto si smette di leggere.

  Il controllo quindi **non si accorge** che i controller sono condivisi, perché non guarda i
  controller: confronta due elenchi di stringhe — i `path:` delle annotazioni e le URI registrate.

- **D3** — come si confronta lo specifico prima e dopo? → **Va bene `git diff`.**
  **Con un ostacolo da togliere prima** (`F15`): `storage/api-docs/.gitignore` esclude tutto, quindi
  oggi `api-docs.json` non è versionato e un `git diff` non ha niente da confrontare. O si versiona
  quel file, o il confronto si fa su una copia salvata a mano prima delle modifiche.

## 5. Consigli

| Domanda | Raccomandazione | Esito |
|---|---|---|
| **D1** | Nel controller base, accanto alle `OA_DESC_MSG_*`: sono dodici righe e stanno dove chi legge le cerca. | **accolta** |
| **D2** | Aprirlo come task suo e non infilarlo qui. | **superata**: è fattibile e va fatto, ma **in una direzione sola** — annotazione → rotta. È un punto di questo piano, non un task |
| **D3** | Verificarlo per primo: se il `diff` è disponibile, tutto il piano passa da `man` ad `auto`. | **accolta, con un passo in più**: va prima reso versionabile il file generato (`F15`) |

Il piano: [action-plan.md](./action-plan.md).
