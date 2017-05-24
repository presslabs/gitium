PHPUNIT             := $(shell pwd)/vendor/bin/phpunit
INSTALL_WP_TESTS    := $(shell pwd)/presslabs/bin/install-wp-tests.sh

TITLE ?= $(shell bash -c 'read -p "Site Title: " title; echo $$title')
USR ?= $(shell bash -c 'read -p "Admin User: " user; echo $$user')
EMAIL ?= $(shell bash -c 'read -p "Admin Email: " email; echo $$email')
PASSWORD ?= $(shell bash -c 'read -s -p "Password: " pwd; echo $$pwd')

test:
	@echo "\nRunning tests ...\n"
	@$(PHPUNIT) --config phpunit.xml $(ARGS)

coverage:
	@echo "\nRunning tests and generateing HTML coverage report ...\n"
	@$(MAKE) test ARGS="--coverage-html coverage $(ARGS)"
	@echo "\nReport created in /coverage\n"

up:
	@echo "\nStarting local development environment using docker ...\n"
	@sudo docker-compose up -d --remove-orphans
	@echo "\nEnvironment started. Go to http://localhost:8000/ and test it out!"
	@echo "\nUse 'make down' to stop the environment."
	@echo "\nUse 'make log' to listen for the output of containers."

down:
	@echo "Shutting down containers ...\n"
	@sudo docker-compose down
	@echo "\nDevelopment environment is down!"

log:
	@echo "\nListeing for logs ..."
	@sudo docker-compose logs -f -t

bash:
	@echo "\nDropping you to an interactive shell.\nHappy Testing!\n"
	@sudo docker exec gitium-php-fpm chown -R `id -u`:`id -g` /application
	@sudo docker exec -it -u `id -u` gitium-php-fpm bash

env: composer-install
	@echo "\nInstalling "latest" WP distribution and test files ..."
	@bash $(INSTALL_WP_TESTS) wordpress wordpress wordpress gitium-mysql latest true
	@echo -ne '\n'
	@echo "Done! Use 'make' to run tests.\n"

composer-install:
	@echo "Checking and installing composer dependencies ...\nPlease wait..."
	@composer update -q --no-suggest

wp-setup:
	@echo "\n"
	@wp core config --dbname="wordpress" --dbuser="wordpress" --dbpass="wordpress" --dbhost="gitium-mysql"
	@wp core install --url="http://localhost:8000" --title="$(TITLE)" --admin_user="$(USR)" --admin_email="$(EMAIL)" --admin_password="$(PASSWORD)" --skip-email
	@wp plugin activate --all

clean:
	@echo "\nRemoving WP install and database volumes ...\nIgnore any errors that might show during this command.\n"
	@-sudo docker-compose down
	@-sudo rm -rf wp-tests/ wp-includes/ wp-content/ wp-admin/ vendor/ tmp/ public/
	@-sudo rm -f readme.html license.txt *.php composer.lock
	@-sudo docker volume rm gitium_db_data
	@echo "\nDone! You can now start fresh!\n"

permissions-fix:
	@sudo chown --recursive $(shell whoami):$(shell whoami) .

.PHONY: test coverage up down log \
    env env_nightly composer-install \
    bash cleanwp-setup permissions-fix
