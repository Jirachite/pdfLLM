FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    libpq-dev \
    git \
    curl \
    zip \
    unzip \
    && docker-php-ext-install pdo_pgsql \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/laravel

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data /var/www/laravel/storage /var/www/laravel/bootstrap/cache
RUN chmod -R 775 /var/www/laravel/storage /var/www/laravel/bootstrap/cache

RUN echo "[www]\nlisten = 0.0.0.0:9000" >> /usr/local/etc/php-fpm.d/zz-docker.conf

RUN echo "#!/bin/bash\nchown -R www-data:www-data /var/www/laravel/storage /var/www/laravel/bootstrap/cache\nchmod -R 775 /var/www/laravel/storage /var/www/laravel/bootstrap/cache\nphp-fpm" > /start.sh
RUN chmod +x /start.sh

CMD ["/start.sh"]