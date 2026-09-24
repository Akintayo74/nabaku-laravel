FROM php:8.4-fpm-bookworm AS base
RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev libzip-dev libonig-dev libssl-dev unzip \
    && docker-php-ext-install bcmath intl mbstring zip opcache \
    && pecl install mongodb-2.4.0 && docker-php-ext-enable mongodb \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache
COPY docker/php.ini /usr/local/etc/php/conf.d/fundraiser.ini
EXPOSE 9000
CMD ["php-fpm"]
