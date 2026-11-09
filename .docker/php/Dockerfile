# ==================================
# Base stage
# ==================================
FROM php:8.5-fpm as base
WORKDIR "/application"

# Set UTC timezone
RUN rm /etc/localtime
RUN ln -s /usr/share/zoneinfo/UTC /etc/localtime
RUN "date"

# Install selected extensions and other stuff
RUN apt-get update && apt-get install -y \
        zlib1g-dev libicu-dev g++ \
        libjpeg62-turbo-dev \
        libzip-dev \
        libpng-dev \
        libwebp-dev \
        libfreetype6-dev \
        libpq-dev \
        git \
        zip \
        unzip

# Type docker-php-ext-install to see available extensions
RUN docker-php-ext-install pdo pdo_pgsql \
    && docker-php-ext-configure gd --with-webp=/usr/include/webp --with-jpeg=/usr/include --with-freetype=/usr/include/freetype2/ \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install -j$(nproc) zip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl \
    && docker-php-ext-install exif \
    && docker-php-ext-install bcmath
# OPcache is built into PHP since 8.5; opcache.ini below only configures it.

COPY php.ini /usr/local/etc/php/
COPY opcache.ini /usr/local/etc/php/conf.d/opcache.ini


# ==================================
# CI stage
# ==================================
FROM base as ci
# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer --version

# Remove APT cache
RUN apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

# ==================================
# Development stage
# ==================================
FROM ci as dev

ARG USERID
ARG GROUPID

RUN usermod -u ${USERID} www-data \
    && groupmod -o -g ${GROUPID} www-data

RUN pecl install xdebug && \
    docker-php-ext-enable xdebug

COPY xdebug.ini /usr/local/etc/php/conf.d/

# ==================================
# Production stage
# ==================================
FROM base as prod
COPY .. /application
#RUN echo 'alias sf="php app/console"' >> ~/.bashrc
RUN echo "display_errors = Off;" >> /usr/local/etc/php/php.ini

