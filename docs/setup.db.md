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

| Database | A cosa serve | Chi lo svuota |
|---|---|---|
| `idp_local` | il tuo lavoro: utenti, provider, ruoli, gli audit che generi provando l'applicazione | solo tu, quando lo decidi |
| `idp_test` | la suite automatica, e nient'altro | ogni esecuzione dei test, ed è corretto così |

> Il nome `idp_staging` per un database che gira sul portatile è fuorviante: staging è un ambiente
> remoto. `idp_local` dice cos'è.

### Cosa devi eseguire

**1. Creare i due database** (il container MariaDB è `idp_mariadb_2`, utente `root`, password `123`):

```sh
docker exec -i idp_mariadb_2 mariadb -uroot -p123 <<'SQL'
CREATE DATABASE IF NOT EXISTS idp_local  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS idp_test   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SHOW DATABASES;
SQL
```

**2. Puntare l'applicazione a `idp_local`** — in `.env`, e nel `docker-compose.yml` la variabile
`DB_DATABASE` del servizio `app`:

```sh
# .env
DB_DATABASE=idp_local
```

**3. Ricreare lo schema e i dati di partenza.** Da qui in poi `db:seed` **non inventa più la password
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

**5. Verificare che i due database siano davvero separati**: dopo un'esecuzione della suite,
`idp_local` deve avere ancora i suoi utenti.

```sh
docker exec idp_app_2 composer test
docker exec idp_mariadb_2 mariadb -uroot -p123 -N -e "select count(*) from idp_local.users;"
```

Se quel conteggio è `0`, i test stanno ancora scrivendo dove non devono: **fermati** e vedi
[`VDF11`](task/vulnerability/vulnerability.md).

> ⚠️ **Finché `idp_test` non è configurato come database della suite**, non lanciare
> `docker exec idp_app_2 php artisan test`: con la config cache presente ignora `phpunit.xml` e
> migra il database dell'applicazione. Usare `composer test`, che fa `config:clear` per primo, oppure
> il contenitore usa-e-getta descritto in [TEST.md](TEST.md). La separazione vera è il punto `TCC15`,
> la difesa che la rende non aggirabile è `TCC14`, entrambi in
> [static-analysis-findings-v2](task/todo/20260812-static-analysis-findings-v2/action-plan.md).
