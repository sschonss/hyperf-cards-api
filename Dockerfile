FROM hyperf/hyperf:8.2-alpine-v3.20-swoole-v6.2.2

LABEL org.opencontainers.image.title="cards-api" \
      org.opencontainers.image.description="API didática Hyperf para cartões e transações"

ARG timezone=America/Sao_Paulo

ENV TIMEZONE=${timezone} \
    APP_ENV=dev \
    SCAN_CACHEABLE=false \
    COMPOSER_ALLOW_SUPERUSER=1

RUN set -ex \
    && apk add --no-cache tzdata \
    && ln -sf "/usr/share/zoneinfo/${TIMEZONE}" /etc/localtime \
    && echo "${TIMEZONE}" > /etc/timezone \
    && printf 'memory_limit=1G\ndate.timezone=%s\n' "${TIMEZONE}" > /etc/php82/conf.d/99-cards.ini \
    && php -m | grep -q pdo_mysql \
    && php -m | grep -q swoole

WORKDIR /opt/www

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . /opt/www

RUN composer dump-autoload -o --no-scripts \
    && mkdir -p runtime/logs runtime/container \
    && chmod +x bin/hyperf.php docker/entrypoint.sh

EXPOSE 9501

ENTRYPOINT ["sh", "docker/entrypoint.sh"]
