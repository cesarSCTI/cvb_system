FROM php:8.2-apache

# Extensiones necesarias para el proyecto (PDO + MySQL) + opcache
RUN docker-php-ext-install pdo pdo_mysql \
    && docker-php-ext-enable opcache \
    && a2enmod rewrite headers

# opcache: acelera cada request de PHP. validate_timestamps=1 para que
# los cambios en el código montado se tomen sin reconstruir.
RUN { \
      echo 'opcache.enable=1'; \
      echo 'opcache.validate_timestamps=1'; \
      echo 'opcache.revalidate_freq=0'; \
    } > "$PHP_INI_DIR/conf.d/zz-opcache.ini"

# Desactiva el cache de HTML/JS/CSS (solo para desarrollo local)
COPY docker/dev-no-cache.conf /etc/apache2/conf-enabled/dev-no-cache.conf

# Apache sirve desde /var/www/html; el código se monta ahí vía docker-compose
WORKDIR /var/www/html

# Configuración de PHP recomendada para desarrollo
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

EXPOSE 80
