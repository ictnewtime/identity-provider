FROM php:7.4-fpm

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
    locales \
    zip \
    jpegoptim optipng pngquant gifsicle \
    vim \
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
RUN composer install --no-dev --optimize-autoloader

# Install npm dependencies and compile assets
RUN npm install && npm run prod

# Change ownership and permissions
RUN chown -R www-data:www-data /var/www/storage && \
    chmod -R 755 /var/www && \
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

# Create entrypoint script
RUN echo '#!/bin/bash\n\
# Start supervisor without running artisan commands\n\
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf\n\
' > /entrypoint.sh && chmod +x /entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]