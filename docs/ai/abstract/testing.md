# Test

**Quando**: sempre, dopo ogni modifica di codice. Percorsi e comandi di questo progetto:
[testing-custom.md](./testing-custom.md).

- **Unit**: la logica pura — mapping, normalizzazione, date e fusi, decisioni di controllo, parsing.
  Niente rete, niente sistemi esterni, niente filesystem reale.
- **Integration**: i confini — client di rete, chiamate HTTP, database. Restano **in sola lettura**
  sui sistemi esterni: un integration test non scrive da nessuna parte.
- **Regressione**: si corregge un bug scrivendo **prima** il test che lo riproduce e che fallisce.
  Poi il fix. Il test resta.

## Le quattro regole

1. **Un test che non è stato eseguito non conta**: si riporta il comando e il suo output.
2. Se un test preesistente fallisce per una causa **estranea** alla modifica, non si «aggiusta»: si
   segnala.
3. Se un percorso non è testabile senza scrivere su un sistema reale, non lo si testa in automatico:
   diventa un **passo manuale**, e se richiede configurazione esterna anche un intervento fuori dal
   repo.
4. Se i test di un componente **mancano o coprono troppo poco**, non si tace: la lacuna si registra.

## Provare anche che non fallisca a vuoto

Vale per i controlli, non per i test di prodotto, ed è la metà che si dimentica: un controllo si
accetta solo se **trova il caso che lo motiva** *e* **non segnala ciò che è corretto**. Un controllo
che grida sul corretto si smette di leggere, e da lì non protegge più niente.

Quello che non è automatizzabile va nella guida ai test manuali, marcato **[L]** locale, **[D]** al
deploy, o **[L+D]** — non raccontato solo in chat.
