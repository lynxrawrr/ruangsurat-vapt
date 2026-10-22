FROM php:8.4.26-apache-bookworm@sha256:52f04cd1e398edd0f80afa8330ce73bce61931ddbd0d5bdd919c6f914d02bcf8
RUN a2enmod rewrite headers \
    && sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && mkdir -p /var/lib/ruangsurat /var/www/html/public/uploads \
    && chown -R www-data:www-data /var/lib/ruangsurat /var/www/html/public/uploads /var/run/apache2 /var/lock/apache2 \
    && php -r 'if (!in_array("sqlite", PDO::getAvailableDrivers())) exit(1);'
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/lab.ini /usr/local/etc/php/conf.d/lab.ini
COPY app/ /var/www/html/
COPY poc/ /opt/poc/
COPY docker/entrypoint.sh /usr/local/bin/lab-entrypoint
RUN chmod +x /usr/local/bin/lab-entrypoint
USER www-data
EXPOSE 8080
ENTRYPOINT ["lab-entrypoint"]
