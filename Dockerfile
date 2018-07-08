FROM php:5-alpine
WORKDIR /usr/local/app
COPY composer.json composer.lock ./
RUN wget https://getcomposer.org/composer.phar
RUN php composer.phar install --no-dev

FROM php:5-alpine
WORKDIR /usr/local/app
COPY --from=0 /usr/local/app/vendor ./vendor/
COPY src/ ./src
