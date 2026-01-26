docker compose up --build

```php
php artisan key:generate
chown -R www-data:www-data storage
php artisan migrate
php artisan db:seed
// composer install
// npm run build
// composer dump-autoload
// composer dump-autoload -o
```

(Facoltativo) Pulisci cache se si hanno dati sporchi quando già avvaita l'applicazione

```php
php artisan config:cache
php artisan route:cache
php artisan config:clear
php artisan cache:clear
// php artisan config:show app
php artisan storage:link
php artisan optimize:clear
```
