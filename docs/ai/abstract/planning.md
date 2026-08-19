---
full: ../full/planning.md
full-checked: 2026-08-06
---

# Il piano d'azione — si blocca, si scrive, si approva

Una richiesta di modifica **non è** un via libera a implementare. La sequenza:

```
richiesta → lettura del codice → analysis.md → action-plan.md → APPROVAZIONE → implementazione
```

## `analysis.md` viene prima (R17)

Il piano è **sintetico per costruzione**: punti, file, rischio. Il contesto che li ha generati non ci
sta, e fra una sessione e l'altra si perde — chi riprende il task legge le decisioni e non sa più su
quali fatti poggiavano. L'analisi è ciò che sopravvive alla sessione.

**Cinque sezioni numerate, in quest'ordine:**

| § | Cosa contiene | Di chi è |
|---|---|---|
| **1. Obiettivo** | cosa deve essere vero alla fine, **per esteso**, e perché adesso | il developer l'ha chiesto, l'agente lo riscrive |
| **2. Pre-analisi: i fatti** | i **punti salienti di cosa è presente**, ognuno col comando o il `file:riga` che lo dimostra | il repo. Niente ricostruzioni a memoria |
| **3. Analisi** | le **alternative viste**, anche quelle scartate subito, col perché | l'agente: è il suo ragionamento |
| **4. Da decidere** | **in fondo, una macro-sezione sola**, divisa in *vincoli · conflitti · ignoto* | il **developer**: sono le domande a cui deve rispondere |
| **5. Consigli** | per ogni domanda del § 4, la raccomandazione dell'agente | l'agente, e non sostituisce la risposta |

Il § 4 sta **in fondo e tutto insieme**: sparso nel documento si legge a pezzi e non si risponde.

Il piano **cita l'analisi invece di ripeterla** — due copie della stessa cosa sono due verità che
divergono, e la seconda divergenza nessuno la nota.

**Le specifiche che arrivano dopo aggiornano l'analisi**, e di conseguenza il piano: il contesto nuovo
appartiene al § 2 o al § 4, non alla coda del piano.

**Si salta** solo se il developer lo chiede **e** `skip-analysis` è `enabled` — default `disabled`,
lo cambia solo lui: [dev-guide/](../dev-guide/index.md).

**Ci si ferma al primo punto non approvato.** Se durante l'implementazione serve un intervento non
previsto, non lo si fa: si torna qui, si aggiunge il punto, lo si fa approvare.

Un `procedi` esplicito vale come approvazione di tutti. L'eccezione è la correzione **banale e
reversibile** — un refuso, una costante, una rinomina locale: si annuncia in una riga e si fa.

**Dove va**: `docs/task/todo/<YYYYMMDD>-<cosa-fa>/action-plan.md`. In `docs/` non restano piani
sparsi: un piano è sempre un task, e il task va nell'[indice](/docs/task/index.md) — un controllo lo
verifica.

## I punti

Colonne: `ID · Stato · Punto · File toccati · Rischio · V · Come si verifica`.

- **Un punto sta nella tabella. Sempre.** Un intervento descritto in prosa accanto alla tabella —
  una nota, un paragrafo, un capoverso «attenzione» — **non viene letto**: chi lavora scorre gli ID e
  gli stati, e la prosa la salta. Se una cosa va fatta, è una riga con un ID; se non lo è, non è un
  punto e non sta in un piano. Le uniche sezioni ammesse fuori dalla tabella sono l'intestazione, il
  perché delle onde, **cosa il piano non copre** — che dice ciò che *non* si fa, quindi nessuno
  rischia di aspettarselo — e le **dichiarazioni d'esito** imposte da una policy, come il controllo
  perf/leak: sono risultati già ottenuti, non lavoro che qualcuno deve prendere in carico.
  Il modo più comune di sbagliare non è la prosa voluta: è **una riga vuota** fra la
  tabella e la riga nuova, che nel sorgente non si vede e in Markdown butta il punto fuori. Un
  controllo lo rileva.
- **Un punto = un intervento verificabile**, col suo modo di verifica. «Migliorare la testabilità»
  non è un punto; «estrarre il client dietro un'interfaccia, così il test può iniettare un doppio» sì.
- **V** è `auto` (uno script lo stabilisce) o `man` (qualcuno lo legge). Una `man` non è più debole:
  è **lavoro di una persona**, e va programmata.
