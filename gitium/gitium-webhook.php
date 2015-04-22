<?php
/*  Copyright 2014-2015 PressLabs SRL <ping@presslabs.com>

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
$wordpress_loader = $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';

require_once $wordpress_loader;
require_once __DIR__ . '/gitium.php';

function exit_with_error( $message ) {
	gitium_disable_maintenance_mode();
	wp_die( $message );
}

$webhook_key = get_option( 'gitium_webhook_key', '' );
if ( ! empty ( $webhook_key ) && isset( $_GET['key'] ) && $webhook_key == $_GET['key'] ) :
	( '1.7' <= substr( $git->get_version(), 0, 3 ) ) or wp_die( 'Gitium plugin require minimum `git version 1.7`!' );

	list( $git_public_key, $git_private_key ) = gitium_get_keypair();
	$git->set_key( $git_private_key );

	gitium_enable_maintenance_mode() or wp_die( 'Could not enable the maintenance mode!...' );

	$commitmsg = sprintf( 'Merged changes from %s on %s', $_SERVER['SERVER_NAME'], date( 'm.d.Y' ) );
	$commits   = array();

	if ( $git->is_dirty() && $git->add() > 0 ) {
		$commits[] = $git->commit( $commitmsg ) or wp_die( 'Could not commit local changes!' );
	}

	$git->fetch_ref() or exit_with_error( 'Cound not fetch from remote repo.' );
	$git->merge_with_accept_mine( $commits ) or exit_with_error( 'Could not merge changes.' );

	gitium_disable_maintenance_mode();

	if ( ! $git->push() ) {
		wp_die( 'Push failed!' . ' <pre>' . $git->get_last_error() . '</pre>' );
	}
	wp_die( $commitmsg , 'Pull done!', array( 'response' => 200 ) );
else :
	wp_die( 'Cheating uh?', 'Cheating uh?', array( 'response' => 403 ) );
endif;
