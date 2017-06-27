<?php
ini_set('error_reporting', E_ALL); // or error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) $_tests_dir = '/application/wp-tests';

define( 'WP_PLUGIN_DIR', dirname( __DIR__ ) );

$GLOBALS['wp_tests_options'] = array(
	'active_plugins' => array( 'src/gitium.php' ),
);

require $_tests_dir . '/includes/bootstrap.php';
