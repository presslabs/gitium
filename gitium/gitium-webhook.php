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

header( 'Content-Type: text/html' );
define( 'SHORTINIT', true );

$current_dir = __DIR__;

// Define an array of possible WordPress root locations
$try_wp_roots = [
    getenv('DOCUMENT_ROOT'),
    filter_input(INPUT_SERVER, 'DOCUMENT_ROOT', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
    realpath($current_dir . '/../../../../../'),
    realpath($current_dir . '/../../../../'), 
    realpath($current_dir . '/../../../'), // Typical WordPress structure
    realpath($current_dir . '/../../'),    // Alternative structure
    realpath($current_dir . '/../'),       // Closer parent directory
    $current_dir,                          // Fallback to current directory
];

$wordpress_loader = null;

foreach ($try_wp_roots as $root) {
    if ($root && file_exists($root . '/wp-load.php')) {
        $wordpress_loader = $root . '/wp-load.php';
        break;
    }
}

if ($wordpress_loader) {
    require_once $wordpress_loader;
} else {
    die('Error: Unable to locate wp-load.php. Please verify your WordPress installation.');
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/inc/class-git-wrapper.php';

$webhook_key = get_option( 'gitium_webhook_key', '' );
$get_key = filter_input(INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if ( ! empty ( $webhook_key ) && isset( $get_key ) && $webhook_key == $get_key ) :
	( '1.7' <= substr( $git->get_version(), 0, 3 ) ) or wp_die( 'Gitium plugin require minimum `git version 1.7`!' );

	list( $git_public_key, $git_private_key ) = gitium_get_keypair();
	if ( ! $git_public_key || ! $git_private_key )
		wp_die('Not ready.', 'Not ready.', array( 'response' => 403 ));
	else
		$git->set_key( $git_private_key );

	$commits   = array();
	$commitmsg = sprintf( 'Merged changes from %s on %s', $_SERVER['SERVER_NAME'], date( 'm.d.Y' ) );
	
	if ( $git->is_dirty() && $git->add() > 0 ) {
		$commits[] = $git->commit( $commitmsg ) or trigger_error( 'Could not commit local changes!', E_USER_ERROR );
	}
	gitium_merge_and_push( $commits ) or trigger_error( 'Failed merge & push: ' . serialize( $git->get_last_error() ), E_USER_ERROR );

	wp_die( $commitmsg , 'Pull done!', array( 'response' => 200 ) );
else :
	wp_die( 'Cheating uh?', 'Cheating uh?', array( 'response' => 403 ) );
endif;
