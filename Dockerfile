# Use the official PHP image as the base image
FROM php:8.1-apache

# Set the working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    curl \
    unzip \
    wget \
    git \
    libonig-dev \
    libzip-dev \
    libpng-dev \
    libxml2-dev \
    libpq-dev \
    supervisor \
    zip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd zip

# Copy application files
COPY . /var/www/html

# Install Composer dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader

# Install Node.js, NPM, and Vite
RUN curl -sL https://deb.nodesource.com/setup_16.x | bash -
RUN apt-get install -y nodejs npm
RUN npm install -g create-vite

# Set the Apache document root
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!/var/www/html/public!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Set ServerName directive
RUN echo "ServerName office.ontechzambia.tech" >> /etc/apache2/apache2.conf

# Enable Apache modules
RUN a2enmod rewrite
RUN a2enmod headers

# Set the permissions for Laravel storage and bootstrap/cache folders
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html/storage

# Install Node.js dependencies
COPY package*.json ./
RUN npm install

# Generate App Key
RUN php artisan key:generate

# storage link
RUN php artisan storage:link

# Clear cache and configuration
RUN php artisan cache:clear && \
    php artisan config:clear && \
    php artisan route:clear

# Configure Supervisor to run Laravel scheduler
COPY supervisor.conf /etc/supervisor/conf.d/supervisor.conf

# Expose port 80 for HTTP traffic
EXPOSE 80

# Start Apache server
#CMD ["apache2-foreground"]

# Start Apache server and Supervisor
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]
