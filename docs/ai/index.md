# Indice della procedura AI

Si legge **a ogni prompt**, ed è l'unico: il resto si apre quando una riga qui sotto lo dice.

## Regole sempre attive — testo in `.claude/rules/`, perché e vincoli in [full/rules.md](/docs/ai/full/rules.md)

| #   | Regola                                                                                                                                                                                                |
| --- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| R0  | **Niente resta solo in chat**: analisi, decisioni, dubbi e rischi finiscono in un file. Se non trovano casella, manca la casella                                                                      |
| R1  | **Mai scrivere sui sistemi reali**: le letture sono permesse, le scritture le esegue il developer. I **database** solo su approvazione esplicita, volta per volta                                     |
| R2  | Niente azioni distruttive senza conferma. **Il versionamento è del developer**: git in sola lettura                                                                                                   |
| R3  | Identificatori, **nomi di file/cartelle** e **messaggi di commit** in inglese; contenuto dei documenti in italiano                                                                                    |
| R4  | Scope minimo: si modifica quello che è stato chiesto, nello stile del file                                                                                                                            |
| R5  | La gestione degli errori sta in una funzione dedicata, non dentro il business                                                                                                                         |
| R6  | Segreti e dati personali non finiscono in documenti, commenti, commit o log: si scrive il **nome** della variabile                                                                                    |
| R7  | **A fine iterazione** quello che è emerso sulla meta-doc si **accumula** in [full/backlog.md](/docs/ai/full/backlog.md), una riga in append. Non si rilegge, non ha contatori                         |
| R8  | Ogni regola "da applicare sempre" si scrive **subito**: testo in `.claude/rules/`, spiegazione in **`full/`** — in `abstract/` solo se il developer lo dice                                           |
| R9  | Un file **generico** non nomina tecnologie, servizi o comandi **di questo progetto**. I meccanismi dello strumento sì                                                                                 |
| R10 | **Nessuna implementazione senza piano approvato**: alla richiesta si risponde col piano, non col codice                                                                                               |
| R17 | **Prima del piano viene l'analisi**: `analysis.md` in 5 sezioni — obiettivo · fatti · alternative · **da decidere** · consigli. Si salta solo se il developer lo chiede e `skip-analysis` è `enabled` |
| R11 | La documentazione **si può spostare**: `mv`, riferimenti aggiornati nella stessa modifica, niente perso                                                                                               |
| R12 | Ogni risposta si chiude con il **riepilogo facilitato**: `[x]` fatto · `[ ]` aperto · `[?]` decidi tu, più **2 lavori fra cui scegliere e il difetto più grave aperto**                               |
| R13 | Se qualcosa di **importante** non torna: fermarsi, tornare indietro, ragionare. Se è piccolo: annotarlo e proseguire                                                                                  |
| R14 | Alla **chiusura della giornata** si propone il messaggio di commit, **in inglese**, citando gli ID chiusi. Il commit lo fa il developer                                                               |
| R15 | **Report dei ragionamenti** — interruttore: **`spento`**. Se acceso _e_ l'iterazione **modifica o pianifica** la meta-doc, si scrive un report in [reports/](./reports/index.md)                      |
| R16 | **Onestà sul risultato**: se i test falliscono si riporta l'output, se una fase è saltata si dice. Vale anche per i **numeri**: una cifra dichiara il comando che la produce                          |

## Com'è indicizzata `docs/ai/`

**Quattro cartelle e questo file**, niente altro al primo livello — e un controllo lo verifica.

| Cartella                           | Cosa c'è                                                                                                            | Si apre                                       |
| ---------------------------------- | ------------------------------------------------------------------------------------------------------------------- | --------------------------------------------- |
| [abstract/](./abstract/index.md)   | le **sintesi**: dove va un documento, come si cita una voce, come si legge il grafo, una foglia per fase            | **sempre**: è l'unica sul cammino di lettura  |
| [full/](./full/index.md)           | **tutto il resto** — il perché delle regole, i registri, i modelli, i gemelli `*-custom`, il registro di migrazione | **mai da sé**: solo se il developer lo indica |
| [dev-guide/](./dev-guide/index.md) | cosa fa **una persona**, e i settaggi che governano l'agente                                                        | **mai da sé**: se ne legge solo l'indice      |
| [reports/](./reports/index.md)     | un file per iterazione, mai modificato dopo (R15)                                                                   | **mai da sé**                                 |

**`full/` è chiusa anche per i registri che contiene**, ed è la scelta che costa: `full/backlog.md`
accumula quello che emerge sulla meta-doc e **non si rilegge**. Si apre quando il developer ha tempo.

**Le fasi**, a ogni modifica di codice: **F1 analisi e piano — blocca** → F2 implementazione → **F3
perf/leak se si tocca un service** → F4 test → F5 chiusura → F6 requisiti, F7 fuori dal repo, F8
rilievi. Chiudendo: dichiarare le fasi applicate e quelle saltate col perché, controllare R0, e il
riepilogo di R12. Il lavoro di prodotto sta in [task/](/docs/task/index.md).

**Chi risponde del risultato è il developer.** La procedura riduce gli errori dell'agente, non li
elimina: può saltare un passo, ragionare male, implementare male, dichiarare fatto ciò che non è.
Quindi **si rivede tutto**, e un buco trovato in revisione si chiude due volte — nel codice, e con una
riga di meta-doc perché non si riapra. È così che migliora il progetto.
