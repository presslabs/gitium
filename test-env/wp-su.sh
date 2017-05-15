#!/bin/sh
# This is a wrapper so that wp-cli can run as the www-data user so that permissions
# remain correct
sudo -u www-data /bin/wp-cli.phar "$@"