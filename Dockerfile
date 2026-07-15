FROM php:8.1-apache

# Instal ekstensi mysqli
RUN docker-php-ext-install mysqli

# 1. Hapus semua konfigurasi modul MPM yang ada di folder mods-enabled
# Ini akan membersihkan semua modul yang konflik
RUN rm -rf /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf

# 2. Paksa aktifkan modul prefork saja
RUN a2enmod mpm_prefork

# 3. Salin aplikasi
COPY . /var/www/html/

# 4. Beri akses
RUN chown -R www-data:www-data /var/www/html
