.PHONY:

all: test

clean:
	rm -rf /tmp/wordpress
	# rm /tmp/wordpress.tar.gz /tmp/wordpress-*.tar.gz
	rm -rf /tmp/wordpress-tests-lib
	mysqladmin drop  wordpress_test --user root --force

env_39:
	./composer.phar install
	bash ./bin/install-wp-tests.sh wordpress_test root '' localhost 3.9

env_latest:
	./composer.phar install
	bash ./bin/install-wp-tests.sh wordpress_test root '' localhost latest

test:
	./vendor/bin/phpunit --tap
