# =========================
# Base image
# =========================
FROM php:8.2-fpm

# =========================
# Install system dependencies
# =========================
RUN apt-get update && apt-get install -y \
    nginx \
    git \
    unzip \
    curl \
    nodejs \
    npm \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        zip \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd

# =========================
# Copy Nginx config
# =========================
COPY nginx/default.conf /etc/nginx/sites-available/default

# =========================
# Set working directory
# =========================
WORKDIR /var/www/html

# =========================
# Copy project files
# =========================
COPY . .

# =========================
# Install Composer
# =========================
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# =========================
# Install PHP dependencies
# =========================
RUN composer install --no-dev --optimize-autoloader

# =========================
# Build Vite assets
# =========================
RUN npm ci && npm run build

# =========================
# Permissions
# =========================
RUN chown -R www-data:www-data \
    storage \
    bootstrap/cache

# =========================
# Expose port
# =========================
EXPOSE 80

# =========================
# Start services
# =========================
CMD service nginx start && php-fpm
