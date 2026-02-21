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
    && { \
        echo 'sys_temp_dir=/tmp'; \
        echo 'upload_tmp_dir=/tmp'; \
    } > /usr/local/etc/php/conf.d/99-tempdir.ini \
    && apk del --no-network $PHPIZE_DEPS \
    && rm -rf /tmp/pear

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
