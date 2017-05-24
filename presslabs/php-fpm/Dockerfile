# 1 Inherit from PHPDocker
FROM phpdockerio/php71-fpm:latest

# 2 Install required packages
RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get -y --no-install-recommends install \
        make \
        mysql-client \
        openssh-client \
        git \
        subversion \
        zip \
        unzip \
        less \
        php-memcached \
        php7.1-mysql \
        php7.1-gd \
        php7.1-imap \
        php-imagick \
        php7.1-intl \
        php7.1-mbstring \
        php7.1-phpdbg \
        php-ssh2 \
        php7.1-xml \
        php7.1-xmlrpc \
        php-xdebug \
    && apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/* \
    # Install php composer globally
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer \
    # Add WP-CLI for WP related tasks
    && curl -o /usr/local/bin/wp-cli https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar

# 3 Copy the WP wrapper for wp-cli to use our custom wp install location
COPY ["./presslabs/php-fpm/wp-cli-wrapper.sh", "/usr/local/bin/wp"]

# 4 Add execution permissions for all users on composer, wp-cli and wp wrapper
RUN chmod +x /usr/local/bin/wp-cli /usr/local/bin/wp /usr/bin/composer

# 5 - Assign the working directory for our WP install
WORKDIR "/application"

# 6 - Make sure we own the working directory
RUN chown -R `id -u www-data` /application
