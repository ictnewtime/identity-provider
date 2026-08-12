# Post-deploy — checklist

Cose da fare/verificare **dopo ogni deploy**, in ordine. La §1 è **obbligatoria a
ogni release**; la §2 sono le verifiche di smoke test; la §3 i casi specifici; la
§4 i miglioramenti opzionali per eliminare i passi manuali.

> I comandi `php artisan …` vanno eseguiti **dentro il container** dell'app.
> Trova il nome con `docker ps` (es. `identity-provider-<tag>`), poi:
>
> ```bash
> docker exec -it <container> bash        # entra nel container (workdir = root Laravel)
> # oppure one-shot:
> docker exec <container> php artisan <comando>
> ```

---

## 1. Operazioni obbligatorie a ogni release

Il container viene **ricreato** a ogni deploy e `storage` **non è persistito**
(nessun volume montato), mentre `storage/*.key` è gitignorato: quindi le **chiavi
OAuth di Passport vengono perse a ogni release** e vanno rigenerate, altrimenti
`POST /oauth/token` risponde `500 — "Invalid key supplied"`.

Dopo ogni deploy eseguire **sempre**, in quest'ordine:

```bash
php artisan passport:keys
chown -R www-data:www-data storage
```

- `passport:keys` rigenera `storage/oauth-private.key` / `oauth-public.key`.
  Se dice che le chiavi esistono già (storage persistito), aggiungi `--force`
  solo se vuoi davvero rigenerarle (invalida i token già emessi).
- `chown` assicura che `www-data` (il webserver) possa leggere le chiavi e
  scrivere log/cache.

> Per non doverlo fare a mano a ogni release vedi §4.

---

## 2. Verifiche sempre (smoke test)

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
4. **OAuth token** — un `POST /oauth/token` (client_credentials) deve tornare un
   token e **non** `500 "Invalid key supplied"` (se lo fa → §1 non eseguita).
5. **Swagger accessibile** — apri
   `https://idp-staging.newtimegroup.it/api/documentation` e verifica che la
   pagina si carichi (basta l'accesso). Se non si vede → vedi §3.1.
6. **JWKS raggiungibile** — la chiave pubblica del master token deve rispondere:
    ```bash
    curl -sf https://idp-staging.newtimegroup.it/.well-known/jwks.json | head
    ```
7. **SSO end-to-end** (se toccato il flusso di login/token) — un login verso un
   provider esterno che riceve il master token e completa il `token/exchange`.

---

## 3. Casi specifici

### 3.1 Swagger non si vede

Rigenera la documentazione OpenAPI:

```bash
php artisan l5-swagger:generate
```

Se fallisce per **permesso di scrittura** su `storage`, sistema i permessi e ripeti:

```bash
chown -R www-data:www-data storage
chmod -R 775 storage bootstrap/cache
php artisan l5-swagger:generate
```

### 3.2 Nuove migration nel deploy

```bash
php artisan migrate --force
```

### 3.3 Cache "sporca" (config/route non aggiornate dopo il deploy)

```bash
php artisan optimize:clear
# oppure singolarmente:
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

### 3.4 Chiavi del master token (RS256) mancanti

L'`entrypoint.sh` le genera all'avvio se assenti; se mancano
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

> ⚠️ Se `storage` non è persistito (vedi §4), queste chiavi vengono **rigenerate
> a ogni deploy**: la `public.key` del JWKS cambia e i master token già emessi /
> i provider esterni che cachano il JWKS falliscono la verifica.

### 3.5 Permessi di scrittura su storage (log/upload che falliscono)

```bash
chown -R www-data:www-data storage
chmod -R 775 storage bootstrap/cache
```

---

## 4. (Opzionale) Eliminare i passi manuali della §1

Per non dover rigenerare le chiavi a ogni release, scegliere una delle due:

- **Persistere `storage` con un volume** nel deploy Ansible
  (`ansible/project-deploy/tasks/identity-provider-deploy.yml`), così le chiavi
  Passport **e** quelle RS256 del master token sopravvivono ai redeploy:
    ```yaml
          volumes:
              - "idp_storage:/var/www/storage"
    ```
  È la soluzione migliore perché stabilizza anche il JWKS del master token.
- **Generare le chiavi Passport nell'`entrypoint.sh`** solo se mancanti (come già
  avviene per le RS256), così un container nuovo si auto-ripara:
    ```bash
    if [ ! -f storage/oauth-private.key ]; then
        php artisan passport:keys --no-interaction
        chown www-data:www-data storage/oauth-*.key
        chmod 600 storage/oauth-private.key
    fi
    ```
  > Da solo non stabilizza il JWKS del master token: per quello serve il volume.

---

## Note

- I comandi di permessi (`chown www-data`) servono perché il webserver gira come
  `www-data`: se i file vengono creati da un altro utente (es. `root` durante il
  deploy o `php artisan`), Laravel/Passport non riescono a leggerli o a scrivere
  log/cache/swagger.
- In staging il container e i path possono cambiare tra un deploy e l'altro:
  usa sempre `docker ps` per il nome corrente del container.
