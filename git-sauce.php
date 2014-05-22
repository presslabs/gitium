<?php
/*
 * Plugin Name: Git sauce
 */

define( 'GIT_BRANCH', 'master' );
require_once __DIR__ . '/git-wrapper.php';

//---------------------------------------------------------------------------------------------------------------------
function _log() {
	if ( func_num_args() == 1 && is_string( func_get_arg( 0 ) ) ) {
		;//error_log(func_get_arg(0));
	} else {
		ob_start();
		$args = func_get_args();
		foreach ( $args as $arg )
			var_dump( $arg );
		$out = ob_get_clean();
		error_log( $out );
	}
}

//---------------------------------------------------------------------------------------------------------------------
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
function git_update_versions() {
	$versions = get_transient( 'git_versions', array() );

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

	set_transient( 'git_versions', $new_versions );

	return $new_versions;
}

//---------------------------------------------------------------------------------------------------------------------
function git_get_versions() {
	$versions = get_transient( 'git_versions', array() );
	if ( empty( $versions ) )
		$versions = git_update_versions();
	return $versions;
}

//---------------------------------------------------------------------------------------------------------------------
function _git_commit_changes( $message, $dir = '.', $push_commits = TRUE ) {
	global $git;

	$git->add( $dir );
	$git->commit( $message );
	if ( $push_commits ) {
		$git->pull();
		$git->push( 'origin', GIT_BRANCH );
		git_update_versions();
	}
}

//---------------------------------------------------------------------------------------------------------------------
function _git_format_message( $name, $version = FALSE, $prefix = '' ) {
	$commit_message = "`$name`";
	if ( $version ) {
		$commit_message .= " version $version";
	}
	if ( $prefix ) {
		$commit_message = "$prefix $commit_message";
	}
	return $commit_message;
}

//---------------------------------------------------------------------------------------------------------------------
function git_upgrader_post_install( $res, $hook_extra, $result ) {
	global $git;

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

	$commit_message = _git_format_message( $name,$version,"$action $type" );
	_git_commit_changes( $commit_message, $git_dir, FALSE );

	return $res;
}
add_filter( 'upgrader_post_install', 'git_upgrader_post_install', 10, 3 );

