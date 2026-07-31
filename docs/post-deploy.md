# Post-deploy — checklist

Cose da verificare/fare **dopo ogni deploy**, in ordine. Alcune sono sempre da
fare, altre solo in casi specifici (sezione dedicata in fondo).

> I comandi `php artisan …` vanno eseguiti **dentro il container** dell'app.
> Trova il nome con `docker ps` (es. `identity-provider-<tag>`), poi:
>
> ```bash
> docker exec -it <container> bash        # entra nel container (workdir = root Laravel)
> # oppure one-shot:
> docker exec <container> php artisan <comando>
> ```

---

## 1. Verifiche sempre (smoke test)

Da fare a ogni deploy, in quest'ordine:

1. **L'app risponde** — health check:
    ```bash
    curl -sf https://idp-staging.newtimegroup.it/up && echo OK
    ```
2. **Login e accesso admin** — accedi con un utente `admin` dalla UI e verifica
   che atterri sulla home admin (nessun force-logout / redirect anomalo).
3. **Lettura e ricerca dei dati** — dalla UI admin apri e fai una ricerca su
   ciascuna sezione (deve rispondere e paginare senza errori):
    - **Users** (ricerca per username/email)
    - **Roles** (filtro per provider)
    - **Providers** (ricerca per nome/dominio)
    - **Provider-User-Roles** (associazioni)
    - **Sessioni attive**
    - **Log / audit**
4. **Swagger accessibile** — apri
   `https://idp-staging.newtimegroup.it/api/documentation` e verifica che la
   pagina si carichi (basta l'accesso). Se non si vede → vedi §2.1.
5. **JWKS raggiungibile** — la chiave pubblica del master token deve rispondere:
    ```bash
    curl -sf https://idp-staging.newtimegroup.it/.well-known/jwks.json | head
    ```
6. **SSO end-to-end** (se toccato il flusso di login/token) — un login verso un
   provider esterno che riceve il master token e completa il `token/exchange`.

---

## 2. Casi specifici

### 2.1 Swagger non si vede

Rigenera la documentazione OpenAPI:

```bash
php artisan l5-swagger:generate
```

Se fallisce per **permesso di scrittura** su `storage` (es. i file di swagger o
i log non sono scrivibili da `www-data`), sistema i permessi e ripeti:

```bash
chown -R www-data:www-data storage
chmod -R 775 storage bootstrap/cache
php artisan l5-swagger:generate
```

### 2.2 Nuove migration nel deploy

```bash
php artisan migrate --force
```

### 2.3 Cache "sporca" (config/route non aggiornate dopo il deploy)

```bash
php artisan optimize:clear
# oppure singolarmente:
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

### 2.4 Chiavi del master token (RS256) mancanti

L'`entrypoint.sh` le genera all'avvio se assenti; se per qualche motivo mancano
(`storage/app/keys/private.key` / `public.key`), rigenerale a mano:

```bash
mkdir -p storage/app/keys
openssl genrsa -out storage/app/keys/private.key 2048
openssl rsa -in storage/app/keys/private.key -pubout -out storage/app/keys/public.key
chown -R www-data:www-data storage/app/keys
chmod 600 storage/app/keys/private.key
chmod 644 storage/app/keys/public.key
chmod 755 storage/app/keys
```

> Dopo questo, ricontrolla che `/.well-known/jwks.json` esponga la chiave.

### 2.5 Chiavi Passport mancanti / OAuth in errore

```bash
php artisan passport:install --force
```

### 2.6 Permessi di scrittura su storage (log/upload che falliscono)

```bash
chown -R www-data:www-data storage
chmod -R 775 storage bootstrap/cache
```

---

## Note

- I comandi di permessi (`chown www-data`) servono perché il webserver gira come
  `www-data`: se i file vengono creati da un altro utente (es. `root` durante il
  deploy), Laravel non riesce a scrivere log/cache/swagger.
- In staging il container e i path possono cambiare tra un deploy e l'altro:
  usa sempre `docker ps` per il nome corrente del container.
