# Controllo performance e leak

**Quando**: a **ogni** creazione o modifica di un service. Nessuna eccezione — è una policy
dell'organizzazione, e un hook impedisce al turno di chiudersi senza.

**Come**: `/check-perf`, che lo esegue in un **contesto separato**. Non è pulizia: chi ha appena
scritto il codice lo rilegge con gli occhi con cui l'ha scritto.

| # | Controllo | Cosa si verifica |
|---|---|---|
| 1 | **Query N+1** | eager loading sulle relazioni serializzate; nessuna query dentro un ciclo o dentro una risorsa di API |
| 2 | **Data leakage** | le risposte espongono **solo** i campi necessari: niente `subscription`, `phone`, `coupon_code`, `permissions`, `metadata`, `token` verso ruoli non autorizzati |
| 3 | **Scope / tenant** | query filtrate per proprietario/progetto/organizzazione: nessun accesso trasversale |
| 4 | **Memoria e streaming** | payload e file grandi in streaming; nessuna lettura non paginata su tabelle che crescono |
| 5 | **Query non vincolate** | sempre limite o paginazione, e indice sulle colonne su cui si filtra |

**Trasversale**: ogni chiamata di rete ha un **timeout esplicito**.

## Se il progetto non è un'API con ORM

**Non decade: si traduce.** Una chiamata di rete dentro un ciclo *è* un N+1, un dato personale in un
log *è* un leak, una lettura senza finestra *è* una query non vincolata. La traduzione di questo
progetto sta in [perf-leak-custom.md](./perf-leak-custom.md) e si legge **prima** della checklist.

**Il rischio vero non è saltare il controllo: è timbrarlo** — rispondere «nessuna query N+1» perché
non ci sono query, senza guardare l'equivalente. Per questo l'esito si dichiara **voce per voce**, e
ogni «non applicabile» porta il suo **perché**.
