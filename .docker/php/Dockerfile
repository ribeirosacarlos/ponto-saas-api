# ---------- vendor ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
  --no-dev \
  --no-interaction \
  --prefer-dist \
  --optimize-autoloader \
  --no-progress \
  --no-scripts

# ---------- runtime ----------
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git curl \
    libpng-dev libonig-dev libxml2-dev \
    zip unzip libzip-dev \
    libpq-dev \
    netcat-traditional \
&& apt-get clean && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install \
    pdo_pgsql pgsql \
    mbstring exif pcntl bcmath gd zip opcache

WORKDIR /var/www
COPY . /var/www

# copia vendor pronto
COPY --from=vendor /app/vendor /var/www/vendor

# composer binário só para reotimizar o autoload abaixo
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN composer dump-autoload --optimize --no-dev

# agora que artisan existe, podemos rodar discover/otimizações
RUN php -v \
&& php artisan package:discover --ansi || true

# gera a doc Swagger/OpenAPI a partir das anotações em app/Swagger, embutida na imagem
RUN php artisan swagger:generate-safe || true

COPY .docker/php/php.ini /usr/local/etc/php/conf.d/local.ini
COPY .docker/php/zz-logs.conf /usr/local/etc/php-fpm.d/zz-logs.conf
COPY .docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
&& chown -R www-data:www-data /var/www \
&& chmod -R 775 /var/www/storage /var/www/bootstrap/cache

ARG APP_BUILD_SHA=unknown
ENV APP_BUILD_SHA=${APP_BUILD_SHA}

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
