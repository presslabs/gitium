PHPUNIT             := $(shell pwd)/vendor/bin/phpunit
COMPOSER            := $(shell pwd)/composer.phar
INSTALL_WP_TESTS    := $(shell pwd)/bin/install-wp-tests.sh

test:
	$(PHPUNIT) --config phpunit.xml --tap $(ARGS)

report:
	$(MAKE) test ARGS="--coverage-html coverage $(ARGS)"

env_39: composer-update
	bash $(INSTALL_WP_TESTS) wordpress_test root '' localhost 3.9

env_latest: composer-update
	bash $(INSTALL_WP_TESTS) wordpress_test root ''

composer-update: clean
	$(COMPOSER) self-update --stable --clean-backups
	$(COMPOSER) install --quiet --no-plugins --no-scripts --prefer-dist --no-interaction --no-progress

clean:
	-rm -rf /tmp/wordpress
	# rm /tmp/wordpress.tar.gz /tmp/wordpress-*.tar.gz
	-rm -rf /tmp/wordpress-tests-lib
	-mysqladmin drop  wordpress_test --user root --force

.PHONY: all clean env_39 env_latest test report