FROM composer:2@sha256:ca11f643d7791ebdd90a34fc20caf11380d434007ae539a9ea75cd9212e13dca AS composer
FROM php:8.3-fpm-bookworm@sha256:d7677e2a3c1579fbe52848cc446bc24860187c876c383a43b7d4b8e06dce537e
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libfcgi-bin libicu-dev libzip-dev libonig-dev libxml2-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring intl zip bcmath opcache dom xml \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/php/development.ini /usr/local/etc/php/conf.d/development.ini
COPY docker/php/zz-health.conf /usr/local/etc/php-fpm.d/zz-health.conf
COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint
WORKDIR /var/www/html
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
