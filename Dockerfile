#syntax=docker/dockerfile:1

FROM openswoole/openswoole:26.2-php8.4-alpine
WORKDIR /app

COPY bin ./bin
COPY config ./config
COPY migrations ./migrations
COPY public ./public
COPY src ./src
COPY composer.json ./composer.json
COPY composer.lock ./composer.lock
COPY .env ./.env

COPY www.conf /etc/php84/php-fpm.d/www.conf

ENV APP_ENV=prod
ENV DATABASE_HOST=localhost
ENV DATABASE_PORT=5432

RUN apk update 

RUN apk add --no-cache gmp-dev php84-fpm

RUN docker-php-ext-install gmp pgsql pdo_pgsql

RUN composer install --no-dev -o -n

EXPOSE 1234 9000

COPY entrypoint.sh ./entrypoint.sh

ENTRYPOINT ["/bin/ash", "entrypoint.sh"]

CMD ["php", "bin/console", "app:start-game-server", "1234"]
