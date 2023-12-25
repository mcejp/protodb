# Thanks to https://github.com/TrafeX/docker-php-nginx/blob/master/Dockerfile for some tricks
FROM php:8.1-fpm-alpine3.15

# libzip-dev, zlib-dev: requred for php zip extension
# yaml-dev: php yaml extension (used for instance configuration)
RUN apk add --no-cache autoconf build-base cloc libzip-dev "python3>3.8" py3-mypy py3-pip py3-pytest uwsgi-python3 yaml-dev zlib-dev\
        nginx supervisor && \
    rm /etc/nginx/http.d/default.conf

RUN docker-php-source  extract \
    && yes "" | pecl install yaml-2.2.2 xdebug-3.1.2 \
    && docker-php-ext-enable xdebug yaml \
    && docker-php-ext-install pdo_mysql zip \
    && docker-php-source delete

# TODO: use proper Python packages & setuptools instead of this
RUN pip3 install ConfigArgParse flask Jinja2 mysql-connector-python PyYAML types-PyYAML

RUN apk del autoconf build-base zlib-dev

COPY container/nginx.conf /etc/nginx/nginx.conf
COPY container/nginx-server.conf /etc/nginx/http.d/default.conf
COPY container/supervisord.conf /etc/supervisor/conf.d/supervisord.conf


# Keep this list in sync with docker-compose.yml mounts for easier development
COPY api-server /var/www/html/api-server/
COPY candb /var/www/html/candb/
COPY config /var/www/html/config/
COPY protodb /var/www/html/protodb/
COPY static /var/www/html/static/
COPY vendor /var/www/html/vendor/
COPY views /var/www/html/views/
COPY bootstrap.php /var/www/html/
COPY bsod.php /var/www/html/
COPY CHANGELOG.md /var/www/html/


CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

HEALTHCHECK --timeout=10s CMD curl --silent --fail http://127.0.0.1:8080/fpm-ping
