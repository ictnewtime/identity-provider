---
full: ../full/where-documents-go.md
full-checked: 2026-08-07
---

# Dove va un documento

La **domanda che smista**, in ordine: rompe qualcosa che esiste → `vulnerability.md` · manca qualcosa
di deciso → `todo.md` · è un dubbio o un'idea → `backlog.md` · si fa a mano quando serve →
`manual-activity/`. Se non rientra in nessuna, **si crea la categoria** e la si scrive.

| File | Cosa contiene |
|---|---|
| `todo.md` | lavoro deciso e proposte, **non** difetti |
| `vulnerability.md` | difetti accertati con livello e mitigazione, **non** lavoro nuovo |
| `backlog.md` | dubbi e idee non ancora decise. **Non si contano**: una proposta non scade |
| `manual-activity/<argomento>.md` | procedure da eseguire a mano, coi file che servono |
| `index.md` | il nodo: instrada, non spiega |

**In radice di `docs/`**: `requirements-actual.md` (il sistema com'è **adesso**), `setup.md` (come si
prepara), `todo-manual.md` (interventi **fuori dal repo**).

**Il README di un servizio sta accanto al codice**, non in `docs/`.

**`docs/task/`**: un task per **obiettivo**, non per componente. Percorso `backlog/` → `todo/` →
`done/`, e ogni passaggio è una decisione. Un task chiuso **non si cancella**: `mv` in `done/`. Una
lista che supera i **5 punti** diventa un task.

**Un'entità esterna ha una cartella**, non un file: se di quella cosa esistono più documenti che si
leggono insieme, è una cartella.

**Spostare è permesso** (`R11`): `mv`, tutti i riferimenti aggiornati **nella stessa modifica**,
niente perso senza dirlo. Se sono più file, passa da un piano.

approfondimento: [../full/where-documents-go.md](../full/where-documents-go.md) — perché queste
famiglie, cosa fare quando il contesto manca, e cosa si trova arrivando su un progetto già avviato.
