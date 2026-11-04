# PHP 8.5 и стабильный Swoole 6.2, не ночной тег php8.5.
# Образ уже содержит Swoole, Composer и docker-php-ext-*.
FROM phpswoole/swoole:6.2-php8.5

# pdo_pgsql — заказы в Postgres.
# redis — кэш, локи, throttle и очередь Laravel.
# rdkafka — шина событий, не очередь фреймворка.
# OPcache для CLI включаем ниже: Octane запускается как php artisan, не как php-fpm.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpq-dev \
        librdkafka-dev \
    && docker-php-ext-install pdo_pgsql \
    && pecl install redis rdkafka \
    && docker-php-ext-enable redis rdkafka \
    && echo "opcache.enable_cli=1" > /usr/local/etc/php/conf.d/opcache-cli.ini \
    && rm -rf /var/lib/apt/lists/*

# Octane и queue:work ловят сигналы через pcntl. В базовом образе его нет,
# поэтому процесс octane:start сразу выходит. posix, mbstring, openssl,
# tokenizer, xml, curl и pdo там уже собраны. sockets, intl, bcmath, zip
# этому стенду не нужны.
RUN docker-php-ext-install pcntl

WORKDIR /var/www/html
