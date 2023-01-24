FROM php:8.1-apache

ARG FS_MODE

COPY ./${FS_MODE} /var/www/html/

WORKDIR /var/www/html/

RUN apt-get update
RUN apt-get install -y libzip-dev unzip
RUN docker-php-ext-install zip

RUN php -r "readfile('http://getcomposer.org/installer');" | php -- --install-dir=/usr/bin/ --filename=composer

EXPOSE 80

RUN composer install
