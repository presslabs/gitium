PHPUNIT        := $(CURDIR)/vendor/bin/phpunit

# the WordPress version the tests run against
WP_VERSION     ?= 7.1.2
WP_CORE_DIR    ?= /tmp/wordpress-$(WP_VERSION)
WP_DEVELOP_DIR ?= /tmp/wordpress-develop-$(WP_VERSION)
WP_TESTS_DIR   ?= $(WP_DEVELOP_DIR)/tests/phpunit
export WP_CORE_DIR WP_TESTS_DIR

# The database is set with the WORDPRESS_TEST_DB_HOST, WORDPRESS_TEST_DB_NAME, WORDPRESS_TEST_DB_USER and
# WORDPRESS_TEST_DB_PASSWORD env vars (see tests/wp-tests-config.php). All its tables are dropped by the tests.
test: $(PHPUNIT) $(WP_CORE_DIR)/wp-includes/version.php $(WP_TESTS_DIR)/includes/bootstrap.php
	$(PHPUNIT) --configuration phpunit.xml $(ARGS)

html-report:
	$(MAKE) test ARGS="--coverage-html coverage $(ARGS)"

clover-report:
	$(MAKE) test ARGS="--verbose --coverage-clover build/logs/clover.xml $(ARGS)"

$(PHPUNIT): composer.json
	composer install --prefer-dist --no-interaction --no-progress
	@touch $@

# WordPress, as released
$(WP_CORE_DIR)/wp-includes/version.php:
	@rm -rf $(WP_CORE_DIR) && mkdir -p $(WP_CORE_DIR)
	curl -fsSL https://wordpress.org/wordpress-$(WP_VERSION).tar.gz | tar -xz --no-same-owner --strip-components 1 -C $(WP_CORE_DIR)

# the WordPress test suite, from the wordpress-develop repository
$(WP_TESTS_DIR)/includes/bootstrap.php:
	@rm -rf $(WP_DEVELOP_DIR) && mkdir -p $(WP_DEVELOP_DIR)
	curl -fsSL https://github.com/WordPress/wordpress-develop/archive/$(WP_VERSION).tar.gz \
		| tar -xz --no-same-owner --strip-components 1 -C $(WP_DEVELOP_DIR) wordpress-develop-$(WP_VERSION)/tests/phpunit

clean:
	@-rm -rf $(WP_CORE_DIR) $(WP_DEVELOP_DIR) vendor

.PHONY: test html-report clover-report clean
