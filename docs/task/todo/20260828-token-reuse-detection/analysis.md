# Il master token rubato, e come accorgersene

**Identificatori**: `TRR` = task token-reuse-detection

Stato: da approvare · Data: 2026-08-28

Nato da un'osservazione del developer dentro l'analisi di
[master-token-sessions](../20260828-master-token-sessions/analysis.md) (`TMT`): «se hanno rubato il
master-token allora possono rinnovare il master token all'infinito». **E' esatto**, ed e' il motivo per
cui la rotazione va progettata insieme al modo di accorgersi del furto — sennò peggiora il problema che
vuole risolvere.

## 1. Obiettivo

**Un master token rubato smette di funzionare, e il furto si vede.** Oggi non si vede: un master token
e' valido perche' la firma regge, e nessuno chiede al database se quel token e' ancora **quello buono**.

**Il momento in cui ce ne si accorge** e' preciso e non richiede indovinare: quando un token gia'
scambiato **viene ripresentato**. Due soggetti hanno lo stesso token — uno e' di troppo.

**Perche' adesso**: `TMT17` introduce la rotazione. Senza rilevamento, la rotazione **allunga** la vita
di un token rubato invece di limitarla, e il developer lo ha notato prima di implementarla.

## 2. Situazione attuale

### Il master token oggi non ha stato: vale finche' la firma regge

`VerifyMasterToken` fa **zero** query (verificato: nessun `Session::` nel file). Decodifica con la
chiave pubblica RS256, controlla il claim `sub`, e passa. Da qui tre conseguenze:

- **non esiste una revoca**: un master token rubato vale fino alla sua scadenza, otto ore;
- **non esiste un «questo non e' piu' quello buono»**: due copie dello stesso token sono
  indistinguibili;
- il logout cancella le **righe**, non i token: chi ha una copia del token continua a passare
  `VerifyMasterToken`, e viene fermato solo piu' avanti, se e quando qualcosa guarda la sessione.

### Il pezzo che serve esiste gia', ed e' di oggi

`TMT02` ha messo il master token nella riga, in `refresh_token`. Fino al 2026-08-28 quella colonna era
**vuota per tutti** — quindi il confronto «il token presentato e' quello salvato?» oggi si puo' fare, e
prima no. E' una `where` in piu', e nessuno la fa.

### Cosa cambia con la rotazione di `TMT17`

Sulla `v2`, un master token piu' vecchio di un'ora viene rigenerato e **sovrascritto** nella riga. Senza
altro, il vecchio **resta valido** fino alla sua scadenza: la firma regge, e nessuno controlla che sia
ancora quello nella riga. Chi l'ha rubato lo scambia a sua volta, ottiene uno nuovo, e ricomincia —
**per sempre**, perche' ogni scambio riparte da otto ore.

### Il vincolo che complica tutto: una riga per utente+provider

Con il modello attuale, due dispositivi dello stesso utente **si sovrascrivono** la riga (deciso e
accettato in `TMT`). Quindi il dispositivo 1, dopo che il dispositivo 2 ha fatto login, ha in mano un
master token che **non e' piu' quello salvato** — e per un rilevamento ingenuo e' indistinguibile da un
ladro.

## 3. Analisi

### Il meccanismo, in tre righe

1. Allo scambio, il master token presentato si confronta con quello salvato nella riga.
2. Se **coincide**: si ruota — si genera il nuovo, si salva, si restituisce.
3. Se **non coincide** ed e' comunque un token valido per firma: e' un token gia' sostituito. Non e' un
   errore del chiamante, e' il **sintomo** che due soggetti hanno la stessa credenziale.

Cosa fare al punto 3 e' la decisione vera, e sta nel § 4.

### Le quattro strade, e cosa costano

| Strada | Cosa fa | Cosa costa |
|---|---|---|
| **(a)** Rotazione con rilevamento, e alla scoperta si abbatte **tutta** la sessione dell'utente | e' la difesa completa: il ladro perde l'accesso e il legittimo se ne accorge subito | falsi positivi: **ogni** caso di piu' dispositivi o di richiesta ripetuta diventa una disconnessione |
| **(b)** Rotazione con rilevamento, ma alla scoperta si **rifiuta e basta** | il ladro viene fermato; il legittimo rifa' il login | non si distingue un furto da una collisione benigna, e nessuno lo viene a sapere |
| **(c)** Nessun rilevamento, ma il master token si **lega** all'IP o allo user-agent | il token rubato non funziona altrove | l'IP cambia da solo — rete mobile, proxy, VPN — ed e' gia' il difetto `VDF18` |
| **(d)** Nessuna rotazione, e master token **piu' corto** | niente da rilevare: il token rubato vale poco | l'utente rifa' il login piu' spesso, che e' cio' da cui `TMT` sta scappando |

