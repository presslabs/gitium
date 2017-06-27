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

function gitium_error_log( $message ) {
	if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) { return; }
	error_log( "gitium_error_log: $message" );
}

function wp_content_is_versioned() {
	return file_exists( WP_CONTENT_DIR . '/.git' );
}

if ( ! function_exists( 'gitium_enable_maintenance_mode' ) ) :
	function gitium_enable_maintenance_mode() {
		$file = ABSPATH . '/.maintenance';

		if ( false === file_put_contents( $file, '<?php $upgrading = ' . time() .';' ) ) {
			return false;
		} else {
			return true;
		}
	}
endif;

if ( ! function_exists( 'gitium_disable_maintenance_mode' ) ) :
	function gitium_disable_maintenance_mode() {
		return unlink( ABSPATH . '/.maintenance' );
	}
endif;

function gitium_get_versions() {
	$versions = get_transient( 'gitium_versions' );
	if ( empty( $versions ) ) {
		$versions = gitium_update_versions();
	}
	return $versions;
}

function _gitium_commit_changes( $message, $dir = '.' ) {
	global $git;

	list( , $git_private_key ) = gitium_get_keypair();
	$git->set_key( $git_private_key );

	$git->add( $dir );
	gitium_update_versions();
	$current_user = wp_get_current_user();
	return $git->commit( $message, $current_user->display_name, $current_user->user_email );
}

function _gitium_format_message( $name, $version = false, $prefix = '' ) {
	$commit_message = "`$name`";
	if ( $version ) {
		$commit_message .= " version $version";
	}
	if ( $prefix ) {
		$commit_message = "$prefix $commit_message";
	}
	return $commit_message;
}

/**
 * This function return the basic info about a path.
 *
 * base_path - means the path after wp-content dir (themes/plugins)
 * type      - can be file/theme/plugin
 * name      - the file name of the path, if it is a file, or the theme/plugin name
 * version   - the theme/plugin version, othewise null
 */
