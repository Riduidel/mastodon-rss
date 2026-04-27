# syntax=docker/dockerfile:1


FROM docker.io/composer:lts as mastodon-rss
WORKDIR /usr/app
COPY composer.json .
COPY index.php .
RUN composer install

FROM php:8.5-apache as final
#RUN docker-php-ext-install pdo pdo_mysql
#RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --from=mastodon-rss /usr/app/vendor/ /var/www/html/vendor
COPY --from=mastodon-rss /usr/app/index.php /var/www/html
USER www-data