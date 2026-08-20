# syntax=docker/dockerfile:1

# =============================================================================
# base — PHP runtime shared by every other stage.
# =============================================================================
FROM php:8.4-fpm-alpine AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

# imagemagick's coders live in separate Alpine packages and are installed
# first, so the imagick extension is built against an ImageMagick that already
# has them. Without imagemagick-heic a photo straight off an iPhone cannot be
# decoded at all — and no browser can display HEIC either, so it has to be
# converted here or it is useless. See App\Services\ImageOptimiser.
#
# Read support is all that is needed: nothing writes HEIC, and the encoder
# (x265) is deliberately absent.
# postgresql17-client and tar are for `php artisan backup:run` and
# `backup:restore`. The client major version must match the database server
# (postgres:17-alpine in compose.yaml) — pg_dump refuses a server newer than
# itself, so these two move together. GNU tar rather than busybox's, because
# the archive is built by streaming several roots into one file and busybox
# tar's handling of repeated -C is not something to discover in production.
RUN apk add --no-cache fcgi tzdata imagemagick imagemagick-heic imagemagick-webp \
    postgresql17-client tar \
    && install-php-extensions \
        pdo_pgsql \
        pgsql \
        intl \
        zip \
        gd \
        exif \
        opcache \
        pcntl \
        imagick \
    && rm -rf /var/cache/apk/*

# Disables the coders that turn "decode an image" into "follow instructions in
# a file" — see the reasoning in the file itself.
COPY docker/php/imagemagick-policy.xml /etc/ImageMagick-7/policy.xml

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-app.conf

WORKDIR /var/www/html

# =============================================================================
# build — installs dependencies and compiles front-end assets.
#
# This stage needs BOTH PHP and Node. The Wayfinder Vite plugin shells out to
# `php artisan` to generate typed route helpers, so `vite build` cannot run on
# a Node-only image. Do not "optimise" this into a plain node stage.
# =============================================================================
FROM base AS build

RUN apk add --no-cache nodejs npm

# PHP dependencies first, so this layer caches independently of app source.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

# JS dependencies. .npmrc sets ignore-scripts=true; the platform-specific
# native binaries come from optionalDependencies rather than postinstall.
COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY . .

# Laravel has to boot for Wayfinder to enumerate routes. This throwaway key is
# build-time only and is never present at runtime — the real APP_KEY is
# injected from the environment.
ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=

RUN composer dump-autoload --optimize --no-dev \
    && npm run build \
    && rm -rf node_modules

# =============================================================================
# development — source is bind-mounted over this at runtime.
# =============================================================================
FROM base AS development

RUN apk add --no-cache nodejs npm git

ENV APP_ENV=local \
    APP_DEBUG=true

COPY docker/php/opcache-dev.ini /usr/local/etc/php/conf.d/99-opcache.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# =============================================================================
# production — PHP-FPM serving the compiled application.
# =============================================================================
FROM base AS production

ENV APP_ENV=production \
    APP_DEBUG=false

COPY docker/php/opcache-prod.ini /usr/local/etc/php/conf.d/99-opcache.ini

COPY . .
COPY --from=build /var/www/html/vendor ./vendor
COPY --from=build /var/www/html/public/build ./public/build

# Recreate the writable tree explicitly.
#
# These directories contain nothing but .gitignore files, so they are
# effectively invisible to version control and trivially lost in a multi-stage
# copy. Losing them produces a container that boots and then fails on the first
# write. This project has been bitten by exactly that before — do not remove.
RUN mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

USER www-data

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
        cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# =============================================================================
# web — nginx. Serves static assets directly and streams gated media via
# X-Accel-Redirect so PHP-FPM workers are never held open by a video.
# =============================================================================
FROM nginx:1.29-alpine AS web

COPY docker/nginx/app.conf /etc/nginx/conf.d/default.conf
COPY --from=build /var/www/html/public /var/www/html/public

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget -qO- http://127.0.0.1/up >/dev/null 2>&1 || exit 1
