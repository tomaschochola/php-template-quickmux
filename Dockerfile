# syntax=docker/dockerfile:1

FROM composer:2 AS versionedcomposer
FROM php:8.5-fpm-trixie AS versionedphp
FROM nginxinc/nginx-unprivileged:1-trixie AS versionednginx
FROM container-registry.oracle.com/mysql/community-server:9.6 AS versionedmysql
FROM container-registry.oracle.com/database/free:latest AS versionedoracle
FROM valkey/valkey:9-trixie AS versionedvalkey

FROM busybox:latest AS instantclient
ADD --checksum=sha256:d6715e404a35b3a538280b78df6f7ee59da83a9d36b596218fd264051db977f3 https://download.oracle.com/otn_software/linux/instantclient/2326100/instantclient-basic-linux.x64-23.26.1.0.0.zip /tmp/instantclient-basic.zip
ADD --checksum=sha256:2d7ef8ec14c3e0240221620c12ce94d047092c9065171db17778cce7b1fdd5db https://download.oracle.com/otn_software/linux/instantclient/2326100/instantclient-sdk-linux.x64-23.26.1.0.0.zip /tmp/instantclient-sdk.zip
RUN <<EOF
  set -eu
  mkdir -p /opt/oracle
  unzip -oq /tmp/instantclient-basic.zip -d /opt/oracle
  unzip -oq /tmp/instantclient-sdk.zip -d /opt/oracle
  mv /opt/oracle/instantclient_23_26 /opt/oracle/instantclient
EOF

FROM versionedphp AS base
WORKDIR /var/www/html
ENV APP_ENV=production
ENV NODE_ENV=production
COPY --from=instantclient /opt/oracle/instantclient /opt/oracle/instantclient
RUN <<EOF
  set -euo pipefail
  apt-get update -y
  apt-get upgrade -y --no-install-recommends
  apt-get install -y --no-install-recommends libaio1t64 libfcgi-bin
  ln -sfn /usr/lib/x86_64-linux-gnu/libaio.so.1t64 /usr/lib/x86_64-linux-gnu/libaio.so.1
  echo /opt/oracle/instantclient > /etc/ld.so.conf.d/oracle-instantclient.conf
  ldconfig
  docker-php-ext-install pdo_mysql
  pecl install apcu redis
  printf 'instantclient,/opt/oracle/instantclient\n' | pecl install pdo_oci
  docker-php-ext-enable apcu pdo_oci redis
  rm -rf /opt/oracle/instantclient/sdk /tmp/pear
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
ADD --chmod=755 https://github.com/mikefarah/yq/releases/latest/download/yq_linux_amd64 /usr/local/bin/yq
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
  apt-get autoremove -y
  apt-get autoclean -y
  apt-get clean -y
  rm -rf /var/lib/apt/lists/*
EOF
COPY ./ops/php/z.ini /usr/local/etc/php/conf.d/z.ini
COPY ./ops/php/zz.ini /usr/local/etc/php/conf.d/zz.ini
COPY ./ops/php/zzz.ini /usr/local/etc/php/conf.d/zzz.ini
USER devcontainer

FROM versionedcomposer AS vendor
COPY ./composer* ./
RUN <<EOF
  set -euo pipefail
  composer install --no-plugins --no-scripts --no-dev --no-autoloader --ignore-platform-reqs
EOF

FROM base AS php
COPY --from=vendor /app/composer* ./
COPY --from=vendor /app/vendor ./vendor
COPY ./index.php ./index.php
COPY ./src ./src
COPY ./bin ./bin
RUN <<EOF
  set -euo pipefail
  composer dump-autoload --no-plugins --no-scripts --no-dev --classmap-authoritative --strict-psr --strict-ambiguous
  composer audit --no-plugins --no-scripts --no-dev
  composer check-platform-reqs --no-plugins --no-scripts --no-dev
  composer validate --no-plugins --no-scripts --strict --with-dependencies --check-lock
  mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
EOF
COPY ./ops/php/z.ini /usr/local/etc/php/conf.d/z.ini
COPY ./ops/php/zz.ini /usr/local/etc/php/conf.d/zz.ini
COPY ./ops/php/entrypoint.sh /usr/local/bin/docker-php-entrypoint

FROM versionednginx AS nginx
WORKDIR /var/www/html
COPY ./ops/nginx /etc/nginx
COPY ./public ./
RUN <<EOF
	set -euo pipefail
	openssl req -x509 -newkey rsa:4096 -nodes -sha256 -keyout /etc/nginx/snakeoil.key -out /etc/nginx/snakeoil.pem -days 3650 -subj "/CN=localhost" -addext "subjectAltName=DNS:*.localhost,DNS:localhost"
EOF

FROM versionedmysql AS mysql
COPY ./ops/mysql /docker-entrypoint-initdb.d

FROM versionedoracle AS oracle

FROM versionedvalkey AS valkey
