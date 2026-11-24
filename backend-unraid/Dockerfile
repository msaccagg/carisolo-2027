FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/data

COPY --chown=www-data:www-data carisolo-api.php /var/www/html/carisolo-api.php