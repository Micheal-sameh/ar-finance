# syntax=docker/dockerfile:1
#
# Build context is the parent directory (see docker-compose.yml) because
# composer.json pulls avarewase/sso-client from the sibling path repo
# "../avarewase-sso-client" — both directories need to be in the build context
# for `composer install` to resolve it.

########################################
# 1) Install PHP dependencies
########################################
FROM composer:2 AS vendor
WORKDIR /build
COPY avarewase-sso-client ./avarewase-sso-client
COPY avarewase-finance/composer.json avarewase-finance/composer.lock ./avarewase-finance/
WORKDIR /build/avarewase-finance
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --no-interaction \
        --prefer-dist \
        --ignore-platform-req=ext-exif
COPY avarewase-finance/. .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

########################################
# 2) Build frontend assets (Vite/React)
########################################
FROM node:22-alpine AS frontend
WORKDIR /app
COPY avarewase-finance/package.json avarewase-finance/package-lock.json ./
RUN npm ci
COPY avarewase-finance/vite.config.js avarewase-finance/tsconfig.json ./
COPY avarewase-finance/resources ./resources
# vite.config.js aliases "ziggy-js" straight to this vendor package, so it
# must exist before `npm run build` resolves resources/js/app.tsx.
COPY --from=vendor /build/avarewase-finance/vendor/tightenco/ziggy ./vendor/tightenco/ziggy

ARG VITE_APP_NAME
ENV VITE_APP_NAME=$VITE_APP_NAME

RUN npm run build

########################################
# 3) Runtime image: php-fpm + scheduler/queue worker, managed by supervisor
#    (nginx/webserver and MySQL are expected to already run on the host)
########################################
FROM php:8.3-fpm-alpine AS runtime

RUN apk add --no-cache \
        bash \
        curl \
        git \
        unzip \
        icu-libs \
        libzip \
        oniguruma \
        libpng \
        libjpeg-turbo \
        freetype \
        supervisor \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        bcmath \
        zip \
        intl \
        gd \
        exif \
        pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=vendor /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY --from=vendor /build/avarewase-finance ./
COPY --from=frontend /app/public/build /opt/frontend-build

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY avarewase-finance/docker/php/local.ini /usr/local/etc/php/conf.d/local.ini
COPY avarewase-finance/docker/supervisord.conf /etc/supervisord.conf
COPY avarewase-finance/docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
