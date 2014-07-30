<?php
/*
 * Plugin Name: Gitium
 * Version: 0.4-beta
 * Author: PressLabs
 * Author URI: http://www.presslabs.com
 * License: GPL2
 * Description: Keep all your code on git version control system.
 */
/*  Copyright 2014 PressLabs SRL <ping@presslabs.com>

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

require_once __DIR__ . '/git-wrapper.php';
require_once __DIR__ . '/gitium-admin.php';


function _gitium_make_ssh_git_file_exe() {
	$ssh_wrapper = dirname( __FILE__ ) . '/ssh-git';
	$process     = proc_open(
		"chmod -f +x $ssh_wrapper",
		array(
			0 => array( 'pipe', 'r' ),  // stdin
			1 => array( 'pipe', 'w' ),  // stdout
		),
		$pipes
	);
	fclose( $pipes[0] );
}

function enable_maintenance_mode() {
	$file = ABSPATH . '/.maintenance';

	if ( FALSE === file_put_contents( $file, '<?php $upgrading = ' . time() .';' ) )
		return FALSE;
	else
		return TRUE;
}

function disable_maintenance_mode() {
	return unlink( ABSPATH . '/.maintenance' );
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
	//
	// get all themes from WP
	//
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

	if ( ! empty( $theme_versions ) )
		$new_versions['themes'] = $theme_versions;

	//
	// get all plugins from WP
	//
	if ( ! function_exists( 'get_plugins' ) )
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

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

	if ( ! empty( $plugin_versions ) )
		$new_versions['plugins'] = $plugin_versions;

	set_transient( 'gitium_versions', $new_versions );

	return $new_versions;
}
add_action( 'load-plugins.php', 'gitium_update_versions', 999 );

function gitium_get_versions() {
	$versions = get_transient( 'gitium_versions', array() );
	if ( empty( $versions ) )
		$versions = gitium_update_versions();
	return $versions;
}

function _gitium_commit_changes( $message, $dir = '.' ) {
	global $git;

	list( $git_public_key, $git_private_key ) = gitium_get_keypair();
	$git->set_key( $git_private_key );

	$git->add( $dir );
	gitium_update_versions();
	$current_user = wp_get_current_user();
	return $git->commit( $message, $current_user->display_name, $current_user->user_email );
}

function _gitium_format_message( $name, $version = FALSE, $prefix = '' ) {
	$commit_message = "`$name`";
	if ( $version ) {
		$commit_message .= " version $version";
	}
	if ( $prefix ) {
		$commit_message = "$prefix $commit_message";
	}
	return $commit_message;
}

function gitium_upgrader_post_install( $res, $hook_extra, $result ) {
	global $git;

	_gitium_make_ssh_git_file_exe();

	$type    = isset( $hook_extra['type']) ? $hook_extra['type'] : 'plugin';
	$action  = isset( $hook_extra['action']) ? $hook_extra['action'] : 'update';
	$git_dir = $result['destination'];

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
				if ( '.php' != substr( $file, -4 ) ) continue;
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

	if ( empty( $name ) )
		$name = $result['destination_name'];

	$commit_message = _gitium_format_message( $name,$version,"$action $type" );
	$commit = _gitium_commit_changes( $commit_message, $git_dir, FALSE );
	gitium_merge_and_push( $commit );

	return $res;
}
add_filter( 'upgrader_post_install', 'gitium_upgrader_post_install', 10, 3 );

/*
  wp-content/themes/twentyten/style.css => array(
    'base_path' => wp-content/themes/twentyten
    'type' => 'theme'
    'name' => 'TwentyTen'
    'varsion' => 1.12
  )
  wp-content/themes/twentyten/img/foo.png => array(
    'base_path' => wp-content/themes/twentyten
    'type' => 'theme'
    'name' => 'TwentyTen'
    'varsion' => 1.12
  )
  wp-content/plugins/foo.php => array(
    'base_path' => wp-content/plugins/foo.php
    'type' => 'plugin'
    'name' => 'Foo'
    'varsion' => 2.0
  )

  wp-content/plugins/autover/autover.php => array(
    'base_path' => wp-content/plugins/autover
    'type' => 'plugin'
    'name' => 'autover'
    'varsion' => 3.12
  )
  wp-content/plugins/autover/ => array(
    'base_path' => wp-content/plugins/autover
    'type' => 'plugin'
    'name' => 'autover'
    'varsion' => 3.12
  )
*/
function _gitium_module_by_path( $path ) {
	$versions = gitium_get_versions();
	$module   = array(
		'base_path' => $path,
		'type'      => 'other',
		'name'      => basename( $path ),
		'version'   => null,
	);

	if ( 0 === strpos( $path, 'wp-content/themes/' ) ) {
		$module['type'] = 'theme';
		foreach ( $versions['themes'] as $theme => $data ) {
			if ( 0 === strpos( $path, 'wp-content/themes/' . $theme ) ) {
				$module['base_path'] = 'wp-content/themes/' . $theme;
				$module['name']      = $data['name'];
				$module['version']   = $data['version'];
				break;
			}
		}
	}

	if ( 0 === strpos( $path, 'wp-content/plugins/' ) ) {
		$module['type'] = 'plugin';
		foreach ( $versions['plugins'] as $plugin => $data ) {
			if ( basename( $plugin ) == $plugin ) {
				$plugin_base_path = 'wp-content/plugins/' . $plugin;
			} else {
				$plugin_base_path = 'wp-content/plugins/' . dirname( $plugin );
			}
			if ( 0 === strpos( $path, $plugin_base_path ) ) {
				$module['base_path'] = $plugin_base_path;
				$module['name']      = $data['name'];
				$module['version']   = $data['version'];
				break;
			}
		}
	}
	return $module;
}

