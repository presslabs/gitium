# 1
FROM ubuntu:xenial

# 2
MAINTAINER Presslabs Engineering Team <support@presslabs.com>

# 3
RUN set -ex \
    # Add PPA for PHP
    && apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 14AA40EC0831756756D7F66C4F4EA0AAE5267A6C \
    && echo deb http://ppa.launchpad.net/ondrej/php/ubuntu xenial main > '/etc/apt/sources.list.d/ondrej.list' \

    # Update repos
    && apt-get -qq update \

    # Install packages
    && DEBIAN_FRONTEND=noninteractive apt-get install --no-install-recommends -yq \
        ca-certificates \
        sudo \
        make \
        git \
        subversion \
        curl \
        zip \
        unzip \
        # install PHP and extensions
        php7.1-bcmath="7.1.4*" \
        php7.1-cli="7.1.4*" \
        php7.1-curl="7.1.4*"  \
        php7.1-fpm="7.1.4*" \
        php7.1-gd="7.1.4*" \
        php7.1-imap="7.1.4*" \
        php7.1-json="7.1.4*" \
        php7.1-mbstring="7.1.4*" \
        php7.1-mysql="7.1.4*" \
        php7.1-xml="7.1.4*" \
        php7.1-zip="7.1.4*" \
        php7.1-soap="7.1.4*" \
        php7.1-mcrypt="7.1.4*" \
        php7.1-mysql="7.1.4*" \
        php7.1-dev="7.1.4*" \
        php7.1="7.1.4*" \
        php-pear \
        # install mysql client & server
        mysql-client-5.7 \
        mysql-server-5.7

# 4 XDebug Install
RUN pecl install -o -f xdebug-2.5.3 \
    && rm -rf /tmp/pear \
    && echo "zend_extension=/usr/lib/php/20160303/xdebug.so" > /etc/php/7.1/cli/php.ini

# 5 Composer Install
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 6 Create a user to be
ADD create_user.py /create_user.py

# 7
CMD ["/usr/bin/python3", "/create_user.py"]
