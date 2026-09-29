<?php
/**
 * Configuration of the WordPress test suite, used by `make test`.
 *
 * WARNING: the tests DROP ALL TABLES with the prefix below from the database, never use a production database.
 *
 * @package         Gitium
 */

// Path to the WordPress codebase to test, with a trailing slash.
define( 'ABSPATH', rtrim( getenv( 'WP_CORE_DIR' ) ?: '/tmp/wordpress', '/' ) . '/' );

define( 'WP_DEFAULT_THEME', 'default' );
define( 'WP_DEBUG', true );

define( 'DB_HOST', getenv( 'WORDPRESS_TEST_DB_HOST' ) ?: '127.0.0.1' );
define( 'DB_NAME', getenv( 'WORDPRESS_TEST_DB_NAME' ) ?: 'wordpress_test' );
define( 'DB_USER', getenv( 'WORDPRESS_TEST_DB_USER' ) ?: 'wordpress' );
define( 'DB_PASSWORD', getenv( 'WORDPRESS_TEST_DB_PASSWORD' ) ?: 'wordpress' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_';

define( 'AUTH_KEY', 'put your unique phrase here' );
define( 'SECURE_AUTH_KEY', 'put your unique phrase here' );
define( 'LOGGED_IN_KEY', 'put your unique phrase here' );
define( 'NONCE_KEY', 'put your unique phrase here' );
define( 'AUTH_SALT', 'put your unique phrase here' );
define( 'SECURE_AUTH_SALT', 'put your unique phrase here' );
define( 'LOGGED_IN_SALT', 'put your unique phrase here' );
define( 'NONCE_SALT', 'put your unique phrase here' );

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );

define( 'WPLANG', '' );
