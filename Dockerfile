# use the one of your preference
FROM php:8.1-apache

# Set your working directory
WORKDIR /var/www/html

# Install the packages you need
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libonig-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    libzip-dev \
    zip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    unzip \
    git \
    curl

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install the extensions of your preference
RUN docker-php-ext-install pdo_mysql mbstring zip exif pcntl
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install gd
RUN a2enmod rewrite

# sudo a2enmod rewrite && sudo service apache2 restart
# Copy existing application directory contents
COPY . .

# Copy existing application directory permissions
# COPY --chown=www:www . .

# CMD ["apache2ctl", "-D", "FOREGROUND"]