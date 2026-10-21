# Stage Composer pour installer les dépendances PHP
FROM composer:2 AS composer

WORKDIR /app

# Copier les fichiers composer
COPY composer.json composer.lock ./

# Installer les dépendances PHP
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Build stage pour les assets JS
FROM node:22.23.4-alpine AS frontend

WORKDIR /app

COPY scripts/check-runtime-security.sh ./scripts/check-runtime-security.sh
RUN sh scripts/check-runtime-security.sh node

# Copier les fichiers de dépendances
COPY package.json yarn.lock ./

# Installer les dépendances Node
RUN yarn install --frozen-lockfile

# Copier le code source ET les vendor (pour Ziggy)
COPY . .
COPY --from=composer /app/vendor ./vendor

# Build les assets (maintenant Ziggy est disponible)
RUN yarn build

# Stage PHP principal
FROM php:8.4.27-fpm-alpine

COPY scripts/check-runtime-security.sh /usr/local/bin/check-runtime-security.sh
RUN sh /usr/local/bin/check-runtime-security.sh php

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    postgresql-dev \
    nginx \
    supervisor \
    netcat-openbsd \
    libatomic \
    libstdc++

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql pgsql bcmath opcache

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application files (exclude .env, use environment variables from Dokploy)
COPY . .
RUN rm -f .env

# Copy built assets from frontend stage
COPY --from=frontend /app/public/build ./public/build
COPY --from=frontend /app/bootstrap/ssr ./bootstrap/ssr
COPY --from=frontend /app/node_modules ./node_modules
COPY --from=frontend /usr/local/bin/node /usr/local/bin/node
RUN sh /usr/local/bin/check-runtime-security.sh node

# Copy vendor from composer stage
COPY --from=composer /app/vendor ./vendor
RUN composer check-platform-reqs --no-dev

# Create storage directories and set permissions
RUN mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Copy nginx config
COPY docker/nginx.conf /etc/nginx/nginx.conf

# Copy supervisor config
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
