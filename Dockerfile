FROM php:7.4-apache
COPY . /var/www/html
RUN apt-get update && apt-get install -y wget libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev libonig-dev libcurl4-openssl-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install json pdo pdo_mysql zip gd mbstring curl xml  \
    && php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php -r "if (hash_file('sha384', 'composer-setup.php') === '906a84df04cea2aa72f40b5f787e49f22d4c2f19492ac310e8cba5b96ac8b64115ac402c8cd292b8a03482574915d1a8') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
    && php composer-setup.php \
    && php -r "unlink('composer-setup.php');" \
    && mv composer.phar /usr/local/bin/composer \
    && cd /var/www/html \
    && composer install \
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