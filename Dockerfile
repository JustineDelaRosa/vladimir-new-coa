# Step 1: Define the base image
FROM php:7.4-fpm

# Step 2: Install dependencies and extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    curl \
    libzip-dev \
    libicu-dev

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd fileinfo zip intl

# Step 3: Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Step 4: Copy application code to the image
COPY . /var/www
WORKDIR /var/www

# Step 5: Install the application's dependencies
RUN composer install

# Step 6: Define the command to run your application
CMD php artisan serve --host=0.0.0.0 --port=8000

EXPOSE 8000