####################################################################################################
# 1st stage (composer deps only, in order to populate cache and speed up builds)
FROM php:7.2-apache-stretch AS userdb-runtime

RUN a2enmod rewrite
RUN a2enmod headers

# Install extenstions: MySQL PDO, GD
RUN apt-get update && apt-get install -y \
        git \
        zip \
        unzip \
        libpq-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        python \
    && docker-php-ext-configure pdo_mysql --with-pdo-mysql=mysqlnd \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install pdo pdo_mysql gd zip

# Enable and configure xdebug
RUN pecl install xdebug
RUN docker-php-ext-enable xdebug

RUN echo "xdebug.remote_enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
#RUN echo "xdebug.remote_connect_back=1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN echo "xdebug.remote_host=192.168.0.34" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Use the default production configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN echo "max_input_vars = 3000" >> /usr/local/etc/php/conf.d/uploads.ini
RUN echo "log_errors = On" >> /usr/local/etc/php/conf.d/log.ini
RUN echo "error_log = /dev/stderr" >> /usr/local/etc/php/conf.d/log.ini
RUN echo "zend.multibyte = On" >> /usr/local/etc/php/conf.d/mb.ini

RUN echo "date.timezone = Europe/Prague" > /usr/local/etc/php/conf.d/timezone.ini

COPY apache.conf /etc/apache2/sites-enabled/000-default.conf

RUN echo "<?php header('Location: /userdb/');" > /var/www/html/index.php

RUN mkdir -p /opt/userdb

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer

WORKDIR /opt/userdb

RUN mkdir vendor

# intentional caching of the composer-deps layer
COPY composer.json composer.lock /opt/userdb/

RUN composer install

# Enable and configure xdebug
RUN pecl install xdebug
RUN docker-php-ext-enable xdebug
RUN echo "xdebug.remote_enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN echo "xdebug.remote_autostart=1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN echo "xdebug.remote_timeout=50" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN echo "xdebug.remote_host=10.254.107.107" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

####################################################################################################
# 2nd stage (in order to support composer deps caching)

FROM userdb-runtime

# create empty config.local.neon, we use env. variables for production configuration
RUN mkdir -p /opt/userdb/app/config
RUN echo "" > /opt/userdb/app/config/config.local.neon

# copy application (bind volume to the path during development in order to override the baked-in app version)
COPY . /opt/userdb

# make some dirs writable by apache httpd
RUN chmod 777 -R /opt/userdb/log
RUN chmod 777 -R /opt/userdb/temp
RUN chmod 777 -R /opt/userdb/vendor/mpdf/mpdf/tmp
