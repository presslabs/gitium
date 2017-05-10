PHPUNIT             := $(shell pwd)/vendor/bin/phpunit
INSTALL_WP_TESTS    := $(shell pwd)/bin/install-wp-tests.sh

test:
	$(PHPUNIT) --config phpunit.xml $(ARGS)

html-report:
	$(MAKE) test ARGS="--coverage-html coverage $(ARGS)"

clover-report:
	$(MAKE) test ARGS="--verbose --coverage-clover build/logs/clover.xml $(ARGS)"

env_latest: composer-install
	bash $(INSTALL_WP_TESTS) wordpress_test root ''

env_nightly: composer-install
	bash $(INSTALL_WP_TESTS) wordpress_test root '' localhost nightly

composer-install: clean
	composer install --no-plugins --no-scripts --prefer-dist --no-interaction

clean:
	@-rm -rf /tmp/wordpress
	@-rm /tmp/wordpress.tar.gz /tmp/wordpress-*.tar.gz
	@-rm -rf /tmp/wordpress-tests-lib
	@-mysqladmin drop  wordpress_test --user root --force

.PHONY: test html-report clover-report env_latest env_nightly composer-install clean
