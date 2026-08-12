# Setup Database

```sh
docker volume create mariadb
docker network create database-network
docker compose -f docker-compose.db.staging.yml up -d
```

```sh
docker exec -it mariadb mariadb -u root -p123
SHOW DATABASES;
CREATE DATABASE IF NOT EXISTS idp_staging;
SHOW DATABASES;
CREATE USER 'idp_user'@'%' IDENTIFIED BY '<password>';
GRANT ALL PRIVILEGES ON idp_staging.* TO 'idp_user'@'%';
FLUSH PRIVILEGES;
SHOW GRANTS FOR 'idp_user'@'%';
```

---

## In locale servono **due** database

Uno solo non basta, e il motivo non è l'ordine: la suite di test usa `RefreshDatabase`, che fa
`migrate:fresh` — **droppa tutte le tabelle e le ricrea**. Se punta al database su cui stai
lavorando, ogni esecuzione dei test cancella i tuoi dati. È già successo il 2026-08-12 e sta scritto
come difetto [`VDF11`](task/vulnerability/vulnerability.md).

| Database | A cosa serve | Chi lo crea | Chi lo svuota |
|---|---|---|---|
| `idp_develop` | il tuo lavoro: utenti, provider, ruoli, gli audit che generi provando l'applicazione | **tu, a mano**, una volta | solo tu, quando lo decidi |
| `idp_test` | i test **E2E**, e nient'altro | **l'ambiente di test, da sé**: `CREATE DATABASE IF NOT EXISTS` a ogni avvio del container | ogni esecuzione, ed è corretto così |

> Il nome `idp_staging` per un database che gira sul portatile è fuorviante: staging è un ambiente
> remoto. `idp_develop` dice cos'è, e `idp_test` dice a chi appartiene.

> **I test di backend non usano nessuno dei due**: girano su **sqlite in memoria**, che non è un
> database su disco e non ha bisogno di essere creato. Solo gli E2E hanno bisogno di `idp_test`,
> perché un browser vero interroga un'applicazione viva e sqlite in memoria non sopravvive fra due
> richieste HTTP. È la decisione `D3` del task
> [local-environments](task/todo/20260812-local-environments/analysis.md).

### Cosa devi eseguire — solo per `idp_develop`

`idp_test` non è in questo elenco di proposito: lo crea l'entrypoint di `Dockerfile.test.e2e`,
quindi non c'è un passo da ricordare né uno da dimenticare.

**1. Creare il database di sviluppo** (container MariaDB `idp_mariadb_2`, utente `root`, password `123`):

```sh
docker exec -i idp_mariadb_2 mariadb -uroot -p123 <<'SQL'
CREATE DATABASE IF NOT EXISTS idp_develop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SHOW DATABASES;
SQL
```

**2. Puntarci l'applicazione**: in `.env` e nella variabile `DB_DATABASE` del servizio `app` di
`docker-compose.yml`.

```sh
# .env
DB_DATABASE=idp_develop
```

**3. Schema e dati di partenza.** Da qui in poi `db:seed` **non inventa più la password
dell'amministratore**: la prende dall'ambiente, e senza si ferma (punto `TSA12`, difetto `VDF08`).

```sh
docker exec idp_app_2 php artisan config:clear
docker exec idp_app_2 php artisan migrate --force
docker exec -e SEED_ADMIN_PASSWORD='<scegli-una-password>' idp_app_2 php artisan db:seed
```

**4. Credenziali dei test E2E** — le genera lo script, che crea anche gli utenti:

```sh
docker exec idp_app_2 ./scripts/prepare-e2e-credentials.sh
```

**5. Verificare che i due ambienti siano davvero separati.** La suite gira nel **suo** container e
non in quello di sviluppo:

```sh
# test di backend: sqlite, nessun servizio, nessun database toccato
docker build -f Dockerfile.test.backend -t idp-test-backend .
docker run --rm -v "$PWD":/var/www idp-test-backend

# test E2E: MariaDB idp_test, creato dall'ambiente
docker compose -f docker-compose.test.yml run --rm --build e2e

docker exec idp_mariadb_2 mariadb -uroot -p123 -N -e "select count(*) from idp_develop.users;"
```

Il conteggio deve essere quello di prima. Se è `0`, la suite sta ancora scrivendo dove non deve:
**fermati** e vedi [`VDF11`](task/vulnerability/vulnerability.md).

> ⚠️ **Non lanciare `php artisan test` dentro `idp_app_2`.** Con una config cache presente ignora
> `phpunit.xml` e punterebbe al database dell'applicazione. Non fa più danno — un guardiano in
> `tests/TestCase.php` aborta **prima** di qualunque migrazione — ma la strada giusta è l'ambiente
> di test separato: `docker-compose.test.yml`, descritto in [TEST.md](TEST.md).

**6. Buttare i residui**, una volta che `idp_develop` risponde. `idp_local` e `idp_staging` hanno 20
tabelle ciascuno e non corrispondono a nessun ambiente dichiarato: tenerli significa che fra un mese
nessuno saprà qual era quello buono.

```sh
docker exec -i idp_mariadb_2 mariadb -uroot -p123 <<'SQL'
DROP DATABASE IF EXISTS idp_local;
DROP DATABASE IF EXISTS idp_staging;
SHOW DATABASES;
SQL
```

> Prima di questo passo, `idp_local` e `idp_staging` sono l'**unica copia** dei dati con cui hai
> lavorato finora. Vanno cancellati dopo che `idp_develop` è popolato e l'applicazione risponde, non
> prima.