- Ordinati per **dipendenza**. Stati: `da approvare` → `approvato` → `fatto`, o `scartato` col perché.
- Le affermazioni sul comportamento attuale si **verificano nel codice** e si citano `file:riga`.

**Decisioni sopra, spostamenti sotto**, in due parti: mescolati, si approva uno spostamento prima di
aver deciso dove le cose vanno. Sui lavori grossi si divide in **onde** con un checkpoint ciascuna —
e lì il `procedi` unico toglie l'unica rete di sicurezza che hanno.

## Chiuderlo

A fine implementazione, **lo stesso file**: i punti chiusi passano a `fatto` — solo se **verificati**,
test eseguito o comportamento osservato. Le premesse rivelatesi sbagliate si correggono **dove
l'analisi era sbagliata**, non in coda. L'intestazione porta stato e data.

Il piano **non si cancella**: resta come storia della decisione. Ma la fonte di verità sul presente è
solo il documento dei requisiti.

## Le uscite dei punti

`fatto` è una **dichiarazione**. Accanto al piano sta un `outputs.md` che, per ogni punto chiuso,
nomina l'**artefatto**: `check-plan-outputs.sh` lo esegue, e a fine turno riporta cosa manca.

Una tabella `ID · Tipo · Bersaglio · Atteso`, e una riga `piano: <file>` che dice da quale file
leggere gli stati — indovinarlo fra `action-plan.md` e `waves.md` sarebbe la stessa approssimazione
che il controllo esiste per impedire. Un punto può avere più righe.

| Tipo | Passa quando | Serve a |
|---|---|---|
| `exists` | il percorso esiste | un file o una cartella creati |
| `exec` | esiste **ed è eseguibile** | uno script: uno che non parte non ha prodotto un numero |
| `absent` | il percorso **non** esiste | uno scioglimento. Su una cartella, non su un file: sciogliere e lasciare il guscio è mezzo lavoro |
| `contains` | il bersaglio contiene l'espressione regolare | una regola che dice ciò che promette. La regex sta fra la **prima coppia di backtick** di `Atteso`, `\|` per una pipe; il resto della cella è la prosa |
| `no-match` | l'espressione regolare **non** compare in nessun file sotto l'albero | un punto che **rimuove**: la sua uscita è un'assenza, e un'assenza non è un percorso. Senza questo tipo quei punti finiscono in un `absent` su un file inventato, che passa sempre |
| `moved` | `sorgente → destinazione`: sorgente assente **e** destinazione presente | [R11](/docs/ai/full/rules.md). Due righe separate si soddisfano cancellando, e si chiama spostamento |
| `man` | mai: la riga è **elencata, non verificata** | un punto con V `man`. `Atteso` porta la **ragione** dell'esclusione |

**La colonna `Bersaglio` non è la colonna `File` dei punti.** Quella dice *dove si è lavorato*, questa
*cosa deve essere vero dopo* — e per i punti che sciolgono o spostano sono opposte.

I bersagli sono **relativi alla radice del repo**, sempre, anche per un file che sta accanto al
manifesto: `action-plan.md` esiste in undici cartelle di task, e due regole di risoluzione
renderebbero ambiguo proprio il caso più comune.

Il vocabolario è **chiuso**: un'uscita non esprimibile si scrive `man` con la ragione, non si forza in
`exists`. Una riga di shell per asserzione renderebbe il manifesto codice arbitrario eseguito da un
hook a ogni turno.

**Il controllo è la copertura, non le asserzioni.** Ogni punto `fatto` con V `auto` senza riga è un
fallimento che nomina l'ID; una riga per un ID non `fatto` è un fallimento — senza, si pre-compila il
manifesto e si dichiara dopo. Un `man` su un punto `auto` è rifiutato: l'esclusione la decide il
piano, non chi compila.

**Cosa non copre**: `contains` verifica che una stringa ci sia, non che dica il vero; le righe `man`
sono elencate e nient'altro. 22 righe elencate non sono 22 righe verificate, e il conteggio finale le
tiene separate proprio per questo.

approfondimento: [../full/planning.md](../full/planning.md) — la struttura completa, gli errori
tipici, e un esempio compilato in [planning-custom](../full/planning-custom.md).
