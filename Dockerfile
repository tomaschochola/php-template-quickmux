# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.5-fpm-trixie
ARG NGINX_VERSION=1-trixie
ARG COMPOSER_VERSION=2

FROM composer:${COMPOSER_VERSION} AS versionedcomposer
FROM php:${PHP_VERSION} AS versionedphp
FROM nginxinc/nginx-unprivileged:${NGINX_VERSION} AS versionednginx

FROM versionedphp AS base
WORKDIR /var/www/html
ENV APP_ENV=production
ENV NODE_ENV=production
RUN <<EOF
  set -euo pipefail
  apt-get update -y
  apt-get upgrade -y --no-install-recommends
  apt-get install -y --no-install-recommends libfcgi-bin
  docker-php-ext-install pdo pdo_mysql
  pecl install apcu redis
  docker-php-ext-enable apcu redis
  apt-get autoremove -y
  apt-get autoclean -y
  apt-get clean -y
  rm -rf /var/lib/apt/lists/*
EOF
COPY ./ops/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY --from=versionedcomposer /usr/bin/composer /usr/bin/composer

FROM base AS devcontainer
ENV APP_ENV=local
ENV NODE_ENV=development
RUN <<EOF
  set -euo pipefail
  apt-get update -y
  apt-get upgrade -y --no-install-recommends
  apt-get install -y --no-install-recommends ca-certificates curl wget build-essential git zip unzip
  docker-php-ext-install pcntl
  pecl install xdebug
  docker-php-ext-enable xdebug
  apt-get install -y --no-install-recommends libzip-dev
  docker-php-ext-install zip
  apt-get install -y --no-install-recommends libicu-dev
  docker-php-ext-install intl
  mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
  groupadd devcontainer
  useradd -s /bin/bash --gid devcontainer -m devcontainer
  wget https://nodejs.org/dist/v24.14.0/node-v24.14.0-linux-x64.tar.xz -O node.tar.xz
  tar -xf node.tar.xz -C /usr/local --strip-components=1
  rm node.tar.xz
  wget https://github.com/mikefarah/yq/releases/latest/download/yq_linux_amd64 -O /usr/local/bin/yq
  chmod +x /usr/local/bin/yq
  apt-get autoremove -y
  apt-get autoclean -y
  apt-get clean -y
  rm -rf /var/lib/apt/lists/*
EOF
COPY ./ops/php/z.ini /usr/local/etc/php/conf.d/z.ini
COPY ./ops/php/zz.ini /usr/local/etc/php/conf.d/zz.ini
COPY ./ops/php/zzz.ini /usr/local/etc/php/conf.d/zzz.ini
USER devcontainer:devcontainer

FROM versionedcomposer AS vendor
COPY ./composer* ./
RUN <<EOF
  set -euo pipefail
  composer install --no-ansi --no-interaction --no-plugins --no-scripts --no-dev --no-autoloader --ignore-platform-reqs
EOF

FROM base AS php
COPY --from=vendor /app/composer* ./
COPY --from=vendor /app/vendor ./vendor
COPY ./index.php ./index.php
COPY ./src ./src
RUN <<EOF
  set -euo pipefail
  composer dump-autoload --no-ansi --no-interaction --no-plugins --no-scripts --no-dev --classmap-authoritative --strict-psr --strict-ambiguous
  composer audit --no-ansi --no-interaction --no-plugins --no-scripts --no-dev
  composer check-platform-reqs --no-ansi --no-interaction --no-plugins --no-scripts --no-dev
  composer validate --no-ansi --no-interaction --no-plugins --no-scripts --strict --with-dependencies --check-lock
  mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
EOF
COPY ./ops/php/z.ini /usr/local/etc/php/conf.d/z.ini
COPY ./ops/php/zz.ini /usr/local/etc/php/conf.d/zz.ini
COPY ./ops/php/entrypoint.sh /usr/local/bin/docker-php-entrypoint

FROM versionednginx AS nginx
WORKDIR /var/www/html
COPY ./ops/nginx /etc/nginx
COPY ./public ./
