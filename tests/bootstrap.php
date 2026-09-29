<?php
/**
 * Gitium provides automatic git version control and deployment for
 * your plugins and themes integrated into wp-admin.
 *
 * Copyright (C) 2014-2025 PRESSINFRA SRL <ping@presslabs.com>
 *
 * Gitium is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Gitium is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Gitium. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package         Gitium
 */

ini_set('error_reporting', E_ALL); // or error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
// with WP_DEBUG Gitium logs every git command it runs, keep them out of the test output
ini_set( 'error_log', getenv( 'GITIUM_TESTS_LOG' ) ?: sys_get_temp_dir() . '/gitium-tests.log' );

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) $_tests_dir = '/tmp/wordpress-tests-lib';

// the WordPress test suite needs the PHPUnit polyfills and our config (it reads the WordPress path from WP_CORE_DIR)
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );
define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );

define( 'WP_PLUGIN_DIR', dirname( __DIR__ ) );

$GLOBALS['wp_tests_options'] = array(
	'active_plugins' => array( 'gitium/gitium.php' ),
);

require $_tests_dir . '/includes/bootstrap.php';
