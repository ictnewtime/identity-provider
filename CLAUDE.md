# Regole per Claude — identity-provider2

Laravel 12 / PHP 8.2 — Passport, JWT, Socialite, Inertia. Il codice sta in `app/`
(`Services/`, `Repositories/`, `Http/{Controllers,Requests,Resources,Middleware}/`, `Models/`).

## Come si scrive

- Identificatori, nomi di file e messaggi di commit in **inglese**; commenti e documenti in **italiano**.

## Cosa non si fa senza chiedere

- **Git è in sola lettura**: nessun commit, nessun push. Il messaggio di commit si _propone_.
- **Scritture sul database e sui sistemi reali**: solo con approvazione esplicita, volta per volta.
- **Perf/leak obbligatorio su ogni service** toccato — policy dell'organizzazione: query N+1, dati
  esposti di troppo, query per tenant, memoria e streaming, query senza limite. Esito dichiarato
  voce per voce.

## Ambiente e comandi

- `docker compose up` → app su `:8001`, MariaDB su `:3307`, Mailpit su `:8025`.
- **Test**: [docs/TEST.md](docs/TEST.md). Backend con `./scripts/run-test-backend.sh`, E2E (Playwright)
  con `./scripts/run-test-e2e.sh`: container usa-e-getta su sqlite, non toccano il database di sviluppo
  e si lanciano senza chiedere. **Mai `php artisan test` dentro il container
  dell'applicazione**: con la config cache ignora `phpunit.xml` e punta al database vero.
- **Composer**: via immagine `composer:2`, non installato in locale.

## Onestà sul risultato

Se i test falliscono si riporta l'output; se una fase è saltata si dice; ogni cifra dichiara il
comando che la produce.
