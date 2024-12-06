<?php
/*  Copyright 2014-2016 Presslabs SRL <ping@presslabs.com>

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

header( 'Content-Type: text/html' );
define( 'SHORTINIT', true );

$current_dir = __DIR__;

// First, check if wp-load.php exists in the DOCUMENT_ROOT
$wordpress_loader = filter_input(INPUT_SERVER, 'DOCUMENT_ROOT', FILTER_SANITIZE_FULL_SPECIAL_CHARS) . '/wp-load.php';

if ( file_exists( $wordpress_loader ) ) {
    require_once $wordpress_loader;
} else {
    // Try to locate wp-load.php dynamically
    if ( defined( 'WP_CONTENT_DIR' ) ) {
        $web_root = dirname( WP_CONTENT_DIR );
    } else {
        // Fallback: search upward from the current directory
        $web_root = $current_dir;
        while ( $web_root && ! file_exists( $web_root . '/wp-load.php' ) ) {
            $web_root = dirname( $web_root );
        }
    }

    $wordpress_loader = $web_root . '/wp-load.php';

    if ( $web_root && file_exists( $wordpress_loader ) ) {
        require_once $wordpress_loader;
    } else {
        die( 'Error: Unable to locate wp-load.php. Please verify your WordPress installation.' );
    }
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
