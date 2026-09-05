# syntax=docker/dockerfile:1

# La imagen de producción tal como la exige el ecosistema: base fijada a una
# versión completa, HEALTHCHECK propio en forma shell contra /up, y curl
# instalado a propósito aunque la base lo traiga.
FROM dunglas/frankenphp:1.12.7-php8.5-alpine AS base

RUN apk add --no-cache \
    curl \
    icu-libs \
    && install-php-extensions intl pdo_mysql

COPY . /app

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8000/up > /dev/null || exit 1
