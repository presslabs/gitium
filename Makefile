PHPUNIT             := $(shell pwd)/vendor/bin/phpunit
INSTALL_WP_TESTS    := $(shell pwd)/bin/install-wp-tests.sh

TITLE ?= $(shell bash -c 'read -p "Site Title: " title; echo $$title')
USR ?= $(shell bash -c 'read -p "Admin User: " user; echo $$user')
EMAIL ?= $(shell bash -c 'read -p "Admin Email: " email; echo $$email')
PASSWORD ?= $(shell bash -c 'read -s -p "Password: " pwd; echo $$pwd')

test:
	$(PHPUNIT) --config phpunit.xml $(ARGS)

html-report:
	$(MAKE) test ARGS="--coverage-html coverage $(ARGS)"

env_latest: composer-install
	@echo "\nInstalling "latest" WP test files ..."
	@bash $(INSTALL_WP_TESTS) wordpress wordpress wordpress ${WORDPRESS_DB_HOST} latest true
	@echo -ne '\n'
	@echo "Done! Run 'make' now for latest tests!\n"

env_nightly: composer-install
	@echo "\nInstalling "nightly" WP test files ..."
	@bash $(INSTALL_WP_TESTS) wordpress wordpress wordpress ${WORDPRESS_DB_HOST} nightly true
	@echo "Done! Run 'make' now for nightly tests!\n"

composer-install: clean
	@echo "Checking and installing composer dependencies ...\nPlease wait..."
	@composer update -q --no-suggest

start-testing:
	@echo "\nStarting up docker containers..."
	@docker-compose up -d
	@echo "\nDropping you to an interactive shell.\nHappy Testing!\n"
	@docker exec -it -u $(shell id -u) gitium bash

clean:
	@-rm -rf /tmp/wordpress
	@-rm -rf /tmp/wordpress-tests-lib

wp-setup:
	@echo "\n"
	@wp core install --url="http://localhost:8000" --title="$(TITLE)" --admin_user="$(USR)" --admin_email="$(EMAIL)" --admin_password="$(PASSWORD)" --skip-email
	@wp plugin activate --all

wp-debug:
	@sed "80s/.*/define\( \'WP_DEBUG\'\, true \)\;/" ../wp-config.php > ../temp.wp-config.php
	@mv ../temp.wp-config.php ../wp-config.php

permissions-fix:
	@sudo chown --recursive $(shell whoami):$(shell whoami) .

.PHONY: test html-report \
    env_latest env_nightly composer-install \
    start-testing clean wp-setup wp-debug
