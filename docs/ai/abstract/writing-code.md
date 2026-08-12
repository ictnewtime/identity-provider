# Scrivere codice

## Prima

- **Il punto che stai per implementare è approvato?** Se no, non si comincia. Uno per volta,
  nell'ordine del piano.
- Leggere il file **per intero** e i suoi chiamanti: si scrive nello stile che c'è, non nel proprio.
- Verificare che un helper equivalente non esista già.

## Mentre

- Identificatori in **inglese**, commenti in **italiano**.
- La gestione degli errori sta in una **funzione dedicata**, non dentro quella che fa il lavoro.
- **Nessun percorso d'errore risale fino al ciclo principale** di un processo che gira di continuo:
  l'errore si instrada, non si propaga. Ogni percorso termina con una conferma o un rifiuto espliciti.
- **Ogni chiamata di rete ha un timeout esplicito.** Senza, non è lentezza: è un servizio vivo che ha
  smesso di lavorare, in silenzio.
- Configurazione da variabile d'ambiente: default esplicito, e un valore assurdo (`0`, vuoto,
  negativo) **non** deve poter significare «nessun limite».
- Nessuna dipendenza nuova senza averla motivata.
- Niente segreti né dati personali nel codice, nei commenti, nei log.

## Dopo

- Se la modifica ha cambiato l'analisi del piano, annotarlo: serve alla chiusura.
- Difetti trovati **fuori scope**: non si correggono, si registrano.
- Se hai toccato un **service**, il controllo perf/leak è obbligatorio — e il turno non si chiude
  senza: [perf-leak.md](./perf-leak.md).
