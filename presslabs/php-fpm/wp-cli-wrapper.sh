#!/bin/sh
# This is a wrapper so that wp-cli can be used in our folder structure
(cd /application/public ; TERM=xterm /usr/local/bin/wp-cli "$@")