---
full: ../full/migration-register.md
full-checked: 2026-08-07
---

# Dove è finito un pezzo spostato

Il **registro di migrazione** tiene una riga per ogni **sezione** della meta-doc: dove stava, dove è
andata, e in quale stato è quel passaggio. È la traccia che [R11](/docs/ai/full/rules.md) impone a ogni
spostamento, in un posto solo.

**Cinque stati**, e si avanza in un senso solo:

```
censito → ricollocato → verificato → sciolto        (+ `nata qui`, per ciò che non viene da nessun posto)
```

**La regola che vincola**: un file si scioglie solo quando **tutte** le sue sezioni sono
`verificato`, e `verificato` lo mette chi ha **riletto la destinazione**, non chi ha spostato. Una
sola sezione indietro blocca l'intero file.

**Ciò che non ha destinazione resta `censito`** e apre una riga in [full/backlog.md](/docs/ai/full/backlog.md) con scritto
**perché esisteva**: quelle sezioni ci sono per un motivo, e vanno reintrodotte con un altro
meccanismo.

Il controllo `./scripts/check-migration-register.sh` conta le intestazioni della meta-doc e le
confronta con le righe del registro: non prova che una ricollocazione sia **giusta** — quello è una
rilettura umana — prova che nessuna sezione è sparita senza che qualcuno l'abbia guardata.

approfondimento: [../full/migration-register.md](../full/migration-register.md) — le 450 righe (`wc -l`) del
registro vero, più il triage delle 23 regole per *chi riesce a rispettarle*.
