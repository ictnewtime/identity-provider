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
[static-analysis-findings-v1](task/done/20260812-static-analysis-findings-v1/action-plan.md).

### Dove manca è una scelta, non una dimenticanza

Sette `<Password>` su nove non dichiarano niente, e **va bene così**: il developer ha deciso il
2026-08-13 che su quei campi il browser **deve** poter suggerire le password salvate, perché serve ai
test manuali — si inseriscono vecchie password memorizzate.

| Form | Campi | `autocomplete` |
|---|---|---|
| `UserForm.vue` | `password` `:312` · ripeti `:452` | solo sul secondo |
| `ResetPassword.vue` | `password` `:73` · ripeti `:144` | nessuno |
| `ForcePasswordChange.vue` | attuale `:78` · nuova `:115` · ripeti `:184` | nessuno |
| `Login.vue` | password `:100` | nessuno |

**La regola risultante è asimmetrica, di proposito**: non si aggiunge dove manca, **non si toglie dove
c'è**. Le due metà hanno ragioni diverse — la prima è la comodità nei test manuali, la seconda è che
togliere l'attributo reintrodurrebbe il suggerimento automatico dove qualcuno lo aveva escluso
apposta. Chi «uniforma» in una direzione o nell'altra sta disfacendo una decisione.

Traccia: `VDF04`, **chiusa come comportamento voluto**, in
[vulnerability.md](task/vulnerability/vulnerability.md).

---

## Prima di cancellare un database, chiama la guardia

**Qualunque codice che svuoti o ricrei un database** — `migrate:fresh`, un `truncate`, uno script di
riallineamento — deve prima chiamare:

```php
use App\Support\DestructiveDatabaseGuard;

DestructiveDatabaseGuard::ensureTestDatabase();
```

Rifiuta se il database in uso non è fra quelli di `TEST_ALLOWED_DATABASES` (`:memory:`, `idp_test`), e
**fallisce chiusa**: senza elenco non lascia passare. Su un'operazione che distrugge dati, «non lo so»
deve valere «no».

**Perché non sovrascriviamo `migrate:fresh`**: quel comando, fuori da `local`, chiede già conferma da
console — Laravel protegge chi lo digita. Quello che mancava è una guardia per chi cancella **da
codice**, dove nessuno chiede niente. Da qui la forma: una funzione che si chiama, non un comando che
si sostituisce.

**Perché esiste**: il 2026-08-12 una esecuzione della suite ha fatto `migrate:fresh` sul database di
sviluppo e lo ha svuotato. La difesa allora stava dentro `tests/TestCase.php` e proteggeva solo chi
passava di lì. Traccia: `VDF11`, punto `TVF04`.

---

## Un client Passport si **revoca**, non si cancella

Se stai per scrivere il metodo che cancella un client OAuth, fermati: **non deve cancellarlo.** Deve
metterlo a `revoked`.

```php
$client->forceFill(["revoked" => true])->save();   // sì
$client->delete();                                  // no
```

**Perché**: `Laravel\Passport\Client` **non ha soft delete** (`vendor/laravel/passport/src/Client.php`)
— una cancellazione è definitiva. E gli audit hanno una relazione **polimorfa** verso l'attore, che
può essere un utente **oppure un client**: cancellare la riga lascia gli audit con un `user_id` che
non risolve più, e la perdita è quella che su un registro di audit costa di più — **chi ha fatto
cosa**, in modo irreversibile.

La colonna `revoked` esiste già nello schema
(`database/migrations/2016_06_01_000004_create_oauth_clients_table.php`), quindi non c'è niente da
aggiungere: c'è solo da non usare `delete()`.

**Oggi il rischio non si realizza**, ed è il motivo per cui questa pagina esiste adesso:
`OauthClientsController` non ha un metodo di cancellazione e le sue rotte sono commentate
(`routes/web.php`). La regola è scritta **prima** che quel codice nasca, perché dopo sarebbe una
correzione in produzione.

Traccia: difetto `VDF09`, punto `TCC08`, e il contesto in
[project-analysis.md](project-analysis.md#audit-lattore-è-una-relazione-polimorfa).
