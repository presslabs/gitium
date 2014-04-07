<?php
/*
 * Plugin Name: git sauce
 */

define('GIT_BRANCH', 'master');
require_once __DIR__ . '/git-wrapper.php';

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

  $commit_message = "$action $type `$name`";
  if ( $version )
    $commit_message .= " version $version";

	$git->add($git_dir);
	$git->commit($commit_message);
  $git_changes = true;

  return $res;
}
add_filter('upgrader_post_install', 'git_upgrader_post_install', 10, 3);

//-----------------------------------------------------------------------------
function git_upgrader_process_complete($upgrader, $hook_extra) {
  global $git_changes, $git;
  if ( $git_changes ) {
 	  $git->pull();
    $git->push('origin', GIT_BRANCH);
  }
}
add_action('upgrader_process_complete', 'git_upgrader_process_complete', 11, 2);

function git_check_post_activate_modifications($plugin) {
  global $git;
  if ( $git->is_dirty() ) {
	  $git->add('.');
    $plugin_data = get_plugin_data( trailingslashit( WP_PLUGIN_DIR ) . $plugin );
    if ( $plugin_data['Name'] ) {
      $name = $plugin_data['Name'];
      $version = $plugin_data['Version'];
    } else {
      $name = $plugin;
    }
    $commit_message = "post activation of `$name`";
    if ( $version )
      $commit_message .= " version $version";
      
    $git->commit($commit_message);	    
 	  $git->pull();
 	  $git->push('origin', GIT_BRANCH);
  }
}
add_action('activated_plugin','git_check_post_activate_modifications',999);


function git_check_post_deactivate_modifications($plugin) {
  global $git;
  if ( $git->is_dirty() ) {
	  $git->add('.');
    $plugin_data = get_plugin_data( trailingslashit( WP_PLUGIN_DIR ) . $plugin );
    if ( $plugin_data['Name'] ) {
      $name = $plugin_data['Name'];
      $version = $plugin_data['Version'];
    } else {
      $name = $plugin;
    }
    $commit_message = "post deactivation of `$name`";
    if ( $version )
      $commit_message .= " version $version";
      
    $git->commit($commit_message);	    
 	  $git->pull();
 	  $git->push('origin', GIT_BRANCH);
  }
}
add_action('deactivated_plugin','git_check_post_deactivate_modifications',999);


//-----------------------------------------------------------------------------
function git_get_checked_plugins() {
  global $checked;
  if ( 'delete-selected' == $_GET['action'] )
    $checked = $_GET['checked'];
}
add_action('plugins.php', 'git_get_checked_plugins');

//-----------------------------------------------------------------------------
function git_check_for_plugin_deletions() {
  global $git, $checked;
	if ( 'true' == $_GET['deleted'] ) {
	  $plugins = join(' ', $cheked);
	  $git->add('.');
	  $git->commit("removed plugins $plugins");
 	  $git->pull();
 	  $git->push('origin', GIT_BRANCH);
	}
}
add_action('load-plugins.php', 'git_check_for_plugin_deletions');

//-----------------------------------------------------------------------------
function git_check_for_themes_deletions() {
  global $git;
	if ( 'true' == $_GET['deleted'] ) {
	  $git->add('.');
	  $git->commit('removed themes');
 	  $git->pull();
 	  $git->push('origin', GIT_BRANCH);
	}
}
add_action('load-themes.php', 'git_check_for_themes_deletions');

//-----------------------------------------------------------------------------
function git_sauce_activation() {
  wp_schedule_event( time(), 'every_5_minutes', 'pl_git_check_modification' );
}
register_activation_hook( 'git-sauce/git-sauce.php', 'git_sauce_activation' );

//-----------------------------------------------------------------------------
function git_cron_job() { // call this function every 5 min
  global $git_changes, $git;
  
  if ( $git->is_dirty() ) {
    _log('is dirty');
	  $git->commit_changes();
	  $git->commit("Update dirty changes!");
	  $git->pull();
	  $git->push('origin', GIT_BRANCH);
    $git_changes = true;
  }
}
add_action('pl_git_check_modification', 'git_cron_job');

//-----------------------------------------------------------------------------
function git_sauce_deactivation() {
  wp_clear_scheduled_hook( 'pl_git_check_modification' );
}
register_deactivation_hook( 'git-sauce/git-sauce.php', 'git_sauce_deactivation' );

