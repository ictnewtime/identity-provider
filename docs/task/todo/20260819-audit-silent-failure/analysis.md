# Analisi — l'audit che non viene scritto non lo sa nessuno

**Identificatori**: `TAS` = task audit-silent-failure

Stato: da approvare · Data: 2026-08-19

## 1. Obiettivo

Decidere, e poi scrivere, **cosa deve accadere quando un audit non riesce a scriversi**.

Oggi non accade niente: l'eccezione viene catturata, scritta nel log e la vita continua. Nasce come
`D2` del task [audit-complexity](../20260819-audit-complexity/analysis.md), dove è stata messa da parte
di proposito — un rifacimento di complessità non è il posto per cambiare cosa succede quando qualcosa
si rompe.

Perché adesso: la tabella degli audit è **ciò che si guarda quando si deve ricostruire chi ha fatto
cosa**. Un buco in quella tabella non si nota mai al momento; si nota il giorno che serve, e quel giorno
è troppo tardi.

## 2. Situazione attuale

| # | Fatto | Prova |
|---|---|---|
| F1 | `logAudit()` è avvolto per intero in `try { … } catch (\Exception $e) { Log::error("CRASH AUDIT (ignorato): " . $e->getMessage()); }` | `app/Traits/CustomAuditable.php:121-123` |
| F2 | Il metodo gira sugli eventi `created`, `updated`, `deleted` di **sei** modelli: `User`, `Provider`, `Role`, `Session`, `Parameter`, `ProviderUserRole` | `bootCustomAuditable()` |
| F3 | La scrittura è un `DB::table("audits")->insert([...])` **fuori** dalla transazione di chi ha provocato l'evento: se l'insert fallisce, l'operazione dell'utente **riesce comunque** | `CustomAuditable.php`, `DB::table(...)->insert` |
| F4 | Non c'è nessun test che copra il caso: `logAudit` non ha test del tutto | `grep -rln audit tests/` — ci sono `AuditListTest`, `AuditSearchFilterTest`, `AuditSortOrderTest`, tutti sulla **lettura** |
| F5 | La stessa abitudine esiste nel deploy, e lì è già un difetto registrato: `php artisan l5-swagger:generate \|\| echo "…proseguo comunque."` — `VDF07`, con la sua conseguenza («un guasto silenzioso che ha già la sua procedura manuale è un guasto che si accetta di avere») | `entrypoint.sh:35`; `VDF07` nel registro |

### Dipendenze e breaking change

- **Qualunque scelta diversa da «si lascia» è un cambiamento di comportamento in un percorso di
  scrittura**: se l'audit può fermare l'operazione, un guasto della tabella `audits` diventa un guasto
  dell'applicazione. È esattamente la ragione per cui quel `catch` è stato scritto.
- **Non è una decisione tecnica**: è una decisione su cosa il prodotto promette. «Ogni scrittura è
  tracciata» e «l'applicazione funziona anche se la traccia non si scrive» sono due promesse diverse, e
  oggi il codice fa la seconda senza dirlo da nessuna parte.

## 3. Analisi

### Le quattro strade

| Strada | Cosa comporta | Perché sì / perché no |
|---|---|---|
| **(a) Lasciare così** | niente | È la scelta attuale, e per un audit di comodo sarebbe legittima. Ma allora va **scritta** — in `project-analysis.md` — o al primo controllo qualcuno darà per buono che la tabella sia completa |
| **(b) Lasciare, ma rendere visibile il buco** | il log resta, e si aggiunge un modo di **contare** i fallimenti: un contatore, o un livello `critical` invece di `error` | Non cambia il comportamento e toglie l'invisibilità. È il minimo che rende la scelta (a) onesta |
| **(c) Far fallire l'operazione** | l'eccezione risale, e la scrittura dell'utente si annulla | È l'unica strada che rende vera la promessa «ogni scrittura è tracciata». Ma trasforma un guasto della tabella degli audit in un blocco totale: se `audits` si riempie o si corrompe, l'applicazione si ferma |
| **(d) Coda o riserva** | l'audit non riuscito finisce in una coda o in un file, e si riprova | Rende la promessa quasi vera senza bloccare. Costa un'infrastruttura che questo progetto non ha ancora (`ShouldQueue` esiste per i listener, non per gli audit) |

### La domanda che decide

Non è «quanto costa»: è **cosa promette il prodotto**. Se gli audit servono a rispondere a «chi ha
cancellato questo utente?» in una verifica, la risposta non può essere «di solito lo sappiamo». Se
servono da comodità di lettura, (a) va bene — ma va detto.

### Cosa si può fare senza decidere niente

Il test che oggi non c'è (`F4`). Un caso che rende il buco **visibile** — un audit che fallisce, e
l'operazione che riesce lo stesso — non cambia il comportamento e fissa nero su bianco quale dei due
comportamenti il codice ha. Qualunque strada si scelga dopo, quel test serve: è la fotografia da cui si
parte.

## 4. Da decidere

### Vincoli

- **`D1`** — quale strada: **(a)**, **(b)**, **(c)** o **(d)**? È una decisione di prodotto, non di
  codice.

### Conflitti

- **`D2`** — se la risposta è (a) o (b), lo stesso criterio vale per `VDF07`? Sono la stessa abitudine
  in due posti, e due criteri diversi sullo stesso tema sono la premessa di una discussione che si
  rifà ogni volta.

### Ignoto

- **`D3`** — è mai accaduto? Il log direbbe `CRASH AUDIT (ignorato)`. Se in esercizio non compare mai,
  la scelta è più libera; se compare, c'è già un buco da spiegare — e lo si può cercare **adesso**, con
  un `grep` sui log di staging.

## 5. Consigli

- **`D3` prima di tutto**: cercare `CRASH AUDIT` nei log di staging costa un comando e cambia la
  discussione. Decidere cosa fare di un guasto che non è mai avvenuto è diverso dal decidere cosa fare
  di uno che avviene ogni giorno.
- **`D1` → (b), e non per prudenza**: (c) è giusta in teoria e in pratica trasforma la tabella degli
  audit in un componente da cui dipende la scrittura di sei modelli — con `VDF15` ancora aperto, quella
  tabella non è il posto dove metterei un blocco. (b) toglie l'invisibilità, che è il difetto vero:
  oggi nessuno **sa** se ci sono buchi.
- **`D2` → sì, lo stesso criterio.** Un fallimento inghiottito è la stessa scelta sia che riguardi un
  audit sia che riguardi la documentazione OpenAPI. Se si accetta, si accetta scrivendolo; se non si
  accetta, si corregge nei due posti.
- **Il test di `F4` si può scrivere prima di qualunque decisione**, e conviene: fissa cosa fa il codice
  oggi, così la decisione si prende guardando un fatto invece che una ricostruzione.
