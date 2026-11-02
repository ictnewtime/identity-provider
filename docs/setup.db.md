# Setup dei database

## 1. Riepilogo

Due database su MariaDB, e **non si toccano fra loro**. La separazione non è ordine: è la
correzione di un difetto — con un database solo, una esecuzione dei test faceva `migrate:fresh` su
quello di lavoro e lo svuotava ([`VDF11`](task/vulnerability/vulnerability.md)).

| Ambiente               | Database                        | Chi lo crea                                                             | Chi lo svuota   | Obbligatorio?                                      |
| ---------------------- | ------------------------------- | ----------------------------------------------------------------------- | --------------- | -------------------------------------------------- |
| **develop**            | `idp_develop`                   | **tu, a mano** (§ 2)                                                    | solo tu         | **opzionale** — serve per lavorare, non per i test |
| **stage / production** | `idp_staging`, `idp_production` | chi amministra il server (§ 4)                                          | nessuno         | fuori da questa macchina                           |

**I test non usano nessuno dei due**: i test di backend girano su **sqlite in memoria**, gli E2E su un
**file sqlite dentro il loro container**, che sparisce con lui (§ 3). È il motivo per cui il § 2 è
opzionale.

Due difese impediscono che un'esecuzione dei test tocchi `idp_develop`:

- `tests/TestCase.php` **aborta prima di qualunque migrazione** se il database in uso non è fra
  quelli consentiti (`:memory:`, `idp_test`);
- ogni ambiente di test è un container suo, con la propria configurazione: non legge il `.env` di
  sviluppo e non tocca `bootstrap/cache/`.

---

## 2. Database di develop

Serve per lavorare sull'applicazione. Se stai solo eseguendo i test, salta al § 3.

### Dove sta la configurazione

Due file, e il secondo **vince sul primo**:

| File                                                        | Cosa dichiara                                                                                                   |
| ----------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| `.env`                                                      | `DB_HOST`, `DB_DATABASE`, credenziali. È quello che si legge girando i comandi `artisan` da fuori dal container |
| `docker-compose.yml`, servizio `app`, blocco `environment:` | le stesse variabili **per il container**. Sono variabili d'ambiente vere, quindi **sovrascrivono il `.env`**    |

> ⚠️ **Se l'applicazione non trova il database, guarda prima il `docker-compose.yml`.** `DB_HOST` deve
> essere il **nome del servizio** — oggi `idp_mariadb_2` — non `mariadb` e non un `container_name` di
> un altro progetto. Un valore stantio lì rende inutile un `.env` corretto, e l'`entrypoint.sh` lo
> segnala dopo 30 tentativi.

### Creare il database — **opzionale se si usa docker-compose.yml**

```sh
docker exec -i idp_mariadb_2 mariadb -uroot -p123 <<'SQL'
CREATE DATABASE IF NOT EXISTS idp_develop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SHOW DATABASES;
SQL
```

> **Il compose non lo crea al posto tuo**, anche se sembra: `MYSQL_DATABASE: idp_develop` viene letto
> dall'immagine MariaDB **solo alla prima inizializzazione del volume**, e `mariadb_data` esiste già.
> Sulle accensioni successive quella variabile è inerte ([`BDB28`](task/backlog/backlog.md)). Il
> comando qui sopra è idempotente: se il database c'è, non fa niente.

### Schema e dati di partenza

```sh
docker exec idp_app_2 php artisan config:clear
docker exec idp_app_2 php artisan migrate --force
docker exec -e SEED_ADMIN_PASSWORD='<scegli-una-password>' idp_app_2 php artisan db:seed
```

> `db:seed` **non inventa più la password dell'amministratore**: la legge dall'ambiente e senza si
> ferma con un messaggio. È la correzione del difetto
> [`VDF08`](task/vulnerability/vulnerability.md) — prima era scritta nel codice e versionata.

