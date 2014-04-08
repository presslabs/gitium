<?php
/*
 * Plugin Name: git sauce
 */

define('GIT_BRANCH', 'master');
require_once __DIR__ . '/git-wrapper.php';

//-----------------------------------------------------------------------------
function _log() {
	if ( func_num_args() == 1 && is_string(func_get_arg(0)) ) {
		error_log(func_get_arg(0));
	} else {
		ob_start();
		$args = func_get_args();
		foreach ( $args as $arg )
			var_dump($arg);
		$out = ob_get_clean();
		error_log($out);
	}
}

//-----------------------------------------------------------------------------
/* Array
(
    [themes] => Array
        (
            [/home/mario/www/wp.lo/wp-content/themes/twentytwelve/twentytwelve] => Twenty Twelve version 1.3
        )
    [plugins] => Array
        (
            [/home/mario/www/wp.lo/wp-content/plugins/cron-view/cron-gui.php] => Cron GUI version 1.03
            [/home/mario/www/wp.lo/wp-content/plugins/hello-dolly/hello.php] => Hello Dolly version 1.6
        )

) */
function git_update_versions() {
  $versions = get_option('git_all_versions', array());

  // get all themes from WP
  $all_themes = wp_get_themes( array( 'allowed' => true ) );
  foreach ( $all_themes as $theme ) :
    $path = trailingslashit( get_template_directory() ) . $theme->Template;
    $theme_versions[ $path ] = $theme->Name;
    $version = $theme->Version;
    if ( '' < $version )
      $theme_versions[ $path ] .= " version $version";
  endforeach;
  if ( ! empty( $theme_versions ) )
    $new_versions['themes'] = $theme_versions;

  // get all plugins from WP
  if ( ! function_exists( 'get_plugins' ) )
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
  $all_plugins = get_plugins();
  foreach ( $all_plugins as $name => $data ) :
    $filepath = trailingslashit( WP_PLUGIN_DIR ) . $name;
    $plugin_versions[ $filepath ] = $data['Name'];
    if ( '' < $data['Version'] )
      $plugin_versions[ $filepath ] .= " version " . $data['Version'];
  endforeach;
  if ( ! empty( $plugin_versions ) )
    $new_versions['plugins'] = $plugin_versions;

  update_option('git_all_versions', $new_versions);
}

//-----------------------------------------------------------------------------
function _git_commit_changes($message, $dir='.', $push_commits=true) {
  global $git;
  $git->add($dir);
  $git->commit($message);
  if ( $push_commits ) {
    $git->pull();
    $git->push('origin', GIT_BRANCH);
  }
  git_update_versions();
}

//-----------------------------------------------------------------------------
function _git_format_message($name, $version=false, $prefix='') {
  $commit_message = "`name`";
  if ( $prefix ) {
    $commit_message = "$prefix $commit_message";
  }
  if ( $version )
    $commit_message .= " version $version";
  return $commit_message;
}

//-----------------------------------------------------------------------------
function git_upgrader_post_install($res, $hook_extra, $result) {
  global $git_changes, $git;

  $type = isset($hook_extra['type']) ? $hook_extra['type'] : 'plugin';
  $action = isset($hook_extra['action']) ? $hook_extra['action'] : 'update';
  $git_dir = $result['destination'];

  if (substr($git_dir, 0, strlen(ABSPATH)) == ABSPATH) {
    $git_dir = substr($git_dir, strlen(ABSPATH));
  }

  switch ( $type ) {
  case 'theme':
    wp_clean_themes_cache();
    $theme_data = wp_get_theme( $result['destination_name'] );
    $name = $theme_data->get('Name');
    $version = $theme_data->get('Version');
    break;
  case 'plugin':
    foreach ( $result['source_files'] as $file ) {
      if ( '.php' != substr($file,-4) ) continue;
      // every .php file is a possible plugin so we check if it's a plugin
      $filepath = trailingslashit($result['destination']) . $file;
      $plugin_data = get_plugin_data( $filepath );
      if ( $plugin_data['Name'] ) {
        $name = $plugin_data['Name'];
        $version = $plugin_data['Version'];
        // We get info from the first plugin in the package
        break;
      }
    }
    break;
  }

  if ( empty( $name ) )
    $name = $result['destination_name'];

  $commit_message = _git_format_message($name,$version,"$action $type");
  _git_commit_changes($commit_message, $git_dir, false);

  return $res;
}
add_filter('upgrader_post_install', 'git_upgrader_post_install', 10, 3);

