FROM php:8.2-apache

# Enable mod_rewrite for clean URLs (optional)
RUN a2enmod rewrite

# Copy your PHP files into the Apache webroot
COPY . /var/www/html/

# If you need MySQL extensions (for SQL)
RUN docker-php-ext-install mysqli pdo pdo_mysql

EXPOSE 80