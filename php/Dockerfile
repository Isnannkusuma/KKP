FROM php:8.3-cli

WORKDIR /var/www/html

# ============================================
# 1. Install System Dependencies + Supervisor + Node.js
# ============================================
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    supervisor \
    libzip-dev \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# ============================================
# 2. Install PHP Extensions (Redis, PostgreSQL, dll)
# ============================================
RUN docker-php-ext-install pdo pdo_pgsql pdo_mysql mbstring exif pcntl bcmath gd zip \
    && pecl install redis \
    && docker-php-ext-enable redis

RUN printf 'upload_max_filesize=50M\npost_max_size=55M\n' > /usr/local/etc/php/conf.d/uploads.ini

# ============================================
# 3. Install Composer
# ============================================
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ============================================
# 4. Install Dependencies (Fallback jika host tidak punya vendor)
# ============================================
# Copy composer files dulu untuk optimasi cache layer
COPY composer.json composer.lock ./
RUN composer install --no-interaction --optimize-autoloader || true

# Copy package files untuk optimasi cache layer
COPY package.json package-lock.json* ./
RUN npm ci || true

# ============================================
# 5. Copy Source Code (Termasuk vendor & storage dari host)
# ============================================
COPY . .

RUN npm run build || true
# ============================================
# 6. Copy Supervisor Config
# ============================================
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# ============================================
# 7. Setup Permissions
# ============================================
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# ============================================
# 8. Expose Ports
# ============================================
EXPOSE 8000 5173

# ============================================
# 9. Jalankan Entry Script
# ============================================
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

CMD ["/usr/local/bin/entrypoint.sh"]