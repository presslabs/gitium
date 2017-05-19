# 1 Inherit from PHPDocker
FROM phpdockerio/php71-fpm:latest

# 2 Install required packages for WordPress
RUN apt-get update \
    && apt-get -y --no-install-recommends install \
        php-memcached \
        php7.1-mysql \
        php7.1-gd \
        php7.1-imap \
        php-imagick \
        php7.1-intl \
        php7.1-mbstring \
        php7.1-phpdbg \
        php-ssh2 \
        php7.1-xmlrpc \
        php-xdebug \
    && apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

# 3 - Install php composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer

# 4 - Add WP-CLI for WP related tasks
RUN curl -o /usr/bin/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
COPY ../wordpress/wp-su.sh /usr/bin/wp
RUN chmod +x /usr/bin/wp-cli.phar /usr/bin/wp

# 5
WORKDIR "/application"
