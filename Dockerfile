FROM php:7.4-cli AS development-env-stage
COPY . /var/www/html
WORKDIR /var/www/html
RUN apt-get update && apt-get install -y wget libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev libonig-dev libcurl4-openssl-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install json pdo pdo_mysql zip gd mbstring curl xml  \
    && php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php -r "if (hash_file('sha384', 'composer-setup.php') === '55ce33d7678c5a611085589f1f3ddf8b3c52d662cd01d4ba75c0ee0459970c2200a51f492d557530c71c15d8dba01eae') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
    && php composer-setup.php \
    && php -r "unlink('composer-setup.php');" \
    && mv composer.phar /usr/local/bin/composer \
    && composer install

CMD ["php", "-S", "0.0.0.0:80"]

FROM php:7.4-apache AS production-env-stage
WORKDIR /var/www/html
COPY --from=development-env-stage /var/www/html .
COPY --from=development-env-stage /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=development-env-stage /usr/local/lib/php/extensions/no-debug-non-zts-20190902 /usr/local/lib/php/extensions/no-debug-non-zts-20190902
RUN apt-get update && apt-get install -y libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev libonig-dev libcurl4-openssl-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && cp config/apache/mphp.apache.conf /etc/apache2/sites-available/mphp.apache.conf \
    && a2enmod rewrite \
    && cp config/apache/mphp.apache.conf /etc/apache2/sites-available \
    && a2ensite mphp.apache.conf \
    && touch /var/log/apache2/mphp.error.log /var/log/apache2/mphp.access.log  \
    && chmod 775 /var/log/apache2/mphp.error.log \
    && chmod 775 /var/log/apache2/mphp.access.log \
    && service apache2 restart \
    && usermod -u 1000 www-data \
    && chown www-data:www-data /var/log/apache2/mphp.error.log \
    && chown www-data:www-data /var/log/apache2/mphp.access.log