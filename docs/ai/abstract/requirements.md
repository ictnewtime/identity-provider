# Requisiti attuali

Il documento che descrive il sistema **com'è adesso**: [requirements-actual.md](/docs/requirements-actual.md).
Non a ogni commit — solo quando cambia qualcosa che descrive.

**Fa scattare l'aggiornamento**: un componente nuovo, rimosso o rinominato · un cambiamento nel modo
in cui i componenti comunicano (protocollo, topologia, contratto dei dati) · una variabile d'ambiente
nuova, rinominata o con semantica diversa · un cambiamento nel percorso principale dei dati o nel
comportamento in caso di errore.

I casi di questo progetto: [requirements-custom.md](./requirements-custom.md).

**Non la fa scattare**: refactoring interni, rinomine di variabili, correzioni che non cambiano il
comportamento osservabile, test aggiunti.

## Tre regole

1. Descrive **il presente**, non la storia: niente «prima faceva X, ora fa Y». Il passaggio sta nel
   piano.
2. Si aggiorna la **sezione esistente**, non si aggiunge un capitolo in coda che contraddice quello
   sopra.
3. Tabelle e diagrammi si allineano **insieme** al testo: un diagramma sbagliato è letto come vero.

## L'audit è un requisito, non un'aggiunta

Se la modifica **introduce una scrittura** su un dato che conta — un'entità di dominio, qualcosa che
riguarda una persona o del denaro — i requisiti devono dire **come viene tracciata**: chi ha fatto
cosa, quando, e com'era prima.

Se il progetto non ha un audit, l'uscita **non è una scusa**: è un difetto registrato, col suo
livello. Dettaglio: [audit.md](./audit.md).
