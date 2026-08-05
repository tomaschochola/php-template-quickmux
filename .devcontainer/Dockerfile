# syntax=docker/dockerfile:1@sha256:87999aa3d42bdc6bea60565083ee17e86d1f3339802f543c0d03998580f9cb89

FROM docker.io/library/composer:2@sha256:4d71c3c2109c61d5415544264b59ad4087e4c5b7244481723664138fd36d5040 AS versionedcomposer
FROM docker.io/library/node:24-trixie@sha256:ef91212c27702eab90248892b1ac837ba3e903f78385770bd11f7c460e28eaf7 AS versionednode
FROM ghcr.io/php/pie:1.4.5-bin@sha256:14bc484d9be70396e31b0b57222724f780fe91f76aa5294304086dbce386c044 AS versionedpie
FROM docker.io/library/php:8.5-cli-trixie@sha256:f5d2b71350cdc3c2fc807d6679f5bdac2898d7558945105e46308e4659fb37ac

COPY --from=versionedcomposer /usr/bin/composer /usr/local/bin/composer
COPY --from=versionednode /usr/local/bin/node /usr/local/bin/node
COPY --from=versionednode /usr/local/include/node /usr/local/include/node
COPY --from=versionednode /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=versionednode /usr/local/LICENSE /usr/local/share/doc/node/LICENSE

RUN --mount=type=bind,from=versionedpie,source=/pie,target=/usr/local/bin/pie \
    apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get upgrade --yes --no-install-recommends \
    && DEBIAN_FRONTEND=noninteractive apt-get install --yes --no-install-recommends git libicu-dev libtool libzip-dev openssh-client python3 sudo unzip zip \
    && docker-php-ext-install -j"$(nproc)" intl pcntl pdo_mysql zip \
    && pie install --no-cache 'apcu/apcu:5.1.28' \
    && pie install --no-cache 'phpredis/phpredis:6.3.0' \
    && pie install --no-cache 'xdebug/xdebug:3.5.3' \
    && cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && groupadd --gid 1000 devcontainer \
    && useradd --uid 1000 --gid devcontainer --shell /bin/bash --create-home devcontainer \
    && install --directory --owner devcontainer --group devcontainer \
        /home/devcontainer/.composer \
        /home/devcontainer/.local \
        /home/devcontainer/.local/bin \
        /home/devcontainer/.local/lib \
        /home/devcontainer/.local/lib/node_modules \
    && printf '%s\n' 'devcontainer ALL=(root) NOPASSWD:ALL' > /etc/sudoers.d/devcontainer \
    && chmod 0440 /etc/sudoers.d/devcontainer \
    && ln --symbolic ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln --symbolic ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && rm -rf /root/.pie /var/lib/apt/lists/*

COPY php/ "$PHP_INI_DIR/conf.d/"
COPY --chown=devcontainer:devcontainer --from=versionedcomposer /tmp/keys.dev.pub /tmp/keys.tags.pub /home/devcontainer/.composer/

ENV NPM_CONFIG_PREFIX=/home/devcontainer/.local
ENV PATH=/home/devcontainer/.local/bin:${PATH}

USER devcontainer
