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

function _gitium_module_by_path( $path ) {
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
	$versions = gitium_get_versions();
	$module   = array(
		'base_path' => $path,
		'type'      => 'file',
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

// Merges the commits with remote and pushes them back
function gitium_merge_and_push( $commits ) {
	global $git;

	if ( ! $git->fetch_ref() ) {
		return false;
	}
	if ( ! $git->merge_with_accept_mine( $commits ) ) {
		return false;
	}
	if ( ! $git->push() ) {
		return false;
	}
	return true;
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

function _gitium_status( $update_transient = false ) {
	global $git;

	if ( ! $update_transient && ( false !== ( $changes = get_transient( 'gitium_uncommited_changes' ) ) ) ) {
		return $changes;
	}

	$git_version = get_transient( 'gitium_git_version' );
	if ( false === $git_version ) {
		set_transient( 'gitium_git_version', $git->get_version() );
	}

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

function gitium_has_the_minimum_version() {
	return '1.7' <= substr( get_transient( 'gitium_git_version' ), 0, 3 );
}

function gitium_admin_init() {
	global $git;

	$git_version = get_transient( 'gitium_git_version' );
	if ( false === $git_version ) {
		set_transient( 'gitium_git_version', $git->get_version() );
	}
}
add_action( 'admin_init', 'gitium_admin_init' );