function gitium_group_commit_modified_plugins_and_themes( $msg_append = '' ) {
	global $git;

	$uncommited_changes = $git->get_local_changes();
	$commit_groups = array();
	$commits = array();

	if ( ! empty( $msg_append ) )
		$msg_append = "($msg_append)";

	foreach ( $uncommited_changes as $path => $action ) {
		$change = _gitium_module_by_path( $path );
		$change['action'] = $action;
		$commit_groups[ $change['base_path'] ] = $change;
	}

	foreach ( $commit_groups as $base_path => $change ) {
		$commit_message = _gitium_format_message( $change['name'], $change['version'], "${change['action']} ${change['type']}" );
		$commit = _gitium_commit_changes( "$commit_message $msg_append", $base_path, FALSE );
		if ( $commit )
			$commits[] = $commit;
	}

	return $commits;
}

// Merges the commits with remote and pushes them back
function gitium_merge_and_push( $commits ) {
	global $git;

	if ( ! $git->fetch_ref() )
		return false;

	if ( ! $git->merge_with_accept_mine( $commits ) )
		return false;

	if ( ! $git->push() )
		return false;

	return true;
}

// Checks for local changes, tries to group them by plugin/theme and pushes the changes
function gitium_auto_push( $msg_prepend = '' ) {
	global $git;
	list( $git_public_key, $git_private_key ) = gitium_get_keypair();
	$git->set_key( $git_private_key );

	$remote_branch = $git->get_remote_tracking_branch();
	$commits = gitium_group_commit_modified_plugins_and_themes( $msg_prepend );
	gitium_merge_and_push( $commits );
	gitium_update_versions();
}
add_action( 'upgrader_process_complete', 'gitium_auto_push', 11, 0 );

function gitium_check_after_event( $plugin, $event = 'activation' ) {
	global $git;

	if ( 'gitium/gitium.php' == $plugin ) return; // do not hook on activation of this plugin

	if ( $git->is_dirty() ) {
		$versions = gitium_update_versions();
		if ( isset( $versions['plugins'][ $plugin ] ) ) {
			$name    = $versions['plugins'][ $plugin ]['name'];
			$version = $versions['plugins'][ $plugin ]['version'];
		} else {
			$name = $plugin;
		}
		gitium_auto_push( _gitium_format_message( $name, $version, "after $event of" ) );
	}
}

function gitium_check_after_activate_modifications( $plugin ) {
	gitium_check_after_event( $plugin );
}
add_action( 'activated_plugin', 'gitium_check_after_activate_modifications', 999 );

function gitium_check_after_deactivate_modifications( $plugin ) {
	gitium_check_after_event( $plugin, 'deactivation' );
}
add_action( 'deactivated_plugin', 'gitium_check_after_deactivate_modifications', 999 );

function gitium_check_for_plugin_deletions() { // Handle plugin deletion
	if ( isset( $_GET['deleted'] ) && 'true' == $_GET['deleted'] )
		gitium_auto_push();
}
add_action( 'load-plugins.php', 'gitium_check_for_plugin_deletions' );

function gitium_check_for_themes_deletions() { // Handle theme deletion
	if ( isset( $_GET['deleted'] ) && 'true' == $_GET['deleted'] )
		gitium_auto_push();
}
add_action( 'load-themes.php', 'gitium_check_for_themes_deletions' );

// Hook to theme/plugin edit page
function gitium_hook_plugin_and_theme_editor_page( $hook ) {
	switch ( $hook ) {
		case 'plugin-editor.php':
			if ( 'te' == $_GET['a'] ) gitium_auto_push();
		break;

		case 'theme-editor.php':
			if ( 'true' == $_GET['updated'] ) gitium_auto_push();
		break;
	}
	return;
}
add_action( 'admin_enqueue_scripts', 'gitium_hook_plugin_and_theme_editor_page' );