### La finestra di tolleranza, che decide se la (a) e' vivibile

Un client che ripete la stessa richiesta — rete lenta, retry automatico, doppio clic — presenta il
token vecchio **subito dopo** averlo scambiato. Senza tolleranza, quello e' un falso positivo garantito.
La forma usuale: il token appena sostituito resta accettabile per **pochi secondi**, e solo per
rispondere con lo stesso risultato. Serve un posto dove tenerlo — la colonna c'e' gia' se si accetta di
tenerne **due**, o basta un campo con l'istante della rotazione.

### Il conflitto con i dispositivi multipli, che non e' un dettaglio

Con una riga per utente+provider, la (a) trasforma il secondo dispositivo in un attacco: chi fa login
sul telefono butta fuori il portatile **e** fa scattare l'allarme. Le vie d'uscita sono due, e nessuna
e' gratis: o si tiene **una riga per dispositivo** — che e' la migrazione che `TMT` ha messo fuori
perimetro — oppure il rilevamento si applica **solo alla `v2`**, dove la riga e' una per utente e i
client sono le estensioni, non i browser.

**Cancellazioni**: nessuna.

## 4. Da decidere

### Vincoli

- **`D1` — cosa succede quando il riuso viene rilevato?** Abbattere tutta la sessione (a) o rifiutare e
  basta (b). La prima e' la difesa vera e costa falsi positivi; la seconda non disturba nessuno e non
  protegge il legittimo, che non sa di essere stato derubato.

### Conflitti

- **`D2` — che si fa con i dispositivi multipli?** Oggi si sovrascrivono. Con il rilevamento diventano
  indistinguibili da un furto, a meno di non tenere una riga per dispositivo — che e' una migrazione e
  un modello nuovo.
- **`D3` — quanto dura la finestra di tolleranza?** Zero secondi significa falsi positivi al primo
  retry; troppi secondi significa una finestra in cui il token rubato funziona ancora.

### Ignoto

- **`D4` — dove finisce l'allarme?** Un rilevamento che nessuno legge e' un `if` sprecato: va in
  `audits`, nei log che escono dalla macchina, o da qualche parte che qualcuno guarda davvero?

## 5. Consigli

- **`D1`: la (b) per cominciare, la (a) quando i dispositivi hanno una riga per uno.** Rifiutare e basta
  ferma il ladro **subito** e non fa danni a nessuno; abbattere tutto e' la difesa giusta ma va fatta
  quando un secondo dispositivo non somiglia piu' a un furto — sennò il primo effetto visibile e'
  disconnettere le persone che lavorano.
- **`D2`: non toccarlo qui.** E' il modello di `TMT`, e cambiarlo dentro questo task significherebbe
  farne due insieme.
- **`D3`: pochi secondi, e misurati** — dieci e' il numero che si legge in giro, ma il numero giusto
  dipende dal `IDP_REQUEST_TIMEOUT_SEC` dei client, che oggi vale 5 (estensione PHP) e 4 (Node).
  Una finestra piu' corta del timeout del client garantisce falsi positivi.
- **`D4`: `audits`.** E' la tabella che l'amministratore gia' guarda, ha l'IP e l'utente, e questa e'
  esattamente la riga che si vuole trovare quando si indaga. I log escono dalla macchina e nessuno li
  rilegge.

## 6. Implicazioni

### In positivo

- **Un token rubato smette di valere otto ore**: vale fino al primo scambio del legittimo proprietario.
- **Il furto diventa un fatto osservabile**, non un'ipotesi: c'e' una riga che dice quando e da quale IP.
- **La rotazione di `TMT17` smette di essere un rischio**: senza rilevamento allunga la vita del token
  rubato, con il rilevamento la accorcia.

### In negativo

1. **Il falso positivo e' il rischio principale, e colpisce chi lavora**: due dispositivi, una scheda
   rimasta aperta, un retry. Con la (a) il costo di un errore e' una disconnessione; con la (b) e' un
   login in piu'.
2. **Serve stato dove oggi non ce n'e'**: `VerifyMasterToken` fa zero query, ed e' il suo pregio — sta
   su ogni chiamata dell'exchange. Aggiungere un confronto significa **una query per richiesta**, su una
   colonna **senza indice** (scelta gia' presa in `TMT`).
3. **Il rilevamento vale solo dove il token si scambia**: chi usa il master token per altro — se un
   domani lo si usasse — non passa da li' e non viene controllato.
4. **La finestra di tolleranza e' una finestra vera**: nei secondi in cui il token vecchio resta valido,
   il ladro ci passa. E' il prezzo per non disconnettere chi ha la rete lenta.
5. **Un allarme che nessuno legge non protegge nessuno**: se `D4` finisce nei log e i log li guarda solo
   un servizio esterno, il furto resta una riga che nessuno apre.
