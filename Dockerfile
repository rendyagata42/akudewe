FROM php:8.1-apache

# 1. Instal ekstensi mysqli
RUN docker-php-ext-install mysqli

# 2. Perbaikan Error "More than one MPM loaded"
# Kita nonaktifkan mpm_event dan mpm_worker agar hanya mpm_prefork yang aktif
RUN a2dismod mpm_event mpm_worker && a2enmod mpm_prefork

# 3. Salin file aplikasi
COPY . /var/www/html/

# 4. Berikan izin akses
RUN chown -R www-data:www-data /var/www/html
