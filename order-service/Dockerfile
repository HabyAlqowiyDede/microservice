FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip curl libzip-dev \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && chmod +x docker/entrypoint.sh \
    && chmod -R 777 storage bootstrap/cache

EXPOSE 3003

ENTRYPOINT ["docker/entrypoint.sh"]
