FROM php:8.4-fpm

ARG ENABLE_XDEBUG=1

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libxml2-dev libxslt1-dev libzip-dev libicu-dev \
    libonig-dev libgmp-dev libpq-dev \
    imagemagick libmagickwand-dev \
    zip unzip git curl cron logrotate procps \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
    gd zip pdo pdo_mysql mysqli intl xml soap bcmath sockets xsl opcache gmp ftp \
 && (pecl install imagick \
     || (echo "pecl imagick retry 1" && sleep 15 && pecl install imagick) \
     || (echo "pecl imagick retry 2" && sleep 30 && pecl install imagick)) \
 && pecl install igbinary \
 && (pecl install -D 'enable-redis-igbinary="yes"' redis \
     || (echo "pecl redis retry 1" && sleep 15 && pecl install -D 'enable-redis-igbinary="yes"' redis) \
     || (echo "pecl redis retry 2" && sleep 30 && pecl install -D 'enable-redis-igbinary="yes"' redis)) \
 && if [ "$ENABLE_XDEBUG" = "1" ]; then \
      (pecl install xdebug \
       || (echo "pecl xdebug retry 1" && sleep 15 && pecl install xdebug) \
       || (echo "pecl xdebug retry 2" && sleep 30 && pecl install xdebug)) \
      && docker-php-ext-enable xdebug; \
    fi \
 && docker-php-ext-enable igbinary imagick redis opcache \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# Node for the Hyvä Tailwind build inside the container (same version as the host is fine too)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
 && apt-get install -y --no-install-recommends nodejs \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

RUN groupadd -g 1000 magento \
 && useradd -u 1000 -g 1000 -m magento

RUN sed -i 's/^user = .*/user = magento/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/^group = .*/group = magento/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/^;listen.owner = .*/listen.owner = magento/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/^;listen.group = .*/listen.group = magento/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/^;listen.mode = .*/listen.mode = 0660/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/^pm.max_children = .*/pm.max_children = 10/' /usr/local/etc/php-fpm.d/www.conf

USER magento
WORKDIR /var/www/html
