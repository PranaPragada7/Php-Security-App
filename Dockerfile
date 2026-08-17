FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl libcurl4-openssl-dev \
    && docker-php-ext-install curl pdo_mysql \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html
COPY apache/app.conf /etc/apache2/conf-available/cipherdesk.conf

RUN a2enconf cipherdesk \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