//---------------------------------------------------------------------------------------------------------------------
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
function _git_module_by_path( $path ) {
	$versions = git_get_versions();
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

//---------------------------------------------------------------------------------------------------------------------
function git_group_commit_modified_plugins_and_themes( $msg_append = '' ) {
	global $git;

	$versions = git_get_versions();
	$uncommited_changes = $git->get_uncommited_changes();
	$commit_groups = array();

	if ( ! empty( $msg_append ) )
		$msg_append = "($msg_append)";

	foreach ( $uncommited_changes as $path => $action ) {
		$change = _git_module_by_path( $path );
		$change['action'] = $action;
		$commit_groups[ $change['base_path'] ] = $change;
	}

	foreach ( $commit_groups as $base_path => $change ) {
		$commit_message = _git_format_message( $change['name'], $change['version'], "${change['action']} ${change['type']}" );
		_git_commit_changes( "$commit_message $msg_append", $base_path, FALSE );
	}
}

//---------------------------------------------------------------------------------------------------------------------
function git_pull_and_push( $msg_prepend = '' ) {
	global $git;

	git_group_commit_modified_plugins_and_themes( $msg_prepend );
	$git->pull();
	$git->push( 'origin', GIT_BRANCH );
	git_update_versions();
}
add_action( 'upgrader_process_complete', 'git_pull_and_push', 11, 0 );

//---------------------------------------------------------------------------------------------------------------------
function git_check_post_activate_modifications( $plugin ) {
	global $git;

	if ( 'git-sauce/git-sauce.php' == $plugin ) return; // do not hook on activation of this plugin

	if ( $git->is_dirty() ) {
		$versions = git_update_versions();
		if ( isset( $versions['plugins'][ $plugin ]) ) {
			$name    = $versions['plugins'][ $plugin ]['name'];
			$version = $versions['plugins'][ $plugin ]['version'];
		} else {
			$name = $plugin;
		}
		git_pull_and_push( _git_format_message( $name, $version, 'post activation of' ) );
	}
}
add_action( 'activated_plugin', 'git_check_post_activate_modifications', 999 );

//---------------------------------------------------------------------------------------------------------------------
function git_check_post_deactivate_modifications( $plugin ) {
	global $git;

	if ( 'git-sauce/git-sauce.php' == $plugin ) return; // do not hook on deactivation of this plugin

	if ( $git->is_dirty() ) {
		$versions = git_get_versions();
		if ( isset( $versions['plugins'][ $plugin ] ) ) {
			$name    = $versions['plugins'][ $plugin ]['name'];
			$version = $versions['plugins'][ $plugin ]['version'];
		} else {
			$name = $plugin;
		}
		git_pull_and_push( _git_format_message( $name, $version, 'post deactivation of' ) );
	}
}
add_action( 'deactivated_plugin', 'git_check_post_deactivate_modifications', 999 );

//---------------------------------------------------------------------------------------------------------------------
function git_check_for_plugin_deletions() { // Handle plugin deletion
	if ( isset( $_GET['deleted'] ) && 'true' == $_GET['deleted'] )
		git_pull_and_push();
}
add_action( 'load-plugins.php', 'git_check_for_plugin_deletions' );

//---------------------------------------------------------------------------------------------------------------------
function git_check_for_themes_deletions() { // Handle theme deletion
	if ( isset( $_GET['deleted'] ) && 'true' == $_GET['deleted'] )
		git_pull_and_push();
}
add_action( 'load-themes.php', 'git_check_for_themes_deletions' );

//---------------------------------------------------------------------------------------------------------------------
// Hook to theme/plugin edit page
function git_hook_plugin_and_theme_editor_page( $hook ) {
	switch ( $hook ) {
		case 'plugin-editor.php':
			if ( 'te' == $_GET['a'] ) git_pull_and_push();
		break;

		case 'theme-editor.php':
			if ( 'true' == $_GET['updated'] ) git_pull_and_push();
		break;
	}
	return;
}
add_action( 'admin_enqueue_scripts', 'git_hook_plugin_and_theme_editor_page' );

//---------------------------------------------------------------------------------------------------------------------
function git_options_page_check() {
	global $git;

	if ( ! $git->can_exec_git() ) wp_die( 'Cannot exec git' );
}

//---------------------------------------------------------------------------------------------------------------------
function git_options_page() {
	global $git;

	if ( isset( $_POST['SubmitFetch'] ) && isset( $_POST['remote_url'] ) ) {
		$git->init();
		$git->add_wp_content();
		$git->commit( 'Initial commit' );
		$git->add_remote_url( $_POST['remote_url'] );
		$git->fetch_ref();
		$remote_branches = $git->get_remote_branches();
		if ( empty( $remote_branches ) ) {
			$git->track_branch( 'master' );
			$git->push( 'origin', GIT_BRANCH );
		}
		return;
	}

	if ( ! $git->is_versioned() ) {
		if ( $git->has_remote() )
			git_setup_step2();
		else
			git_setup_step1();
		return;
	}
}

//---------------------------------------------------------------------------------------------------------------------
function git_setup_step1() {
	global $git;

	$remote_url = $git->get_remote_url();
	$key_pair   = '';
	?>
	<div class="wrap">
	<div id="icon-options-general" class="icon32">&nbsp;</div>
	<h2>Status</h2>
	<h3>unconfigured</h3>

	<form action="" method="post">

	<table class="form-table">
	<tr>
		<th scope="row"><label for="remote_url">Remote URL</label></th>
		<td>
			<input type="text" class="regular-text" name="remote_url" id="remote_url" value="<?php echo $remote_url; ?>">
			<p class="description">This URL provide access to a Git repository via SSH, HTTPS, or Subversion.</p>
		</td>
	</tr>

	<tr>
		<th scope="row"><label for="key_pair">Key pair</label></th>
		<td>
			<input type="text" class="regular-text" name="key_pair" id="key_pair" value="<?php echo $key_pair; ?>" readonly="readonly">
			<input type="submit" name="SubmitGenerateKeyPair" class="button" value="Generate key pair" /><br />
		</td>
	</tr>
	</table>

	<p class="submit">
		<input type="submit" name="SubmitFetch" class="button-primary" value="Fetch" />
	</p>

	</form>
	</div>
	<?php
}

//---------------------------------------------------------------------------------------------------------------------
function git_setup_step2() { ?>
	<div class="wrap">
	<div id="icon-options-general" class="icon32">&nbsp;</div>
	<h2>Status</h2>

	<form action="" method="post">

	<table class="form-table">
	<tr>
		<th scope="row"><label for="remote_url">Remote URL</label></th>
		<td>
			<input type="text" class"regular-text" name="remote_url" id="remote_url" value="<?php echo $remote_url; ?>">
			<p class="description">This URL provide access to a git repository via SSH, HTTPS, or Subversion.</p>
		</td>
	</tr>
	</table>

	<p class="submit">
		<input type="submit" name="SubmitMergeAndPush" class="button-primary" value="Merge & Push" />
	</p>
	</form>
	</div>
	<?php
};

//---------------------------------------------------------------------------------------------------------------------
function git_menu() {
	$page = add_menu_page( 'Git Status', 'Code', 'manage_options', __FILE__, 'git_options_page' );
	add_action( "load-$page", 'git_options_page_check' );
}
add_action( 'admin_menu', 'git_menu' );

//---------------------------------------------------------------------------------------------------------------------
function git_add_menu_bubble() {
	global $menu, $git;

	$changes = $git->get_uncommited_changes();
	if ( ! empty( $changes  )  ) :
		$files = array();
		foreach ( $changes as $group  ) {
			if ( is_array( $group ) ) {
				foreach ( $group as $item  )
					$files[] = $item;
			} else {
				$file[] = $group;
			}
		}
		$bubble_count = 7; //count( $files  );
		foreach ( $menu as $key => $value  ) {
			if ( 'git-sauce/git-sauce.php' == $menu[ $key ][2] ) {
				$menu[ $key ][0] .= " <span class='update-plugins count-$bubble_count'><span class='plugin-count'>" . $bubble_count . '</span></span>';
				return;
			}
		}
	endif;
}
add_action( 'admin_menu', 'git_add_menu_bubble' );
