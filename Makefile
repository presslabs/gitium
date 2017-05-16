PHPUNIT             := $(shell pwd)/vendor/bin/phpunit
INSTALL_WP_TESTS    := $(shell pwd)/bin/install-wp-tests.sh

test:
	$(PHPUNIT) --config phpunit.xml $(ARGS)

html-report:
	$(MAKE) test ARGS="--coverage-html coverage $(ARGS)"

env_latest: composer-install
	bash $(INSTALL_WP_TESTS) wordpress wordpress wordpress ${WORDPRESS_DB_HOST} latest true

env_nightly: composer-install
	bash $(INSTALL_WP_TESTS) wordpress wordpress wordpress ${WORDPRESS_DB_HOST} nightly true

composer-install: clean
	@composer install --no-plugins --no-scripts --prefer-dist --no-interaction

start-testing: build-test-env
	@echo "\nStarting up docker containers..."
	@docker-compose up -d
	@echo "\nDropping you to an interactive shell as 'www-data'.\nHappy Testing!\n"
	@docker exec -it -u www-data gitium bash

build-test-env:
	@echo "\nChecking for changes of docker image and rebuilding if needed.\nPlease wait ...\n"
	@docker build -q -t pl-wordpress ./test-env/

clean:
	@-rm -rf /tmp/wordpress
	@-rm /tmp/wordpress.tar.gz /tmp/wordpress-*.tar.gz
	@-rm -rf /tmp/wordpress-tests-lib
	@-mysqladmin drop  wordpress_test --user root --force

.PHONY: test html-report \
    env_latest env_nightly composer-install \
    build-test-env start-testing clean \
