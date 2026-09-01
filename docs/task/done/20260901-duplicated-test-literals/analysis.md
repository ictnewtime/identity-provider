# Letterali ripetuti nei test del master token — analisi

Sigla degli ID: **`TDL`**. È una micro-analisi: il rilievo è piccolo, e non merita più spazio di
quanto ne occupi il lavoro.

## 1. Obiettivo

I cinque rilievi di SonarQube sui letterali ripetuti devono sparire, e i due file di test devono
dire **dove** si va a bussare e **con che identità** in un posto solo. Alla fine, cambiare la rotta
di scambio o l'indirizzo IP di prova si fa in una riga, non in ventitré.

Adesso, perché quei due file sono appena cresciuti: la `v2` dello scambio token è arrivata insieme
al task `TMT`, e con lei sedici copie dello stesso percorso.

## 2. Situazione attuale

Le cinque voci di SonarQube, contate a mano nel repository e coincidenti al numero:

| Letterale | File | Quante |
|---|---|---|
| `"1.2.3.4"` | `tests/Feature/Auth/SessionRevocationTest.php` | 19 |
| `"1.2.3.4"` | `tests/Feature/Auth/TokenRefreshTest.php` | 4 |
| `"/api/v2/token/exchange"` | `tests/Feature/Auth/SessionRevocationTest.php` | 16 |
| `"/api/v2/token/exchange"` | `tests/Feature/Auth/TokenRefreshTest.php` | 4 |
| `"/api/v1/token/exchange"` | `tests/Feature/Auth/SessionRevocationTest.php` | 6 |

Comando che li produce: `grep -rc '"1\.2\.3\.4"' tests/`, e gli altri due uguali.

**Nel repository esistono già due modi di fare questa cosa**, e la scelta fra i due è l'unica cosa
da decidere:

- una **costante privata** nella classe di test — `tests/Feature/Auth/TokenRefreshTest.php:22`
  (`private const PROBE_URI`) e `IdpCompositionTest.php:29`;
- una **variabile d'ambiente** dichiarata in `phpunit.xml:50-53` (`TEST_IP_ADDRESS`,
  `TEST_USER_AGENT`, e due indirizzi alternativi), letta con `env(...)` in **7** file di test.

Nessun breaking change: si tocca solo codice di test, e i test o passano o non passano.

## 3. Analisi

Il lavoro è una sostituzione, ma i due letterali **non sono la stessa cosa** e non vanno trattati
insieme.

`"1.2.3.4"` è un **dato di prova**: un indirizzo IP che vale quel che vale. Per questo esiste già
`TEST_IP_ADDRESS` in `phpunit.xml`, usato da sette file. Metterne una copia locale in questi due
aggiungerebbe un terzo modo di dire la stessa cosa.

`"/api/v1/token/exchange"` e `"/api/v2/token/exchange"` sono invece un **contratto**: sono le due
rotte dell'IdP, e il fatto che siano due è il punto di metà dei test di quei file. Una costante con
un nome che dice quale versione è (`EXCHANGE_V1`, `EXCHANGE_V2`) si legge meglio di una stringa
ripetuta, e soprattutto rende visibile a colpo d'occhio quale versione sta provando un test.

**Alternativa vista e scartata**: una sola costante `EXCHANGE` con la versione interpolata. Nasconde
proprio la differenza che quei test esistono per provare.

## 4. Da decidere

**Vincoli**

- **D1** — Per `"1.2.3.4"`: si usa `env("TEST_IP_ADDRESS")` come già fanno sette file (e allora il
  valore diventa `127.0.0.1`, quello dichiarato in `phpunit.xml`), oppure una costante privata che
  conserva `1.2.3.4`?
  → **Deciso il 2026-09-01: costante privata, valore `1.2.3.4` invariato.** Nessun dato di prova
  cambia: è una sostituzione, non una modifica al comportamento dei test.

**Conflitti**

- **D2** — Le costanti delle due rotte stanno in ognuno dei due file, o in un posto condiviso? Un
  posto condiviso vuol dire una classe base o un trait, cioè un file nuovo per due costanti.
  → **Deciso il 2026-09-01: in ognuno dei due file.** Niente file nuovo: i due test non condividono
  altro, e una classe base per due costanti costa più di quel che rende.

**Ignoto**

- **D3** — Quando si tocca la riga per dare un nome a `"1.2.3.4"`, si dà un nome anche a
  `"phpunit"` che le sta accanto? Contato: **19 e 4 volte**, esattamente come `"1.2.3.4"`, perché
  stanno nella **stessa chiamata**. SonarQube non l'ha segnalato — o non è stato incollato, o la
  regola l'ha saltato — ma la riga la si sta riscrivendo comunque.
  → **Deciso il 2026-09-01: no.** `"phpunit"` non e' conteggiato da SonarQube come rilievo che
  blocca il controllo di qualita', quindi non c'e' un motivo per toccarlo adesso. Se un giorno
  comparisse, il punto `TDL04` e' gia' scritto.

  *Chiarimento, perché la domanda era stata posta male*: **sì, SonarQube analizza anche `tests/`**.
  Lo dice la configurazione — `.github/workflows/deploy-staging.yml:41` passa `-Dsonar.sources=.`,
  la radice intera — e lo dimostrano i cinque rilievi, che stanno tutti in `tests/Feature/Auth/`.

## 5. Consigli

- **D1** — **Costante privata, con il valore `1.2.3.4` invariato.** Cambiare il valore in
  `127.0.0.1` non è una sostituzione: è un cambio di dato in venti asserzioni, e `1.2.3.4` è stato
  scelto perché **non** è un indirizzo locale — in quei test l'IP finisce nella riga di sessione, e
  distinguerlo da `127.0.0.1` aiuta a leggere cosa è successo.
- **D2** — In ognuno dei due file. Un file nuovo per due costanti costa più di quel che rende, e i
  due file di test non condividono altro.
- **D3** — Sì, `"phpunit"` insieme agli altri: sta nella stessa chiamata, e lasciarlo indietro
  significa riaprire gli stessi file fra un mese.
