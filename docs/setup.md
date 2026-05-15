docker compose up --build

```php
php artisan key:generate
chown -R www-data:www-data storage
composer install
php artisan migrate
// php artisan db:seed
php artisan db:seed --class=Database\\Seeders\\RolesSeeder
php artisan db:seed --class=Database\\Seeders\\UsersSeeder

# per ri-gestire in develop le dipendenze (composer/vendor o node_mudules)
// composer install
// npm run build
// npm run watch
// composer dump-autoload
// composer dump-autoload -o

# se passport va in errore per delle vecchie dipendenze di altri vendor usare:
# composer require laravel/passport --with-all-dependencies

// per generare le chiavi di passport
php artisan passport:install --force
// per generare un nuovo utente passport
php artisan passport:client --personal
# per leggere le chaivi di passport
chown -R www-data:www-data storage
php artisan storage:link
chmod -R 775 storage bootstrap/cache
npm run build
```

(Facoltativo) Pulisci cache se si hanno dati sporchi quando già avvaita l'applicazione

```php
php artisan config:cache
php artisan route:cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
// php artisan config:show app
composer dump-autoload
php artisan optimize:clear
```

# ri-creare swagger docs

```php
php artisan l5-swagger:generate
```

# Creare la chiave pubblica e privata per firmare il master token

mkdir -p storage/app/keys
openssl genrsa -out storage/app/keys/private.key 2048
openssl rsa -in storage/app/keys/private.key -pubout -out storage/app/keys/public.key
chown -R www-data:www-data storage/app/keys
chmod 600 storage/app/keys/private.key
chmod 644 storage/app/keys/public.key
chmod 755 storage/app/keys
