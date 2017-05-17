# 1 - Pull from wordpress oficial image
FROM wordpress:php7.1

# 2 - Install requirements for wp-cli support and WP tests
RUN apt-get update \
  && apt-get install -y sudo less mysql-client git subversion zip unzip\
  && rm -rf /var/lib/apt/lists/*

# 3 - XDebug install for coverage generation
RUN pecl install -o -f xdebug-2.5.3 \
    && rm -rf /tmp/pear \
    && php -i | grep php.ini \
    && echo "zend_extension=/usr/local/lib/php/extensions/no-debug-non-zts-20160303/xdebug.so" > /usr/local/etc/php/php.ini

# 4 - Composer install for dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 5 - Add WP-CLI for WP related tasks
RUN curl -o /bin/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
COPY wp-su.sh /bin/wp
RUN chmod +x /bin/wp-cli.phar /bin/wp
