# BoneRADF — minimal dev Dockerfile
# ponytail: dev-only container; production deploy recipe lives in docs/DEPLOY.md
FROM php:8.1-cli

RUN docker-php-ext-install pdo pdo_mysql \
 && { \
      echo 'opcache.enable=1'; \
      echo 'opcache.memory_consumption=128'; \
      echo 'opcache.validate_timestamps=1'; \
      echo 'display_errors=On'; \
    } > /usr/local/etc/php/conf.d/boneradf.ini

WORKDIR /app
COPY . .

EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
