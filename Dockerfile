FROM php:8.2-fpm-alpine

# System dependencies
RUN apk add --no-cache \
    nginx \
    curl \
    git \
    unzip \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    nodejs \
    npm

# PHP extensions (LENGKAP untuk Laravel 10)
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    zip \
    bcmath \
    intl \
    exif \
    ctype \
    fileinfo \
    tokenizer

# Working directory
WORKDIR /var/www/html

# Copy app
COPY . .

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# Build Vite
RUN npm install && npm run build

# Nginx config
COPY nginx/default.conf /etc/nginx/http.d/default.conf

# Permissions (INI PENTING)
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

EXPOSE 80

CMD php-fpm -D && nginx -g "daemon off;"
