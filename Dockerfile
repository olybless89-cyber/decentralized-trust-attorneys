FROM php:8.2-apache

# PDO MySQL driver (the app is MySQL-only) + allow .htaccess overrides,
# which this app relies on to block direct access to config.php and /sql/.
RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite headers \
    && sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

COPY . /var/www/html/

RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/uploads

# Render assigns a $PORT at runtime (not known at build time) and routes
# external traffic to it, so Apache's listen port is rewritten on container
# start, not during the build.
ENV PORT=10000
EXPOSE 10000

CMD ["/bin/sh", "-c", "sed -ri -e \"s/Listen 80/Listen ${PORT}/g\" /etc/apache2/ports.conf && sed -ri -e \"s/<VirtualHost \\*:80>/<VirtualHost *:${PORT}>/g\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
