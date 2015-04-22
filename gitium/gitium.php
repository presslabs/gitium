<?php
/*
 * Plugin Name: Gitium
 * Version: 0.5.2-beta
 * Author: PressLabs
 * Author URI: http://www.presslabs.com
 * License: GPL2
 * Description: Keep all your code on git version control system.
 * Text Domain: gitium
 * Domain Path: /languages/
 */
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

define( 'GITIUM_LAST_COMMITS', 20 );

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/inc/class-git-wrapper.php';
require_once __DIR__ . '/inc/class-gitium-admin.php';
require_once __DIR__ . '/inc/class-gitium-help.php';
require_once __DIR__ . '/inc/class-gitium-menu.php';
require_once __DIR__ . '/inc/class-gitium-menu-bubble.php';
require_once __DIR__ . '/inc/class-gitium-submenu-configure.php';
require_once __DIR__ . '/inc/class-gitium-submenu-status.php';
require_once __DIR__ . '/inc/class-gitium-submenu-commits.php';
require_once __DIR__ . '/inc/class-gitium-submenu-settings.php';

/**
 * Load plugin textdomain.
 */
function gitium_load_textdomain() {
	load_plugin_textdomain( 'gitium', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'gitium_load_textdomain' );

function _gitium_make_ssh_git_file_exe() {
	$ssh_wrapper = dirname( __FILE__ ) . '/inc/ssh-git';
	$process = proc_open(
		"chmod -f +x $ssh_wrapper",
		array(
			0 => array( 'pipe', 'r' ),  // stdin
			1 => array( 'pipe', 'w' ),  // stdout
		),
		$pipes
	);
	if ( is_resource( $process ) ) {
		fclose( $pipes[0] );
		proc_close( $process );
	}
}
register_activation_hook( __FILE__, '_gitium_make_ssh_git_file_exe' );

/* Array
(
    [themes] => Array
        (
            [twentytwelve] => `Twenty Twelve` version 1.3
        )
    [plugins] => Array
        (
            [cron-view/cron-gui.php] => `Cron GUI` version 1.03
            [hello-dolly/hello.php] => `Hello Dolly` version 1.6
        )

) */
function gitium_update_versions() {
	// get all themes from WP
	$all_themes = wp_get_themes( array( 'allowed' => true ) );
	foreach ( $all_themes as $theme_name => $theme ) :
		$theme_versions[ $theme_name ] = array(
			'name'    => $theme->Name,
			'version' => null,
			'msg'     => '',
		);
		$theme_versions[ $theme_name ]['msg'] = '`' . $theme->Name . '`';
		$version = $theme->Version;
		if ( ! empty( $version ) ) {
			$theme_versions[ $theme_name ]['msg']     .= " version $version";
			$theme_versions[ $theme_name ]['version'] .= $version;
		}
	endforeach;

	if ( ! empty( $theme_versions ) ) {
		$new_versions['themes'] = $theme_versions;
	}
	// get all plugins from WP
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$all_plugins = get_plugins();
	foreach ( $all_plugins as $name => $data ) :
		$plugin_versions[ $name ] = array(
			'name'    => $data['Name'],
			'version' => null,
			'msg'     => '',
		);
		$plugin_versions[ $name ]['msg'] = "`{$data['Name']}`";
		if ( ! empty( $data['Version'] ) ) {
			$plugin_versions[ $name ]['msg']     .= ' version ' . $data['Version'];
			$plugin_versions[ $name ]['version'] .= $data['Version'];
		}
	endforeach;

	if ( ! empty( $plugin_versions ) ) {
		$new_versions['plugins'] = $plugin_versions;
	}
	set_transient( 'gitium_versions', $new_versions );

	return $new_versions;
}
add_action( 'load-plugins.php', 'gitium_update_versions', 999 );

function gitium_upgrader_post_install( $res, $hook_extra, $result ) {
	_gitium_make_ssh_git_file_exe();

	$type    = isset( $hook_extra['theme']) ? 'theme' : 'plugin';
	$action  = isset( $hook_extra['action']) ? $hook_extra['action'] : 'updated';
	$action  = ( 'install' === $action ) ? 'installed' : $action ;
	$git_dir = $result['destination'];
	$version = '';

	if ( ABSPATH == substr( $git_dir, 0, strlen( ABSPATH ) ) ) {
		$git_dir = substr( $git_dir, strlen( ABSPATH ) );
	}
	switch ( $type ) {
		case 'theme':
			wp_clean_themes_cache();
			$theme_data = wp_get_theme( $result['destination_name'] );
			$name       = $theme_data->get( 'Name' );
			$version    = $theme_data->get( 'Version' );
		break;
		case 'plugin':
			foreach ( $result['source_files'] as $file ) :
				if ( '.php' != substr( $file, -4 ) ) { continue; }
				// every .php file is a possible plugin so we check if it's a plugin
				$filepath    = trailingslashit( $result['destination'] ) . $file;
				$plugin_data = get_plugin_data( $filepath );
				if ( $plugin_data['Name'] ) :
					$name    = $plugin_data['Name'];
					$version = $plugin_data['Version'];
					// We get info from the first plugin in the package
					break;
				endif;
			endforeach;
		break;
	}
	if ( empty( $name ) ) {
		$name = $result['destination_name'];
	}
	$commit_message = _gitium_format_message( $name,$version,"$action $type" );
	$commit = _gitium_commit_changes( $commit_message, $git_dir, false );
	gitium_merge_and_push( $commit );

	return $res;
}
add_filter( 'upgrader_post_install', 'gitium_upgrader_post_install', 10, 3 );

// Checks for local changes, tries to group them by plugin/theme and pushes the changes
function gitium_auto_push( $msg_prepend = '' ) {
	global $git;
	list( , $git_private_key ) = gitium_get_keypair();
	$git->set_key( $git_private_key );

	$commits = gitium_group_commit_modified_plugins_and_themes( $msg_prepend );
	gitium_merge_and_push( $commits );
	gitium_update_versions();
}
add_action( 'upgrader_process_complete', 'gitium_auto_push', 11, 0 );

function gitium_check_after_activate_modifications( $plugin ) {
	gitium_check_after_event( $plugin );
}
add_action( 'activated_plugin', 'gitium_check_after_activate_modifications', 999 );

function gitium_check_after_deactivate_modifications( $plugin ) {
	gitium_check_after_event( $plugin, 'deactivation' );
}
add_action( 'deactivated_plugin', 'gitium_check_after_deactivate_modifications', 999 );

function gitium_check_for_plugin_deletions() { // Handle plugin deletion
	if ( isset( $_GET['deleted'] ) && 'true' == $_GET['deleted'] ) {
		gitium_auto_push();
	}
}
add_action( 'load-plugins.php', 'gitium_check_for_plugin_deletions' );

function gitium_check_for_themes_deletions() { // Handle theme deletion
	if ( isset( $_GET['deleted'] ) && 'true' == $_GET['deleted'] ) {
		gitium_auto_push();
	}
}
add_action( 'load-themes.php', 'gitium_check_for_themes_deletions' );

// Hook to theme/plugin edit page
function gitium_hook_plugin_and_theme_editor_page( $hook ) {
	switch ( $hook ) {
		case 'plugin-editor.php':
			if ( isset( $_GET['a'] ) && 'te' == $_GET['a'] ) { gitium_auto_push(); }
		break;

		case 'theme-editor.php':
			if ( isset( $_GET['updated'] ) && 'true' == $_GET['updated'] ) { gitium_auto_push(); }
		break;
	}
	return;
}
add_action( 'admin_enqueue_scripts', 'gitium_hook_plugin_and_theme_editor_page' );

function gitium_options_page_check() {
	global $git;
	if ( ! $git->can_exec_git() ) { wp_die( 'Cannot exec git' ); }
	return true;
}

function gitium_require_minimum_version() {
	if ( current_user_can( 'manage_options' ) && ( ! gitium_has_the_minimum_version() ) ) :
		global $git;
		$server_git_version = $git->get_version();
		if ( empty( $server_git_version ) ) {
			$git_message = 'There is no git installed on this server.';
		} else {
			$git_message = "The git version `$server_git_version` was found on the server.";
		}
		set_transient( 'gitium_git_version', $server_git_version );
		?><div class="error-nag error">
			<p>Gitium requires minimum git version `1.7`! <?php echo $git_message; ?></p>
		</div><?php
	endif;
}
add_action( 'admin_notices', 'gitium_require_minimum_version' );

function gitium_remote_disconnected_notice() {
	if ( current_user_can( 'manage_options' ) && $message = get_transient( 'gitium_remote_disconnected' ) ) : ?>
		<div class="error-nag error">
			<p>
				Could not connect to remote repository.
				<pre><?php echo esc_html( $message ); ?></pre>
			</p>
		</div>
	<?php endif;
}
add_action( 'admin_notices', 'gitium_remote_disconnected_notice' );
