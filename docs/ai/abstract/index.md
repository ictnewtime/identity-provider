# abstract — la meta-doc concisa, per agire

Qui sta **cosa fare**, in poche righe strutturate. È la parte che l'agente legge lavorando.

Cosa **non** ci si trova: il perché lungo, le alternative scartate, la storia e i calcoli — quelli
stanno in [full/](../full/), che non si apre salvo indicazione del developer o modifica della
meta-doc. E non ci sono le procedure del developer: quelle andranno in `dev-guide/`, che nascerà con l'Onda 6.

`todo 0 · difetti 0 · backlog 0` — i registri della procedura stanno nella [radice](../index.md).

> I costi della colonna sono righe reali, da `wc -l`, verificate il 2026-08-06 (R16).

| Se ti serve… | Apri | Non ci trovi | Costo |
|---|---|---|---|
| dove va un documento, e cosa contiene ogni famiglia di file | [where-documents-go.md](./where-documents-go.md) | come si cita una voce → `identifiers.md` | ~36 righe |
| come si cita una voce, e quale lettera usa un'area | [identifiers.md](./identifiers.md) | dove va il documento che la contiene → `where-documents-go.md` | ~41 righe |
| come si attraversa il grafo, i tetti, cosa si apre e cosa no | [graph-navigation.md](./graph-navigation.md) | dove va un documento: qui c'è **come si legge**, non **dove si scrive** | ~54 righe |

## Come si scrive una foglia qui

Tre vincoli, e il terzo è quello che si sbaglia:

1. **Sta nel tetto**: ~26 righe, che è ciò che resta del budget a questa profondità.
2. **Dichiara la sua completa** nel frontmatter (`full:` e `full-checked:`), se ne ha una.
3. **Contiene *cosa fare* per intero.** Se per agire correttamente serve aprire `full/`, il taglio è
   nel posto sbagliato e va rifatto — non compensato con un rimando.
