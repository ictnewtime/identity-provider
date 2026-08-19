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

# L'host del database si legge da DB_HOST, NON si scrive qui: era `mariadb` a mano, e il giorno
# in cui il servizio del compose ha cambiato nome questa attesa ha smesso di trovarlo — fallendo
# per 30 tentativi e poi proseguendo lo stesso, che è il modo peggiore di sbagliare.
DB_HOST_ATTESO=${DB_HOST:-mariadb}
DB_PORT_ATTESA=${DB_PORT:-3306}

echo "Attesa MariaDB su ${DB_HOST_ATTESO}:${DB_PORT_ATTESA}..."
DB_ONLINE=0
for i in {1..30}; do
  if timeout 1s bash -c "true < /dev/tcp/${DB_HOST_ATTESO}/${DB_PORT_ATTESA}" 2>/dev/null; then
    echo "MariaDB è ONLINE!"
    DB_ONLINE=1
    break
  fi
  echo "Database non ancora pronto... (tentativo $i)"
  sleep 2
done

if [ "$DB_ONLINE" -eq 0 ]; then
  echo "ATTENZIONE: ${DB_HOST_ATTESO}:${DB_PORT_ATTESA} non risponde dopo 30 tentativi." >&2
  echo "  Il nome viene da DB_HOST: deve coincidere con il NOME DEL SERVIZIO nel docker-compose," >&2
  echo "  non con il container_name. Si prosegue lo stesso, ma l'applicazione non troverà il database." >&2
fi

echo "Generazione documentazione Swagger..."
php artisan l5-swagger:generate || echo "Generazione Swagger fallita, proseguo comunque."

exec /usr/bin/supervisord -n -c /etc/supervisord.conf