FROM php:8.4-cli

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

EXPOSE 8000

CMD ["bash", "-lc", "composer install --no-interaction && until php bin/console doctrine:migrations:migrate --no-interaction; do echo 'Waiting for database and retrying migrations...'; sleep 2; done && php -S 0.0.0.0:8000 -t public"]

