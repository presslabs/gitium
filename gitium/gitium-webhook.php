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
 
 // Define possible WordPress root locations
 $try_wp_roots = [
     getenv( 'DOCUMENT_ROOT' ),
     filter_input( INPUT_SERVER, 'DOCUMENT_ROOT', FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
     realpath( $current_dir . '/../../../../../' ),
     realpath( $current_dir . '/../../../../' ),
     realpath( $current_dir . '/../../../' ),
     realpath( $current_dir . '/../../' ),
     realpath( $current_dir . '/../' ),
     $current_dir,
 ];
 
 $wordpress_loader = null;
 foreach ( $try_wp_roots as $root ) {
     if ( $root && file_exists( $root . '/wp-load.php' ) ) {
         $wordpress_loader = $root . '/wp-load.php';
         break;
     }
 }
 
 if ( $wordpress_loader ) {
     require_once $wordpress_loader;
 } else {
     wp_die( 'Error: Unable to locate wp-load.php. Please verify your WordPress installation.', 'Gitium Error', [ 'response' => 500 ] );
 }
 
 require_once __DIR__ . '/functions.php';
 require_once __DIR__ . '/inc/class-git-wrapper.php';
 
 $webhook_key = get_option( 'gitium_webhook_key', '' );
 $get_key     = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
 
 if ( empty( $webhook_key ) || $get_key !== $webhook_key ) {
     wp_die( 'Cheating uh?', 'Gitium Error', [ 'response' => 403 ] );
 }
 
 if ( version_compare( $git->get_version(), '1.7', '<' ) ) {
     wp_die( 'Gitium plugin requires minimum git version 1.7.', 'Gitium Error', [ 'response' => 500 ] );
 }
 
 // Load keypair
 list( $git_public_key, $git_private_key ) = gitium_get_keypair();
 if ( ! $git_public_key || ! $git_private_key ) {
     wp_die( 'Gitium is not ready. SSH keys are missing.', 'Gitium Error', [ 'response' => 403 ] );
 }
 
 $git->set_key( $git_private_key );
 
 $commitmsg = sprintf( 'Merged changes from %s on %s', $_SERVER['SERVER_NAME'], date( 'm.d.Y' ) );
 $commits   = [];
 
 if ( $git->is_dirty() && $git->add() > 0 ) {
     $commit = $git->commit( $commitmsg );
     if ( ! $commit ) {
         wp_die( 'Error: Could not commit local changes.', 'Gitium Error', [ 'response' => 500 ] );
     }
     $commits[] = $commit;
 }
 
 if ( ! gitium_merge_and_push( $commits ) ) {
     $error = $git->get_last_error();
     wp_die( 'Error: Merge & push failed. ' . ( is_string( $error ) ? $error : '' ), 'Gitium Error', [ 'response' => 500 ] );
 }
 
 wp_die( esc_html( $commitmsg ), 'Pull done!', [ 'response' => 200 ] ); 