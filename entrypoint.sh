#!/bin/bash
set -e

# --- Le dipendenze, se mancano ----------------------------------------------------------------
# Perche' esiste (punto TSR15): `vendor/`, `node_modules/` e `public/build/` sono in .gitignore, e il
# montaggio `./:/var/www` COPRE quelli che l'immagine si e' costruita al build. Su un albero appena
# clonato non ci sono, e l'applicazione non parte: qualcuno deve installarli **dentro** il container.
# Se lo fa una persona con `docker exec`, li installa come root e lascia migliaia di file che poi non
# si riscrivono e non si cancellano — l'EACCES di `npm run build`, difetto BDB32.
#
# Qui l'installazione la fa l'avvio, e **lascia i privilegi** invece di prenderli: l'utente e' il
# proprietario dell'albero montato, che e' la cartella del progetto sull'host. Lo stesso ricavo di
# `docker/supervisor/run-vite.sh`, e per la stessa ragione: il compose non sa calcolare valori, e un
# uid scritto in un file e' giusto su una macchina e sbagliato sulla successiva.
#
# `HOME=/tmp`: composer e npm scrivono la loro cache in $HOME, e quell'uid dentro il container non ha
# una casa. `/tmp` sta fuori dall'albero montato: non lascia niente in giro.
#
# ATTENZIONE ALL'ORDINE, e' stato un errore: questo blocco sta PRIMA del `chown` qui sotto. Gli
# script di composer (`package:discover`) scrivono in `bootstrap/cache` e in `storage/logs`, e
# dopo il `chown` quelle due cartelle sono di www-data. Provato il 2026-08-21 con il blocco dopo:
# «The stream or file /var/www/storage/logs/laravel.log could not be opened», composer esce 1 e
# `set -e` ferma l'avvio. Il `chown` che segue rimette a www-data anche cio' che l'installazione
# ha creato, quindi l'ordine giusto non lascia niente indietro.
UID_ALBERO=$(stat -c %u /var/www)
GID_ALBERO=$(stat -c %g /var/www)

come_proprietario() {
    setpriv --reuid="$UID_ALBERO" --regid="$GID_ALBERO" --clear-groups env HOME=/tmp "$@"
}

if [ ! -f /var/www/vendor/autoload.php ]; then
    echo "vendor/ non c'e' (albero appena clonato?): composer install come uid ${UID_ALBERO}"
    come_proprietario composer install --no-interaction --prefer-dist
fi

if [ ! -d /var/www/node_modules ]; then
    echo "node_modules/ non c'e': npm install come uid ${UID_ALBERO}"
    come_proprietario npm install --no-audit --no-fund
fi

# --- I permessi che servono a php-fpm ---------------------------------------------------------
# `storage` e `bootstrap/cache` li scrive l'applicazione, che gira come www-data. Questo `chown`
# copre anche cio' che l'installazione qui sopra ha appena creato.
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