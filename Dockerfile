FROM php:8.3-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libonig-dev \
    && docker-php-ext-install pdo_pgsql pgsql mbstring \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

COPY . /var/www/html/

RUN sed -i 's|/var/www/html|/var/www/html/uob-agreements|g' \
    /etc/apache2/sites-available/000-default.conf

RUN mkdir -p /var/www/html/uob-agreements/uploads \
    /var/www/html/storage \
    && chown -R www-data:www-data \
    /var/www/html/uob-agreements/uploads \
    /var/www/html/storage

EXPOSE 80

CMD ["apache2-foreground"]
