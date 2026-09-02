FROM php:8.3-fpm-alpine

ARG APP_DIR=/var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer-cache \
    TZ=America/Recife

RUN apk add --no-cache \
        acl \
        bash \
        freetds-dev \
        freetype-dev \
        git \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libpq-dev \
        libwebp-dev \
        libxml2-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-client \
        tzdata \
        unzip \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_dblib \
        pdo_pgsql \
        soap \
        zip \
    && cp /usr/share/zoneinfo/America/Recife /etc/localtime \
    && echo "America/Recife" > /etc/timezone

RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/zz-kflow.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR ${APP_DIR}

COPY apps/core/composer.json apps/core/composer.lock apps/core/symfony.lock ./

RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY apps/core ./

RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var \
    && composer dump-autoload --optimize

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
