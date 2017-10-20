FROM php:5.4

# system dependecies
RUN apt-get update && apt-get install -y \
   git \
   libicu-dev \
   libpq-dev \
   zlib1g-dev

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

WORKDIR /src
