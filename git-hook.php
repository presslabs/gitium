<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php'; // load WordPress framework
if ( isset( $_GET['pull'] ) ) : // add later recure pull
	include_once 'git-wrapper.php';
	global $git;
	$git->fetch_ref() or wp_die( 'fetch_ref failed!' );
	$git->merge_with_accept_mine( $commits ) or wp_die( 'merge_with_accept_mine failed!' );
	$git->push() or wp_die( 'push failed!' );
	wp_die( 'Pull done!' );
else :
	wp_die( 'Missing `pull` GET var!' );
endif;