//-----------------------------------------------------------------------------
function git_upgrader_process_complete($upgrader, $hook_extra) {
  global $git;
  $git->pull();
  $git->push('origin', GIT_BRANCH);
  git_update_versions();
}
add_action('upgrader_process_complete', 'git_upgrader_process_complete', 11, 2);

//-----------------------------------------------------------------------------
function git_check_post_activate_modifications($plugin) {
  global $git;
  if ( $git->is_dirty() ) {
    $plugin_data = get_plugin_data( trailingslashit( WP_PLUGIN_DIR ) . $plugin );
    if ( $plugin_data['Name'] ) {
      $name = $plugin_data['Name'];
      $version = $plugin_data['Version'];
    } else {
      $name = $plugin;
    }
    
    $commit_message = _git_format_message($name,$version,"post activation of");
    _git_commit_changes($commit_message);
  }
}
add_action('activated_plugin','git_check_post_activate_modifications',999);

//-----------------------------------------------------------------------------
function git_check_post_deactivate_modifications($plugin) {
  global $git;
  if ( $git->is_dirty() ) {
    $plugin_data = get_plugin_data( trailingslashit( WP_PLUGIN_DIR ) . $plugin );
    if ( $plugin_data['Name'] ) {
      $name = $plugin_data['Name'];
      $version = $plugin_data['Version'];
    } else {
      $name = $plugin;
    }
    $commit_message = _git_format_message($name,$version,"post deactivation of");
    _git_commit_changes($commit_message);
  }
}
add_action('deactivated_plugin','git_check_post_deactivate_modifications',999);

//-----------------------------------------------------------------------------
function git_admin_enqueue_scripts( $hook ) {
  // hook in `plugins.php` page
  if ( ('plugins.php' == $hook) && isset($_REQUEST['action']) && ('delete-selected' == $_REQUEST['action']) ) {
    $plugins_checked = $_REQUEST['checked'];
	  $name = array();
	  foreach ( $plugins_checked as $plugin ) :
      $plugin_data = get_plugin_data( trailingslashit( WP_PLUGIN_DIR ) . $plugin );
      if ( $plugin_data['Name'] )
        $removed_plugins[] = $plugin_data['Name'];
      else
        $removed_plugins[] = $plugin;
    endforeach;
    update_option('git_removed_plugins', $removed_plugins);
  }
  // hook in `themes.php` page
  if ( ('themes.php' == $hook) && isset($_REQUEST['action']) && ('delete' == $_REQUEST['action']) ) {
    wp_clean_themes_cache();
    $theme_data = wp_get_theme( trailingslashit( get_template_directory() ) . $_REQUEST['stylesheet'] );
    $name = $theme_data->get('Name');
    $version = $theme_data->get('Version');

    if ( '' < $name )
      $removed_theme = $name;
    else
      $removed_theme = $_REQUEST['stylesheet'];

    if ( '' < $version )
      $removed_theme .=  " version $version";

    update_option('git_removed_theme', $removed_theme);
    error_log("theme=`$removed_theme`");
  }
}
add_action('admin_enqueue_scripts', 'git_admin_enqueue_scripts');

//-----------------------------------------------------------------------------
function git_check_for_plugin_deletions() {
  global $git;
	if ( 'true' == $_GET['deleted'] ) {
    $removed_plugins = get_option('git_removed_plugins', array() );
    $commit_message  = "removed plugin";
    if ( 1 < count( $removed_plugins ) )
      $commit_message .= "s";
	  $removed_plugins = '`' . join('`, `', $removed_plugins) . '`';
	}
  _git_commit_changes("$commit_message $removed_plugins");	
}
add_action('load-plugins.php', 'git_check_for_plugin_deletions');

//-----------------------------------------------------------------------------
function git_check_for_themes_deletions() {
  global $git;
	if ( 'true' == $_GET['deleted'] ) {
	  $stylesheet = get_option('git_removed_theme', '');
	  _git_commit_changes("removed theme $stylesheet");
	}
}
add_action('load-themes.php', 'git_check_for_themes_deletions');
