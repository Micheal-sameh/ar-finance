# syntax=docker/dockerfile:1

########################################
# 1) Install PHP dependencies
########################################
FROM composer:2 AS vendor
WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --no-interaction \
        --prefer-dist \
        --ignore-platform-req=ext-exif
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

########################################
# 2) Build frontend assets (Vite/React)
########################################
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js tsconfig.json ./
COPY resources ./resources
# vite.config.js aliases "ziggy-js" straight to this vendor package, so it
# must exist before `npm run build` resolves resources/js/app.tsx.
COPY --from=vendor /build/vendor/tightenco/ziggy ./vendor/tightenco/ziggy

ARG VITE_APP_NAME
ENV VITE_APP_NAME=$VITE_APP_NAME

RUN npm run build

########################################
# 3) Runtime image: php-fpm + scheduler/queue worker, managed by supervisor
#    (nginx/webserver and MySQL are expected to already run on the host)
########################################
FROM php:8.3-fpm-alpine AS runtime

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/

RUN --mount=type=cache,target=/var/cache/apk \
    apk add \
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
    && install-php-extensions \
        pdo_mysql \
        mbstring \
        bcmath \
        zip \
        intl \
        gd \
        exif \
        pcntl \
        opcache \
        redis

COPY --from=vendor /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY --from=vendor /build ./
COPY --from=frontend /app/public/build /opt/frontend-build

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
