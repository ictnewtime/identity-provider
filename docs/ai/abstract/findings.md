# Rilievi — quello che si nota e non si risolve adesso

Quattro caselle, e sbagliarle è il modo più rapido per non far leggere più nessuno dei registri.

| Cosa hai trovato | Dove va |
|---|---|
| un difetto o un rischio in ciò che **esiste** | `vulnerability.md` dell'ambito |
| qualcosa che **manca**: test, un caso non gestito, uno script | `todo.md` dell'ambito |
| codice che **funziona ma è da rimettere a posto** | `todo.md`, sezione *Note di refactoring* |
| un **dubbio sul modo di lavorare** | [full/backlog.md](/docs/ai/full/backlog.md), una riga in append — non si rilegge |
| un intervento **fuori dal repo** | [todo-manual.md](/docs/todo-manual.md) — poi lo esegue il developer |

**Una voce che non rompe niente non è un difetto**: è un miglioramento, e va nel todo. Un elenco di
bug in cui metà non sono bug smette di essere letto come urgente.

## Cosa rende una voce utilizzabile

- **Cita il codice** come `file:riga`. Un difetto senza riferimento non è verificabile e non si
  scrive.
- **Livello** `alto`/`medio`/`basso`, misurato sulla **conseguenza in esercizio** — perdita di dati >
  blocco silenzioso > rumore — non sulla bruttezza del codice.
- **Se non esiste un fix risolutivo, la mitigazione è obbligatoria.** Una voce senza né correzione né
  mitigazione è un lamento.
- **Data presa dal sistema** (`date +%F`), non a memoria.
- Le voci chiuse **si spuntano, non si cancellano**.

## I rilievi sulla meta-doc si accumulano, e non si contano

Ogni voce dice **cosa è emerso**, in una riga: consiglio, conflitto, vulnerabilità della procedura. Si
aggiunge in append a [full/backlog.md](/docs/ai/full/backlog.md) a fine iterazione, e **non si
rilegge** — la lista si apre quando il developer ha tempo.

> **Fino al 2026-08-07 c'era un contatore** `n/soglia` per voce, che saliva quando un'iterazione
> metteva alla prova quel dubbio, con `⚠️` oltre soglia. È stato **tolto** con la decisione *structure
> over the logic*: un contatore sale solo se qualcuno rilegge, e la lista non si rilegge più. Toglierlo
> è stato preferito a lasciarlo scritto e fermo. Il ruolo che aveva — accorgersi che un dubbio è
> maturo — ce l'ha ora la **revisione del developer**.

