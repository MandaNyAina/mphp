# --- Stage 1: build the front end (Svelte + TypeScript -> public/build) -----------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.ts svelte.config.js tsconfig.json ./
COPY resources ./resources
RUN npm run build

# --- Stage 2: PHP runtime: no Composer, no Node, no dev tools ---------------------------
FROM php:8.5-apache AS production

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && sed -ri "s#/var/www/html#${APACHE_DOCUMENT_ROOT}#g" /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/hardening.conf \
    && a2enconf hardening \
    && printf 'expose_php=0\nopcache.validate_timestamps=0\n' > /usr/local/etc/php/conf.d/mphp.ini

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# Only storage/ must be writable (logs, cache, uploads, SQLite file).
RUN mkdir -p storage/logs storage/cache storage/uploads \
    && chown -R www-data:www-data storage \
    && php bin/mphp routes:cache

USER www-data
EXPOSE 80
