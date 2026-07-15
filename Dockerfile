FROM php:8.1-apache
# Ini adalah kunci rahasianya:
RUN docker-php-ext-install mysqli
COPY . /var/www/html/
