# Analisi — literali duplicati nelle annotazioni OpenAPI

**Identificatori**: `TOA` = task openapi-annotations

Stato: da approvare · Data: 2026-08-12 · Tranche **v3** di 4 —
[v1](../../done/20260812-static-analysis-findings-v1/analysis.md) · [v2](../20260812-static-analysis-findings-v2/analysis.md) · [v4](../20260812-static-analysis-findings-v4/analysis.md)

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

### Vincoli

- **D1** — le costanti di `F6`–`F8` vanno nel controller base insieme alle `OA_DESC_MSG_*` (`F9`), o
  preferisci una classe dedicata alle annotazioni per non far crescere `Controller.php`?

### Conflitti

- **D2** — la doppia dichiarazione dei percorsi (`F10`): la si accetta come costo di `swagger-php`, o
  vuoi un controllo che verifichi che ogni percorso annotato corrisponda a una rotta esistente? Il
  secondo è un lavoro suo, e chiude un difetto che questi rilievi hanno solo sfiorato.

### Ignoto

- **D3** — come si verifica che la documentazione generata sia **identica** prima e dopo? Se
  `php artisan l5-swagger:generate` produce un file versionabile, il confronto è un `diff` ed è la
  verifica migliore di tutto il piano. Non ho controllato se in questo progetto quel file esiste.

## 5. Consigli

| Domanda | Raccomandazione |
|---|---|
| **D1** | Nel controller base, accanto alle `OA_DESC_MSG_*`. Sono dodici righe e stanno dove chi legge le cerca; una classe nuova per dodici costanti aggiunge un file e una decisione a chi arriva dopo. Se `Controller.php` crescerà, si sposta il blocco intero — è un `mv`, non un problema da prevenire adesso. |
| **D2** | Aprirlo come task suo e **non** infilarlo qui. Ma dirlo: il rilievo che hai in mano nasconde una duplicazione più cara di quella che segnala, e chiuderlo senza annotarla la rende invisibile. |
| **D3** | Verificarlo **per primo**: se il `diff` dello specifico generato è disponibile, ogni punto di questo piano passa da `man` ad `auto` e il task diventa quasi gratuito da controllare. È il singolo accertamento che cambia di più il valore del piano. |

Il piano: [action-plan.md](./action-plan.md).
