FROM php:7.2

# system dependecies
RUN apt-get update && apt-get install -y \
   git \
   libicu-dev \
   libpq-dev \
   unzip \
   zlib1g-dev \
   libonig-dev \
   libzip-dev

# PHP dependencies
RUN docker-php-ext-install \
    intl \
    mbstring \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    zip

# composer
RUN curl -sS https://getcomposer.org/installer | php && \
	  mv composer.phar /usr/local/bin/composer

#xdebug
RUN pecl install xdebug
RUN echo zend_extension=/usr/local/lib/php/extensions/no-debug-non-zts-20170718/xdebug.so >> `php --ini | grep "Scan for additional .ini files" | sed -e "s|.*:\s*||"`/ext-xdebug.ini

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /src
