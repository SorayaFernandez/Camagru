FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        gd

# Copiamos la configuración PHP específica de Camagru
# dentro del directorio oficial de configuración de PHP.
# De esta forma, los límites de subida quedan incluidos
# en la imagen Docker y se mantienen después de recrear
# el contenedor.
COPY docker/php/php.ini /usr/local/etc/php/conf.d/camagru.ini

WORKDIR /var/www/html

CMD ["php-fpm", "-F"]