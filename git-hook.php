<?php
header( 'Content-Type: text/html' );
define( 'SHORTINIT', TRUE );
$wordpress_loader = $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';

require_once $wordpress_loader;
require_once __DIR__ . '/git-wrapper.php';

$webhook_key = get_option( 'git_webhook_key', '' );
if ( ! empty ( $webhook_key ) && isset( $_GET[ $webhook_key ] ) ) :
	$commitmsg = 'Merged changes from ' . $_SERVER['SERVER_NAME'] . ' on ' . date( 'm.d.Y' );
	$commits   = array();

	if ( $git->is_dirty() && $git->add() > 0 )
		$commits[] = $git->commit( $commitmsg ) or wp_die( 'Could not commit local changes!' );

	$git->fetch_ref() or wp_die( 'fetch_ref failed!' );
	$git->merge_with_accept_mine( $commits ) or wp_die( 'merge_with_accept_mine failed!' );
	$git->push() or wp_die( 'push failed!' );
	wp_die( $commitmsg , 'Pull done!', array( 'response' => 200 ) );
else :
	wp_die( 'Cheating uh?', 'Cheating uh?', array( 'response' => 403 ) );
endif;
