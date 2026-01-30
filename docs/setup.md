docker compose up --build

```php
php artisan key:generate
chown -R www-data:www-data storage
php artisan migrate
php artisan db:seed

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
# per leggere le chaivi di passport
chown -R www-data:www-data storage
php artisan storage:link
```

(Facoltativo) Pulisci cache se si hanno dati sporchi quando già avvaita l'applicazione

```php
php artisan config:cache
php artisan route:cache
php artisan config:clear
php artisan cache:clear
// php artisan config:show app
php artisan optimize:clear
```