> **Eseguirlo due volte fallisce, ed è voluto.** Il seeder se ne accorge da sé e si ferma con
> `db:seed non e' rieseguibile`, dicendo come riseminare — invece di lasciar arrivare un
> `UNIQUE constraint failed` dal database, che dice cosa è successo e non cosa fare. E **non lascia
> niente a metà**: il controllo è la prima riga della transazione. Per riseminare da zero:
>
> ```sh
> docker exec idp_app_2 php artisan migrate:fresh --force
> docker exec -e SEED_ADMIN_PASSWORD='<password>' idp_app_2 php artisan db:seed
> ```
>
> `migrate:fresh` **droppa tutte le tabelle** di `idp_develop`: è quello che serve qui, ed è la stessa
> cosa che sul database sbagliato ha causato [`VDF11`](task/vulnerability/vulnerability.md).

### Verificare che i test di backend non lo tocchino

I test di backend girano su sqlite: nessun servizio, nessun database. La verifica è che `idp_develop`
abbia gli stessi utenti **prima e dopo**.

```sh
docker exec idp_mariadb_2 mariadb -uroot -p123 -N -e "select count(*) from idp_develop.users;"

docker build -f Dockerfile.test.backend -t idp-test-backend .
docker run --rm -v "$PWD":/var/www idp-test-backend

docker exec idp_mariadb_2 mariadb -uroot -p123 -N -e "select count(*) from idp_develop.users;"
```

I due conteggi devono coincidere. Se il secondo è `0`, **fermati**: la suite sta scrivendo dove non
deve, ed è [`VDF11`](task/vulnerability/vulnerability.md).

---

## 3. Database dei test E2E

Non c'è niente da preparare: è un file sqlite in `/tmp` del container E2E. Lo crea
`docker/test/e2e-reset-db.sh` all'avvio (migrazioni + `DatabaseSeeder` + `E2EUserSeeder`) e sparisce
quando il container viene eliminato. Lo script rifiuta di partire se il database configurato non è
un sqlite in `/tmp`.

| File                       | Cosa dichiara                                                                                                    |
| -------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| `.env.test.e2e.example`    | `DB_CONNECTION=sqlite`, `DB_DATABASE=/tmp/e2e/database.sqlite`. `Dockerfile.test.e2e` lo copia **nell'immagine** |
| `docker/test/e2e-entrypoint.sh` | genera credenziali e chiavi, crea il database, avvia l'applicazione e lancia Playwright                      |

Si esegue con `./scripts/run-test-e2e.sh`. Perché sqlite e quale limite comporta (la ricerca con
lettere accentate in maiuscolo): [TEST.md](TEST.md#il-limite-di-sqlite).

---

## 4. Database di stage e production

Stanno su un server, non su questa macchina, e la differenza che conta è che l'applicazione **non
accede come `root`**: si crea un utente con i privilegi sul solo database che gli serve.

Il nome del container MariaDB dipende dall'ambiente: sostituiscilo con quello vero (`docker ps`).

```sh
docker exec -it mariadb mariadb -u root -p123
```

```sql
SHOW DATABASES;
CREATE DATABASE IF NOT EXISTS idp_staging;
SHOW DATABASES;
CREATE USER 'idp_user'@'%' IDENTIFIED BY '<password>';
GRANT ALL PRIVILEGES ON idp_staging.* TO 'idp_user'@'%';
FLUSH PRIVILEGES;
SHOW GRANTS FOR 'idp_user'@'%';
```

- **`GRANT` sul solo database**: `idp_user` non deve poter leggere né toccare gli altri schemi.
- **`'idp_user'@'%'`** accetta connessioni da qualunque host. Se conosci la rete da cui arriva
  l'applicazione, restringilo: è un privilegio in meno da dover ricordare.
- **La password non si scrive qui né in un `.env` versionato**: in questi ambienti le variabili
  arrivano da Infisical (`ansible/builder/tasks/identity-provider.yml`).
- Dopo la creazione servono le migrazioni e i passi obbligatori di
  [post-deploy.md](post-deploy.md) — in particolare le chiavi Passport, che a ogni release si
  perdono perché `storage` non è persistito.
