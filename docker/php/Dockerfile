FROM composer:2.8 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts

FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY --from=vendor /app/vendor/tightenco/ziggy /app/vendor/tightenco/ziggy
COPY resources resources
COPY public public
COPY bootstrap bootstrap
COPY vite.config.js tsconfig.json postcss.config.js tailwind.config.js ./
RUN npm run build

FROM php:8.4-fpm-alpine

ENV TMPDIR=/tmp \
    TEMP=/tmp \
    TMP=/tmp

RUN apk add --no-cache \
    $PHPIZE_DEPS \
    bash \
    git \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    unzip \
    zip \
    && docker-php-ext-install -j$(nproc) \
    intl \
    mbstring \
    pdo \
    pdo_pgsql \
    pgsql \
    zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && git config --system --add safe.directory /var/www/html \
    && { \
        echo 'sys_temp_dir=/tmp'; \
        echo 'upload_tmp_dir=/tmp'; \
    } > /usr/local/etc/php/conf.d/99-tempdir.ini \
    && apk del --no-network $PHPIZE_DEPS \
    && rm -rf /tmp/pear

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor /var/www/html/vendor
COPY --from=assets /app/public/build /var/www/html/public/build
COPY --from=assets /app/bootstrap/ssr /var/www/html/bootstrap/ssr
RUN rm -f /var/www/html/bootstrap/cache/*.php \
    && mkdir -p /var/www/html/bootstrap/cache \
    && chown -R 1000:1000 /var/www/html/bootstrap/cache
RUN cp -a /var/www/html/public /opt/app-public

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
