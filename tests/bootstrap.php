<?php

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) $_tests_dir = '/tmp/wordpress-tests-lib';

define( 'WP_PLUGIN_DIR', dirname( __DIR__ ) );

$GLOBALS['wp_tests_options'] = array(
	'active_plugins' => array( 'gitium/gitium.php' ),
);

require $_tests_dir . '/includes/bootstrap.php';
