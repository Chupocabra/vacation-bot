FROM php:8.3.4-fpm-alpine3.19

RUN apk --update add \
    autoconf \
    shadow \
    gcc \
    libc-dev \
    make \
    linux-headers \
    supervisor \
    musl-dev \
    git \
    libzip-dev \
    libxml2-dev \
    bash \
    jq \
    mysql-client \
    msmtp \
    icu \
    icu-data-full \
    icu-dev \
    icu-libs \
    nano \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) mysqli pdo_mysql bcmath intl sockets zip pcntl \
    && docker-php-ext-enable opcache \
    && rm -rf /var/cache/apk/* && rm -rf /etc/apk/cache

RUN pecl install pcov redis && docker-php-ext-enable pcov redis

COPY .docker/php/supervisord.conf /etc/supervisord-bot.conf
RUN chmod 777 /etc/supervisord.conf

COPY .docker/php/dev.ini /usr/local/etc/php/conf.d/dev.ini

RUN curl -L https://getcomposer.org/composer-stable.phar -o /usr/bin/composer && chmod a+x /usr/bin/composer
