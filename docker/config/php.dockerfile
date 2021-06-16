ARG PHP_TAG
FROM php:8-${PHP_TAG}
RUN apt update
RUN apt install -y libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev libldap-dev libc-client-dev libkrb5-dev
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-configure imap --with-kerberos --with-imap-ssl
RUN docker-php-ext-install gd zip ldap pdo pdo_mysql imap

# Only needed for phpmyadmin
RUN docker-php-ext-install mysqli


RUN rm -rf /var/lib/apt/lists/*
