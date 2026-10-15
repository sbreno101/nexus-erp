# Imagem PHP usada pelos serviços app/queue/scheduler do docker-compose (perfil "app").
FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev libpng-dev libicu-dev \
    && docker-php-ext-install pdo_pgsql pgsql zip bcmath gd intl pcntl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