/* Some examples:

  with 'wp-content/themes/twentyten/style.css' will return:
  array(
    'base_path' => 'wp-content/themes/twentyten'
    'type'      => 'theme'
    'name'      => 'TwentyTen'
    'version'   => '1.12'
  )

  with 'wp-content/themes/twentyten/img/foo.png' will return:
  array(
    'base_path' => 'wp-content/themes/twentyten'
    'type'      => 'theme'
    'name'      => 'TwentyTen'
    'version'   => '1.12'
  )

  with 'wp-content/plugins/foo.php' will return:
  array(
    'base_path' => 'wp-content/plugins/foo.php'
    'type'      => 'plugin'
    'name'      => 'Foo'
    'varsion'   => '2.0'
  )

  with 'wp-content/plugins/autover/autover.php' will return:
  array(
    'base_path' => 'wp-content/plugins/autover'
    'type'      => 'plugin'
    'name'      => 'autover'
    'version'   => '3.12'
  )

  with 'wp-content/plugins/autover/' will return:
  array(
    'base_path' => 'wp-content/plugins/autover'
    'type'      => 'plugin'
    'name'      => 'autover'
    'version'   => '3.12'
  )
*/
function _gitium_module_by_path( $path ) {
	$versions = gitium_get_versions();

	// default values
	$module   = array(
		'base_path' => $path,
		'type'      => 'file',
		'name'      => basename( $path ),
		'version'   => null,
	);

	// find the base_path
	$split_path = explode( '/', $path );
	if ( 2 < count( $split_path ) ) {
		$module['base_path'] = "{$split_path[0]}/{$split_path[1]}/{$split_path[2]}";
	}

	// find other data for theme
	if ( array_key_exists( 'themes', $versions ) && 0 === strpos( $path, 'wp-content/themes/' ) ) {
		$module['type'] = 'theme';
		foreach ( $versions['themes'] as $theme => $data ) {
			if ( 0 === strpos( $path, "wp-content/themes/$theme" ) ) {
				$module['name']    = $data['name'];
				$module['version'] = $data['version'];
				break;
			}
		}
	}

	// find other data for plugin
	if ( array_key_exists( 'plugins', $versions ) && 0 === strpos( $path, 'wp-content/plugins/' ) ) {
		$module['type'] = 'plugin';
		foreach ( $versions['plugins'] as $plugin => $data ) {
			if ( '.' === dirname( $plugin ) ) { // single file plugin
				if ( "wp-content/plugins/$plugin" === $path ) {
					$module['base_path'] = $path;
					$module['name']      = $data['name'];
					$module['version']   = $data['version'];
					break;
				}
			} else if ( 'wp-content/plugins/' . dirname( $plugin ) === $module['base_path'] ) {
				$module['name']    = $data['name'];
				$module['version'] = $data['version'];
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

	if ( ! empty( $msg_append ) ) {
		$msg_append = "($msg_append)";
	}
	foreach ( $uncommited_changes as $path => $action ) {
		$change = _gitium_module_by_path( $path );
		$change['action'] = $action;
		$commit_groups[ $change['base_path'] ] = $change;
	}

	foreach ( $commit_groups as $base_path => $change ) {
		$commit_message = _gitium_format_message( $change['name'], $change['version'], "${change['action']} ${change['type']}" );
		$commit = _gitium_commit_changes( "$commit_message $msg_append", $base_path, false );
		if ( $commit ) {
			$commits[] = $commit;
		}
	}

	return $commits;
}

function gitium_commit_and_push_gitignore_file( $path = '' ) {
	global $git;

	$current_user = wp_get_current_user();
	if ( ! empty( $path ) ) { $git->rm_cached( $path ); }
	$git->add( '.gitignore' );
	$commit = $git->commit( 'Update the `.gitignore` file', $current_user->display_name, $current_user->user_email );
	gitium_merge_and_push( $commit );
}

if ( ! function_exists( 'gitium_acquire_merge_lock' ) ) :
	function gitium_acquire_merge_lock() {
		$gitium_lock_path   = apply_filters( 'gitium_lock_path', '/tmp/.gitium-lock' );
		$gitium_lock_handle = fopen( $gitium_lock_path, 'w+' );

		$lock_timeout    = intval( ini_get( 'max_execution_time' ) ) > 10 ? intval( ini_get( 'max_execution_time' ) ) - 5 : 10;
		$lock_timeout_ms = 10;
		$lock_retries    = 0;
		while ( ! flock( $gitium_lock_handle, LOCK_EX | LOCK_NB ) ) {
			usleep( $lock_timeout_ms * 1000 );
			$lock_retries++;
			if ( $lock_retries * $lock_timeout_ms > $lock_timeout * 1000 ) {
				return false; // timeout
			}
		}
		gitium_error_log( __FUNCTION__ );
		return array( $gitium_lock_path, $gitium_lock_handle );
	}
endif;

if ( ! function_exists( 'gitium_release_merge_lock' ) ) :
	function gitium_release_merge_lock( $lock ) {
		list( $gitium_lock_path, $gitium_lock_handle ) = $lock;
		gitium_error_log( __FUNCTION__ );
		flock( $gitium_lock_handle, LOCK_UN );
		fclose( $gitium_lock_handle );
	}
endif;

// Merges the commits with remote and pushes them back
function gitium_merge_and_push( $commits ) {
	global $git;

	$lock = gitium_acquire_merge_lock()
		or trigger_error( 'Timeout when gitium lock was acquired', E_USER_WARNING );

	if ( ! $git->fetch_ref() ) {
		return false;
	}

	$merge_status = $git->merge_with_accept_mine( $commits );

	gitium_release_merge_lock( $lock );

	return $git->push() && $merge_status;
}

function gitium_check_after_event( $plugin, $event = 'activation' ) {
	global $git;

	if ( 'gitium/gitium.php' == $plugin ) { return; } // do not hook on activation of this plugin

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

function gitium_update_remote_tracking_branch() {
	global $git;
	$remote_branch = $git->get_remote_tracking_branch();
	set_transient( 'gitium_remote_tracking_branch', $remote_branch );

	return $remote_branch;
}

function _gitium_get_remote_tracking_branch( $update_transient = false ) {
	if ( ! $update_transient && ( false !== ( $remote_branch = get_transient( 'gitium_remote_tracking_branch' ) ) ) ) {
		return $remote_branch;
	} else {
		return gitium_update_remote_tracking_branch();
	}
}

function gitium_update_is_status_working() {
	global $git;
	$is_status_working = $git->is_status_working();
	set_transient( 'gitium_is_status_working', $is_status_working );
	return $is_status_working;
}

function _gitium_is_status_working( $update_transient = false ) {
	if ( ! $update_transient && ( false !== ( $is_status_working = get_transient( 'gitium_is_status_working' ) ) ) ) {
		return $is_status_working;
	} else {
		return gitium_update_is_status_working();
	}
}

function _gitium_status( $update_transient = false ) {
	global $git;

	if ( ! $update_transient && ( false !== ( $changes = get_transient( 'gitium_uncommited_changes' ) ) ) ) {
		return $changes;
	}

	$git_version = get_transient( 'gitium_git_version' );
	if ( false === $git_version ) {
		set_transient( 'gitium_git_version', $git->get_version() );
	}

	if ( $git->is_status_working() && $git->get_remote_tracking_branch() ) {
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

function gitium_get_keypair( $generate_new_keypair = false ) {
	if ( $generate_new_keypair ) {
		$keypair = _gitium_generate_keypair();
		delete_option( 'gitium_keypair' );
		add_option( 'gitium_keypair', $keypair, '', false );
	}
	if ( false === ( $keypair = get_option( 'gitium_keypair', false ) ) ) {
		$keypair = _gitium_generate_keypair();
		add_option( 'gitium_keypair', $keypair, '', false );
	}
	return $keypair;
}

function _gitium_generate_webhook_key() {
	return md5( str_shuffle( 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789.()[]{}-_=+!@#%^&*~<>:;' ) );
}

function gitium_get_webhook_key( $generate_new_webhook_key = false ) {
	if ( $generate_new_webhook_key ) {
		$key = _gitium_generate_webhook_key();
		delete_option( 'gitium_webhook_key' );
		add_option( 'gitium_webhook_key', $key, '', false );
		return $key;
	}
	if ( false === ( $key = get_option( 'gitium_webhook_key', false ) ) ) {
		$key = _gitium_generate_webhook_key();
		add_option( 'gitium_webhook_key', $key, '', false );
	}
	return $key;
}

function gitium_get_webhook() {
	if ( defined( 'GIT_WEBHOOK_URL' ) && GIT_WEBHOOK_URL ) { return GIT_WEBHOOK_URL; }
	$key = gitium_get_webhook_key();
	$url = add_query_arg( 'key', $key, plugins_url( 'gitium-webhook.php', __FILE__ ) );
	return apply_filters( 'gitium_webhook_url', $url, $key );
}

function gitium_admin_init() {
	global $git;

	$git_version = get_transient( 'gitium_git_version' );
	if ( false === $git_version ) {
		set_transient( 'gitium_git_version', $git->get_version() );
	}
}
add_action( 'admin_init', 'gitium_admin_init' );
