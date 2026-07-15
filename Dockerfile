FROM php:8.1-apache

# Install PHP extensions (adjust according to your needs)
# Tambahkan ini di Dockerfile

ENV TZ=Asia/Jakarta
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache modules

RUN a2enmod rewrite headers

# Set working directory

WORKDIR /var/www/html

# Copy application files

COPY . /var/www/html/

# Set permissions

RUN chown -R www-data:www-data /var/www/html

# Expose port 80

EXPOSE 80

# Copy and setup entrypoint script (fixes MPM conflict on Railway)

COPY docker-entrypoint.sh /usr/local/bin/

RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Start Apache via custom entrypoint

CMD ["/usr/local/bin/docker-entrypoint.sh"]
