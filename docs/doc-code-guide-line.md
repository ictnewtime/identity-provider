# Linee guida di codice — decisioni non ovvie

Ogni voce esiste perché **qualcuno rischiava di disfarla in buona fede**: sono i punti in cui il
codice sembra sbagliato e non lo è.

Vicini: [SETUP.md](SETUP.md) prepara l'ambiente · [TEST.md](TEST.md) esegue i test ·
[post-deploy.md](post-deploy.md) è la checklist di rilascio.

---

## `autocomplete="new-password"` sui componenti `<Password>` di PrimeVue

**Non si toglie.** Se lo trovi su un `<Password>` e sembra fuori posto, è lì apposta.

**A cosa serve**: **impedire l'autocompletamento** del campo — il browser non deve suggerire né
inserire le vecchie password salvate.

**Perché lo si fa**: perché quei form **creano o aggiornano una password nuova**, e ne hanno più di un
campo: `password` + `ripeti password`, oppure `password attuale` + `nuova password` +
`ripeti nuova password`. Senza questa distinzione il browser vede tre campi di tipo `password` e non
sa quale sia quale: riempie con la credenziale salvata anche i campi destinati alla password nuova, e
chi compila si ritrova la password attuale precompilata dove doveva scriverne una diversa.

`new-password` dice «questo campo è per una password nuova: non riempirlo con quella salvata» — e in
più abilita il gestore di credenziali a proporne una generata.

La coppia si usa insieme, ed è per quello che funziona:

| Campo                                                          | Valore                            |
| -------------------------------------------------------------- | --------------------------------- |
| password attuale, e il campo password di un form di **accesso** | `current-password`                |
| nuova password, e la sua **ripetizione**                        | `new-password` — su **entrambi** |

**Perché non `off`**: i browser ignorano deliberatamente `autocomplete="off"` sui campi password, per
non lasciare che un sito disabiliti i gestori di credenziali. `new-password` è il valore che
rispettano, ed è l'unico modo di ottenere quel comportamento.

**Dove sta oggi**, e sono i due soli posti del progetto:

| File:riga                                      | Campo                   | Forma                        |
| ---------------------------------------------- | ----------------------- | ---------------------------- |
| `resources/js/components/UserForm.vue:454`     | `password_confirmation` | prop di `<Password>`         |
| `resources/js/components/ProviderForm.vue:376` | `secret_key`            | dentro `pt.pcInputText.root` |

Il secondo è un **uso affine, e va saputo**: `secret_key` non è una password ma la chiave segreta di
un provider, resa con `<Password>` solo per mascherarla a schermo. Lì non c'è una password nuova da
distinguere da una attuale — l'attributo è usato per lo stesso effetto, cioè impedire che il browser
ci suggerisca dentro una credenziale salvata. Chi lo legge non deve dedurne che quel campo sia una
password.

**Perché è scritto qui**: SonarQube segnala l'occorrenza di `UserForm.vue:454` come «DOM elements
should use the "autocomplete" attribute correctly», perché la vede passata come prop del componente
invece che sull'`<input>`. **È un falso positivo con una conseguenza reale**: chi lo "corregge"
togliendo l'attributo introduce il comportamento che l'attributo preveniva. Il rilievo va soppresso
nella configurazione di SonarQube, non nel codice Vue.

### La motivazione da incollare in SonarQube

Vale per **tutte** le occorrenze. Marcare il rilievo come *False Positive* e incollare questo testo:

> **Falso positivo: l'attributo è intenzionale e non va rimosso.**
>
> `autocomplete="new-password"` serve a **non far suggerire al browser le vecchie password salvate**.
> Si usa sul campo della password nuova, su quello che la ripete, e su campi di form affini in cui una
> credenziale salvata non deve essere proposta né inserita automaticamente.
>
> `autocomplete="off"` non è un'alternativa: i browser lo ignorano deliberatamente sui campi password,
> per non lasciare che un sito disabiliti i gestori di credenziali.
>
> Qui l'attributo è passato come prop del componente PrimeVue `<Password>`, ed è il motivo per cui la
> regola non lo vede sull'`<input>`. Rimuoverlo reintrodurrebbe il suggerimento automatico che
> l'attributo previene.
>
> Motivazione estesa in `docs/doc-code-guide-line.md`.

> Le due forme — prop di `<Password>` e `pt.pcInputText.root` — **non sono equivalenti**: la seconda
> instrada esplicitamente l'attributo sull'input interno, ed è probabilmente il motivo per cui
> SonarQube segnala solo la prima. Se un giorno le si volesse uniformare, la direzione giusta è
> portare `UserForm` alla forma di `ProviderForm`, mai togliere l'attributo.

Traccia: `TSA05` e `TSA05b`, entrambi **scartati**, in
[static-analysis-findings-v1](task/todo/20260812-static-analysis-findings-v1/action-plan.md).

### Dove manca, e sono i posti in cui servirebbe di più

Sette `<Password>` su nove non dichiarano niente — e la coppia, per funzionare, va dichiarata
**intera**: metà attributo non distingue niente.

| Form                        | Campi                                                | Cosa c'è          | Cosa servirebbe                                                                                                           |
| --------------------------- | ---------------------------------------------------- | ----------------- | ------------------------------------------------------------------------------------------------------------------------- |
| `ForcePasswordChange.vue`   | `current_password` `:78` · nuova `:115` · ripeti `:184` | niente            | è **esattamente** il caso a tre campi: `current-password` sul primo, `new-password` sugli altri due                        |
| `ResetPassword.vue`         | password `:73` · ripeti `:144`                        | niente            | `new-password` su entrambi                                                                                                 |
| `UserForm.vue`              | password `:312` · ripeti `:454`                       | solo sul secondo  | `new-password` **anche** sul primo: la coppia è dichiarata a metà                                                          |
| `Login.vue`                 | password `:100`                                       | niente            | `current-password` — qui l'assenza è la meno dannosa, perché su un form di accesso le euristiche del browser bastano       |

I primi tre sono form dove la password nuova si scrive due volte: **è il caso che l'attributo esiste
per risolvere**, e senza di lui il browser suggerisce la credenziale salvata dove va scritta una
password diversa. Solo `UserForm.vue:312` è registrato come difetto (`VDF04` in
[vulnerability.md](task/vulnerability/vulnerability.md)); gli altri due form **non hanno un punto
aperto**, ed è un buco di questo elenco, non una scelta.
