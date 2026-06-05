#!/bin/bash
set -e

# Sistema i permessi all'avvio
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

KEYS_DIR="/var/www/storage/app/keys"

# Se la chiave privata non esiste, la genera
if [ ! -f "$KEYS_DIR/private.key" ]; then
    echo "Chiavi RSA non trovate. Generazione in corso..."
    mkdir -p $KEYS_DIR
    openssl genrsa -out $KEYS_DIR/private.key 2048
    openssl rsa -in $KEYS_DIR/private.key -pubout -out $KEYS_DIR/public.key
    chown -R www-data:www-data $KEYS_DIR
    chmod 600 $KEYS_DIR/private.key
    chmod 644 $KEYS_DIR/public.key
    chmod 755 $KEYS_DIR
    echo "Chiavi generate con successo!"
fi

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