# Analisi — l'errore che il server spiega e l'interfaccia non dice

**Identificatori**: `TER` = task error-messages

Stato: da approvare · Data: 2026-08-20

## 1. Obiettivo

Far arrivare all'utente **il motivo vero** dell'errore, e dargli una frase sensata anche quando un
motivo non c'è.

Nasce da una segnalazione precisa: `UserController::delete()` risponde `json([], 404)` — un 404
**senza messaggio** — mentre il ramo accanto, quello dei ruoli associati, ne ha uno. Chi prova a
cancellare un utente inesistente legge «Si è verificato un errore durante l'eliminazione dell'utente»
invece di «Utente non trovato»: una frase che parla della cancellazione quando il problema è un altro.

Guardando il codice il difetto si è rivelato più grande della segnalazione, e in un punto diverso da
quello sospettato (§ 2).

## 2. Situazione attuale

| # | Fatto | Prova |
|---|---|---|
| F1 | `UserController` ha **tre** risposte `404` **senza corpo**: in `delete()` (riga 455), `restore()` (476) e `getUserRoles()` (551). Sono le uniche in tutto `app/Http/Controllers/` | `grep -rn 'json(\[\], *404)' app/Http/Controllers/` |
| F2 | **L'helper per farlo bene esiste già** e lo usano dodici altri punti: `Controller::notFound(string $chiave)` compone `{"message": __(chiave)}` con stato 404. E la chiave giusta c'è: `user.error.not_found` → «Utente non trovato» | `app/Http/Controllers/Controller.php:46-49`; `lang/it.json` |
| F3 | **`DeleteUserDialog.vue` legge il messaggio del server**, contrariamente al sospetto: `error.response.data.message ? … : trans("admin.users.toast.delete_user_error")`. Fa esattamente la cosa giusta — il problema è che il server non gli manda niente da leggere | `resources/js/components/user/DeleteUserDialog.vue:32-40` |
| F4 | **Ma è una minoranza.** Su **39** blocchi `catch` che mostrano un toast, **5** leggono `data.message` e **34** lo ignorano: mostrano una frase fissa, qualunque cosa il server abbia spiegato | scansione dei `catch` in `resources/js/`, corpo per corpo |
| F5 | I cinque che lo leggono: `ProviderTable`, `RoleTable`, `UserForm`, `DeleteUserDialog`, `DeleteUsersDialog`. Gli altri 34 sono sparsi su nove componenti — form, tabelle e dialoghi. **L'elenco puntuale, `file:riga` per ognuno, è il punto `TER06`**: qui c'è il conto, non la lista, e una lista scritta a mano in un'analisi invecchia al primo componente nuovo | stessa scansione |
| F6 | **Non esiste una frase generica.** `common.error` è «Errore», e serve da **titolo** del toast; il testo generico non c'è, e ogni componente ne ha uno suo specifico del caso — «errore durante l'eliminazione dell'utente», «errore durante il ripristino del parametro» | `lang/it.json`, chiavi `*.toast.*_error` |
| F7 | Il caso «il server non risponde» oggi non è distinto da «il server ha risposto male»: senza `error.response` i cinque buoni ripiegano sul loro messaggio specifico, e i 34 mostrano il proprio. In entrambi i casi l'utente legge una frase che parla dell'operazione, non del fatto che la rete è caduta | `F3`, `F4` |

### Dipendenze e breaking change

- **Il lato server è a somma zero**: dare un corpo a tre risposte 404 non cambia lo stato e non tocca
  nessun contratto — il frontend che legge `data.message` lo trova, quello che non lo legge fa come
  prima. Rischio nullo.
- **Il lato frontend è il lavoro vero**, e tocca nove componenti. Ogni modifica cambia **cosa l'utente
  legge** quando qualcosa va storto: è la parte che va decisa (§ 4), non applicata di slancio.
- **Nessun test copre nessuno di questi percorsi**: né i tre 404 lato server, né un solo `catch` lato
  frontend — e nel frontend non c'è nulla che esegua JavaScript (deciso `D1` di
  [frontend-complexity](../../done/20260819-frontend-complexity/analysis.md)).

## 3. Analisi

### Il lato server: tre righe, e l'helper c'è

`return response()->json([], 404)` diventa `return $this->notFound("user.error.not_found")` (`F2`).
Non è una scelta: è **la forma che questo repository ha già** in dodici altri punti, e quei tre sono
rimasti indietro. Con essa l'utente che cancella un utente inesistente legge «Utente non trovato», e
il dialogo — che il messaggio lo legge già (`F3`) — lo mostra senza toccare una riga di JavaScript.

### La regola: `DeleteUserDialog` è il modello, e vale per tutti i toast

**Ogni toast d'errore si comporta come `DeleteUserDialog.vue`, dove è possibile.** È il punto da cui
questa analisi parte, e conviene scriverlo come regola invece di lasciarlo dedurre da un esempio:

```js
const detail = error.response?.data?.message ?? trans("<chiave specifica del caso>");
```

Cioè: **il messaggio del server ha la precedenza**, e la frase locale è il ripiego — non il contrario, e
non l'unica cosa che si mostra. La ragione è che il server sa **perché** ha rifiutato: «utente non
trovato», «l'associazione esiste già», «l'utente ha ruoli associati» sono informazioni che un componente
non può indovinare, e che oggi butta via.

