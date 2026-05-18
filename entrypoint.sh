#!/bin/bash
set -e

# Sistema i permessi all'avvio
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

echo "Attesa MariaDB..."
for i in {1..30}; do
  if timeout 1s bash -c "true < /dev/tcp/mariadb/3306" 2>/dev/null; then
    echo "MariaDB è ONLINE!"
    break
  fi
  echo "Database non ancora pronto... (tentativo $i)"
  sleep 2
done

exec /usr/bin/supervisord -n -c /etc/supervisord.conf