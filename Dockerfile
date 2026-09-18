FROM php:8.4-apache-bookworm AS backend

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev libicu-dev \
        libonig-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 \
        pdo_pgsql mbstring bcmath intl zip gd exif opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY . .

RUN mkdir -p \
        bootstrap/cache \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && COMPOSER_ALLOW_SUPERUSER=1 composer install \
        --no-dev \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --optimize-autoloader \
        --no-scripts \
    && php artisan package:discover --ansi

FROM node:24-bookworm-slim AS assets

WORKDIR /app

COPY --from=backend /var/www/html /app

RUN npm ci \
    && npm run build

FROM backend AS production

ENV PORT=10000

COPY --from=assets /app/public/build /var/www/html/public/build
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/start.sh /usr/local/bin/start-app

RUN echo 'Listen ${PORT}' > /etc/apache2/ports.conf \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf '%s\n' \
        'upload_max_filesize=2M' \
        'post_max_size=10M' \
        'memory_limit=128M' \
        'expose_php=Off' \
        > "$PHP_INI_DIR/conf.d/app.ini" \
    && sed -i 's/\r$//' /usr/local/bin/start-app \
    && chmod +x /usr/local/bin/start-app \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 10000

CMD ["/usr/local/bin/start-app"]
