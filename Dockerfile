FROM php:8.2-fpm

WORKDIR /var/www

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    zlib1g-dev \
    libssl-dev \
    libzip-dev \
    libonig-dev \
    zip \
    unzip \
    git \
    curl \
    nginx \
    supervisor \
    nodejs \
    npm \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        zip \
        exif \
        pcntl \
        gd \
        opcache

# Install Composer
COPY --from=composer:2.5 /usr/bin/composer /usr/bin/composer

# Copy composer files and vendor
COPY composer.json composer.lock* ./

# Copy application
COPY . .

# Install/update composer dependencies
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Install npm dependencies and compile assets
RUN npm install

# create passport keys if they don't exist
RUN if [ ! -f /var/www/storage/oauth-private.key ] || [ ! -f /var/www/storage/oauth-public.key ]; then \
        php artisan passport:keys --force; \
    fi

# Change ownership and permissions
RUN chmod -R 755 /var/www && \
    chmod -R 755 /var/www/public

# Create PHP-FPM configuration
RUN echo '[www]\n\
user = www-data\n\
group = www-data\n\
listen = 127.0.0.1:9000\n\
pm = dynamic\n\
pm.max_children = 5\n\
pm.start_servers = 2\n\
pm.min_spare_servers = 1\n\
pm.max_spare_servers = 3\n\
' > /usr/local/etc/php-fpm.d/www.conf

# Copy Nginx config
COPY docker/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf

# Disable default nginx site
RUN rm -f /etc/nginx/sites-enabled/default

# Copy Supervisor config
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Set permissions and create directories
RUN mkdir -p /var/log/supervisor /var/run/supervisor /var/run/php-fpm && \
    chmod 755 /var/run/supervisor


RUN chown -R www-data:www-data storage
RUN npm run build

# Create entrypoint script
RUN echo '#!/bin/bash\n\
echo "Attesa che MariaDB (mariadb:3306) sia raggiungibile..."\n\
# Tenta di aprire una connessione TCP ogni secondo finché non risponde o passano 30 secondi\n\
for i in {1..30}; do\n\
  if timeout 1s bash -c "true < /dev/tcp/mariadb/3306" 2>/dev/null; then\n\
    echo "MariaDB è ONLINE!"\n\
    break\n\
  fi\n\
  echo "Database non ancora pronto... (tentativo $i)"\n\
  sleep 2\n\
done\n\
\n\
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf\n\
' > /entrypoint.sh && chmod +x /entrypoint.sh


EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]