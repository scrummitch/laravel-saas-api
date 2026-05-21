# Base Alpine Image for PHP
FROM --platform=$BUILDPLATFORM alpine:3.20 AS base

COPY docker/base/config /usr/local/etc/

RUN set -eux; \
    apk update && \
    apk upgrade && \
    apk add --no-cache \
        ca-certificates \
        php83 \
        php83-bcmath \
        php83-ctype \
        php83-curl \
        php83-fileinfo \
        php83-gd \
        php83-intl \
        php83-mbstring \
        php83-mysqli \
        php83-opcache \
        php83-openssl \
        php83-pdo \
        php83-pdo_mysql \
        php83-session \
        php83-simplexml \
        php83-tokenizer \
        php83-xmlreader \
        php83-pecl-imagick \
        php83-exif \
        php83-sodium \
    ; \
    rm -rf /var/cache/apk/* ; \
    ln -sf /usr/bin/php83 /usr/bin/php; \
    wget https://truststore.pki.rds.amazonaws.com/us-east-1/us-east-1-bundle.pem -O /etc/ssl/certs/ca-certificates.crt


# PHP Build Image
FROM base AS build

COPY ./ srv/app

RUN set -eux; \
    apk add --no-cache \
        composer \
        php83-dom \
        php83-xml \
        php83-xmlwriter \
    ; \
    cd /srv/app; \
    composer install --no-dev --no-interaction --no-progress --no-suggest --optimize-autoloader --prefer-dist; \
    composer dump-autoload --no-dev --optimize --classmap-authoritative


# nginx image
FROM --platform=$BUILDPLATFORM alpine:3.19 AS nginx

COPY --from=build /srv/app /srv/app
COPY docker/nginx/config/default.conf /usr/local/etc/nginx/http.d/default.conf

RUN set -eux; \
    apk update && \
    apk upgrade && \
    apk add --no-cache \
        ca-certificates \
        nginx \
    ; \
    rm -rf /var/cache/apk/* ; \
    ln -sf /usr/local/etc/nginx/http.d/default.conf /etc/nginx/http.d/default.conf

WORKDIR /srv/app

CMD ["/usr/sbin/nginx", "-g", "daemon off;"]


# php-fpm runtime image
FROM base AS php-fpm

COPY --from=build /srv/app /srv/app
COPY docker/ /usr/local/etc/

RUN set -eux; \
    apk add --no-cache \
        php83-fpm \
    ; \
    ln -sf /usr/local/etc/php/config/php.ini /etc/php83/php.ini; \
    ln -sf /usr/local/etc/php/config/00_opcache.ini /etc/php83/conf.d/00_opcache.ini; \
    ln -sf /usr/local/etc/php-fpm/config/php-fpm.conf /etc/php83/php-fpm.conf ; \
    ln -sf /usr/sbin/php-fpm83 /usr/sbin/php-fpm ; \
    chmod -R 777 /srv/app/storage /srv/app/bootstrap/cache

WORKDIR /srv/app/public

CMD ["/srv/app/start-fpm.sh"]

# PHP worker image
FROM base AS php-worker

COPY --from=build /srv/app /srv/app
COPY docker/php/config/php.ini /etc/php83/php.ini

# We use 960M memory limit to allow 64Mb overhead
RUN set -eux; \
    cat /etc/php83/php.ini | sed -e 's/memory_limit=256M/memory_limit=960M/' > /tmp/php.ini; \
    mv /tmp/php.ini /etc/php83/php.ini; \
    chmod -R 777 /srv/app/storage /srv/app/bootstrap/cache

# Add cron
RUN apk add --no-cache dcron

# Add crontab file
COPY docker/crontab /etc/crontabs/root

# Give execution rights on the cron job
RUN chmod 0644 /etc/crontabs/root

WORKDIR /srv/app

CMD ["sh", "-c", "crond -f -d 8 & /srv/app/start-worker.sh"]
