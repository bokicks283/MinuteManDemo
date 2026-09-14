FROM php:8.4-apache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install -y --no-install-recommends unzip libonig-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring \
    && apt-get purge -y libonig-dev \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

RUN printf 'display_errors=Off\nlog_errors=On\n' > /usr/local/etc/php/conf.d/errors.ini

WORKDIR /var/www/html
