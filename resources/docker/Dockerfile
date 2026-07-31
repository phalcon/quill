# syntax=docker/dockerfile:1
ARG PHP_VERSION=8.1

FROM php:${PHP_VERSION}-cli AS base

ARG UID=1000
ARG GID=1000
ARG USER=scribe
ARG GROUP=scribe

# Scribe has nothing to compile - the Zephir parser is pure PHP and there is no
# database, so this stays to PHP, composer and the tools composer needs.
RUN <<EOT
set -eux
groupadd -g "${GID}" "${GROUP}"
useradd -l -m -u "${UID}" -g "${GID}" -s /bin/bash "${USER}"
apt-get update -y
apt-get install -yq --no-install-recommends curl git unzip zip
apt-get clean -y
rm -rf /var/lib/apt/lists/*
EOT

COPY --from=composer/composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /srv
USER ${USER}
