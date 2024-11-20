FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    librabbitmq-dev \
    libssl-dev \
    libicu-dev \
    wait-for-it

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    soap \
    zip \
    intl \
    sockets

# Install AMQP extension
RUN pecl install amqp && docker-php-ext-enable amqp

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Install dependencies and run setup commands
RUN composer install --no-interaction --optimize-autoloader
RUN php artisan storage:link
RUN php artisan config:clear
RUN php artisan config:cache
RUN php artisan migrate --force
RUN php artisan elasticsearch:setup-index


# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port for Laravel server
EXPOSE 8000

# Install Supervisor
RUN apt-get install -y supervisor

# Copy the Supervisor configuration
COPY supervisord.conf /etc/supervisord.conf

CMD ["supervisord", "-c", "/etc/supervisord.conf"]