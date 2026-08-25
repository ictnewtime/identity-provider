# Piano — l'audit che non viene scritto

Sigla dichiarata dall'analisi: `TAS` — qui non si ridichiara.

Stato: **da approvare** · Data: 2026-08-19 · Analisi: [analysis.md](./analysis.md), che questo piano
**cita e non ripete**. `F…` e `D…` puntano lì. V — `auto`: lo stabilisce un comando · `man`: lo legge
una persona.

Il primo punto non cambia niente e va fatto per primo: senza sapere **se** il guasto avviene, la
decisione di `D1` si prende a occhio.

| ID | Stato | Punto | File toccati | Rischio | V | Come si verifica |
|---|---|---|---|---|---|---|
| TAS01 | da approvare | **`D3`** — cercare `CRASH AUDIT` nei log di staging e di produzione. Costa un comando e cambia la discussione: decidere cosa fare di un guasto mai avvenuto è diverso dal decidere cosa fare di uno quotidiano. **Lo esegue il developer**: sono log di sistemi reali | nessuno (verifica) | basso | man | si sa quante volte è accaduto, e con quale messaggio |
| TAS02 | da approvare | **Il test che fotografa il comportamento attuale** (`F4`): un audit che **non riesce** a scriversi, e l'operazione dell'utente che **riesce lo stesso**. Non cambia niente e si può scrivere prima di qualunque decisione: è la fotografia da cui `D1` si prende guardando un fatto. Serve qualunque strada si scelga dopo | `tests/Feature/Audit/` (nuovo) | basso | auto | verde sul codice attuale, e la sua asserzione dice a parole quale delle due promesse il prodotto mantiene oggi |
| TAS03 | da approvare | **`D1`, la scelta** — (a) lasciare e scriverlo, (b) lasciare e rendere contabile il buco, (c) far fallire l'operazione, (d) coda. Il consiglio è **(b)**: (c) mette un blocco su una tabella che ha ancora `VDF15` aperto. Il punto si specifica **dopo** `D1`: qui c'è la casella, non la soluzione | dipende da `D1` | **alto** — percorso di scrittura di sei modelli | auto | il test di `TAS02` cambia asserzione **di proposito**, e il cambiamento si legge in revisione come una decisione |
| TAS04 | da approvare | **`D2`** — allineare `VDF07` al criterio scelto, o scrivere perché no. Sono la stessa abitudine in due posti: `l5-swagger:generate \|\| echo "proseguo comunque"` nel deploy, e il `catch` degli audit. Due criteri diversi sullo stesso tema si rimettono in discussione a ogni occasione | `docs/project-analysis.md`, e ciò che `D1` decide | basso | man | il criterio è scritto una volta e nominato dai due posti |

## Cosa questo piano non copre

- **La complessità di `logAudit`**: è [audit-complexity](../../done/20260819-audit-complexity/action-plan.md),
  sigla `TAC`. Questo task non tocca la forma del metodo, solo cosa fa quando fallisce — e i due
  lavori vanno tenuti separati proprio perché il secondo è invisibile nel primo.
- **`VDF15`**: l'indice mancante che rende quella tabella più costosa. Rimandato per decisione del
  developer, e citato qui solo perché pesa su `D1`: non si mette un blocco su una tabella la cui
  scrittura non è ancora stata resa efficiente.
