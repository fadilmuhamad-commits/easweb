# =========================
# Base Image
# =========================
FROM php:8.2-apache

# =========================
# Install system dependencies
# =========================
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    curl \
    nodejs \
    npm \
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
# Enable Apache rewrite
# =========================
RUN a2enmod rewrite

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
# Install Node dependencies & build assets (Vite)
# =========================
RUN npm install && npm run build

# =========================
# Set permissions
# =========================
RUN chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

# =========================
# Apache Document Root to /public
# =========================
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf

# =========================
# Expose port
# =========================
EXPOSE 80

# =========================
# Start Apache
# =========================
CMD ["apache2-foreground"]