function gitium_options_page_check() {
	global $git;
	if ( ! $git->can_exec_git() ) wp_die( 'Cannot exec git' );
	return TRUE;
}

function _gitium_status( $update_transient = false ) {
	global $git;

	if ( ! $update_transient && ( false !== ( $changes = get_transient( 'gitium_uncommited_changes' ) ) ) ) {
		return $changes;
	}

	$git_version = get_transient( 'gitium_version', '' );
	if ( empty( $git_version ) )
		set_transient( 'gitium_version', $git->get_version() );

	if ( $git->is_versioned() && $git->get_remote_tracking_branch() ) {
		if ( ! $git->fetch_ref() ) {
			set_transient( 'gitium_remote_disconnected', $git->get_last_error() );
		} else {
			delete_transient( 'gitium_remote_disconnected' );
		}
		$changes = $git->status();
	} else {
		delete_transient( 'gitium_remote_disconnected' );
		$changes = array();
	}

	set_transient( 'gitium_uncommited_changes', $changes, 12 * 60 * 60 ); // cache changes for half-a-day
	return $changes;
}

function _gitium_ssh_encode_buffer( $buffer ) {
	$len = strlen( $buffer );
	if ( ord( $buffer[0] ) & 0x80 ) {
		$len++;
		$buffer = "\x00" . $buffer;
	}
	return pack( 'Na*', $len, $buffer );
}

function _gitium_generate_keypair() {
	$rsa_key = openssl_pkey_new(
		array(
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		)
	);

	$private_key = openssl_pkey_get_private( $rsa_key );
	openssl_pkey_export( $private_key, $pem ); //Private Key

	$key_info   = openssl_pkey_get_details( $rsa_key );
	$buffer     = pack( 'N', 7 ) . 'ssh-rsa' .
					_gitium_ssh_encode_buffer( $key_info['rsa']['e'] ) .
					_gitium_ssh_encode_buffer( $key_info['rsa']['n'] );
	$public_key = 'ssh-rsa ' . base64_encode( $buffer ) . ' gitium@' . parse_url( get_home_url(), PHP_URL_HOST );

	return array( $public_key, $pem );
}

function gitium_get_keypair( $generate_new_keypair = FALSE ) {
	if ( $generate_new_keypair ) {
		$keypair = _gitium_generate_keypair();
		delete_option( 'gitium_keypair' );
		add_option( 'gitium_keypair', $keypair, '', FALSE );
	}
	if ( FALSE === ( $keypair = get_option( 'gitium_keypair', FALSE ) ) ) {
		$keypair = _gitium_generate_keypair();
		add_option( 'gitium_keypair', $keypair, '', FALSE );
	}
	return $keypair;
}

function _gitium_generate_webhook_key() {
	return md5( str_shuffle( 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789.()[]{}-_=+!@#%^&*~<>:;' ) );
}

function gitium_get_webhook_key( $generate_new_webhook_key = FALSE ) {
	if ( $generate_new_webhook_key ) {
		$key = _gitium_generate_webhook_key();
		delete_option( 'gitium_webhook_key' );
		add_option( 'gitium_webhook_key', $key, '', FALSE );
		return $key;
	}
	if ( FALSE === ( $key = get_option( 'gitium_webhook_key', FALSE ) ) ) {
		$key = _gitium_generate_webhook_key();
		add_option( 'gitium_webhook_key', $key, '', FALSE );
	}
	return $key;
}

function gitium_get_webhook() {
	if ( defined( 'GIT_WEBHOOK_URL' ) && GIT_WEBHOOK_URL ) return GIT_WEBHOOK_URL;
	$key = gitium_get_webhook_key();
	$url = add_query_arg( 'key', $key, plugins_url( 'gitium-webhook.php', __FILE__ ) );
	return apply_filters( 'gitium_webhook_url', $url, $key );
}

function gitium_has_the_minimum_version() {
	global $git;

	return '1.7' <= substr( get_transient( 'gitium_version', '' ), 0, 3 );
}

function gitium_require_minimum_version() {
	if ( current_user_can( 'manage_options' ) && ( ! gitium_has_the_minimum_version() ) ) : ?>
		<div class="error-nag error">
			<p>Gitium requires minimum git version 1.7!</p>
		</div>
	<?php endif;
}
add_action( 'admin_notices', 'gitium_require_minimum_version' );

function gitium_remote_disconnected_notice() {
	if ( current_user_can( 'manage_options' ) && $message = get_transient( 'gitium_remote_disconnected', null ) ) : ?>
		<div class="error-nag error">
			<p>
				Could not connect to remote repository.
				<pre><?php echo esc_html( $message ); ?></pre>
			</p>
		</div>
	<?php endif;
}
add_action( 'admin_notices', 'gitium_remote_disconnected_notice' );