«Dove è possibile» copre due eccezioni, e sono le sole:

- il messaggio del server **non è mostrabile** — se contiene dettagli interni. Non è un'esenzione per il
  componente: è un difetto del server, e va corretto lì (`D4`, `TER05`);
- la risposta **non è arrivata**: allora non c'è nessun messaggio da preferire, e serve la frase della
  rete (§ «La frase generica»).

Fuori da questi due casi, un `catch` che ignora `data.message` sta scartando l'unica informazione
attendibile che ha in mano.

### Il lato frontend: 34 posti che ignorano ciò che il server dice

`F4` è il difetto sistemico, e nessun rilievo lo nomina. Un server che spiega bene e un'interfaccia che
non lo ascolta sono la stessa cosa di un server che non spiega — con la differenza che nei log il
messaggio giusto c'è, e sembra che tutto funzioni.

Il modello da seguire è già scritto in cinque punti (`F5`):

```js
const detail = error.response?.data?.message ?? trans("<chiave specifica del caso>");
```

Tre modi di estenderlo, e la differenza non è di stile:

| Strada | Come | Prezzo |
|---|---|---|
| **(a) Ripetere le quattro righe** nei 34 punti | come i cinque che ci sono | 34 copie di una logica che un giorno cambierà in un posto solo. È però la forma coerente con `D2` di `frontend-complexity`, dove il developer ha scelto le funzioni nel file invece di un composable condiviso |
| **(b) Una funzione condivisa** — `useApiError()` o simile | un posto solo, 34 chiamate | contraddice la decisione di `D2`, e va deciso di nuovo con questo numero in mano: 34 non è 2 |
| **(c) Un interceptor di axios** | il messaggio viene normalizzato una volta per tutte, prima che arrivi ai componenti | il più potente e il più invadente: cambia il comportamento di **ogni** chiamata, comprese quelle che oggi funzionano |

### La frase generica, che è la parte chiesta

Serve per due casi che oggi si confondono (`F6`, `F7`):

- **il server ha risposto senza messaggio**: si mostra la frase specifica del caso, come già fanno i
  cinque. Questo non richiede una frase nuova;
- **il server non ha risposto affatto** — rete caduta, timeout, 502 di un proxy: qui la frase specifica
  è fuorviante, perché parla dell'operazione. Serve una chiave nuova, del tipo
  `common.server_unreachable` → «Il server non risponde. Controlla la connessione e riprova.»

E conviene distinguere anche il **500 senza corpo**, che è «il server è rotto» e non «la tua operazione
è andata male»: `common.unexpected_error` → «Si è verificato un errore imprevisto. Riprova.»

Distinguere i due casi si può fare con precisione: `error.response` **assente** significa che la
risposta non è mai arrivata; `error.response` presente ma senza `data.message` significa che è arrivata
e non spiegava.

### Codice cancellato

Niente. Tre risposte cambiano forma, e i `catch` guadagnano una riga.

## 4. Da decidere

### Vincoli

- **`D1`** — i 34 `catch` che ignorano il messaggio: si toccano **tutti**, solo quelli delle operazioni
  distruttive (cancella, ripristina), o nessuno per ora? Il lato server (§ 3) è indipendente e si fa in
  ogni caso.
- **`D2`** — se si toccano: **(a)** quattro righe ripetute, **(b)** una funzione condivisa, **(c)** un
  interceptor? `D2` di `frontend-complexity` aveva scelto la ripetizione, ma allora i posti erano due.

### Conflitti

- **`D3`** — le due frasi generiche: i testi proposti nel § 3 vanno bene? Sono parole che l'utente legge
  nel momento peggiore, quando qualcosa è già andato storto.

### Ignoto

- **`D4`** — c'è un caso in cui il messaggio del server **non** va mostrato all'utente? Un errore di
  database che finisce in `message` esporrebbe dettagli interni. Oggi `RoleController::delete()` fa
  `response()->json(["message" => $e], 500)` — l'eccezione **intera** nel corpo — e uno dei 34 `catch`
  che lo ignorano è, per caso, ciò che protegge l'utente da quella vista.

## 5. Consigli

- **Il lato server prima, e da solo**: tre righe, rischio nullo, e chiude la segnalazione da cui il task
  nasce. Non dipende da nessuna delle domande di sopra.
- **`D1` → tutti e 34, ma non in un colpo**: un componente per punto, così una revisione può guardare
  cosa cambia sullo schermo. Un punto unico da 34 file non si rivede, si approva.
- **`D2` → (b), la funzione condivisa**, e cambio idea rispetto a `frontend-complexity` per una ragione
  aritmetica: là erano due punti e la duplicazione costava meno dell'accoppiamento; qui sono 34, e la
  logica da ripetere non è un elenco di campi ma **una decisione** — quale messaggio mostrare in quale
  caso. Trentaquattro copie di una decisione divergono.
- **`D4` → guardarlo prima di toccare i `catch`.** Se `RoleController` mette un'eccezione nel corpo,
  mostrare `data.message` all'utente diventa una fuga di dettagli interni: va corretto **lì**, e in quel
  caso il difetto è del server, non dell'interfaccia.
