FROM php:8.2-fpm-alpine

# Install system deps
RUN apk add --no-cache \
    nginx \
    curl \
    git \
    unzip \
    libzip-dev \
    oniguruma-dev \
    nodejs \
    npm

# PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mbstring zip

# Set working dir
WORKDIR /var/www/html

# Copy project
COPY . .

# Install composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# Build assets
RUN npm install && npm run build

# Nginx config
COPY nginx/default.conf /etc/nginx/http.d/default.conf

# Permissions
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

CMD php-fpm -D && nginx -g "daemon off;"
