<?php
/*
 * Plugin Name: git sauce
 */

define('GIT_BRANCH', 'master');
require_once(__DIR__ . '/git-wrapper.php');

function _log() {
	if (func_num_args() == 1 && is_string(func_get_arg(0))) {
		error_log(func_get_arg(0));
	} else {
		ob_start();
		$args = func_get_args();
		foreach ($args as $arg)
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

  switch($type) {
  case 'theme':
    wp_clean_themes_cache();
    $theme_data = wp_get_theme( $result['destination_name'] );
    $name = $theme_data->get('Name');
    $version = $theme_data->get('Version');
    break;
  case 'plugin':
    foreach ($result['source_files'] as $file) {
      if (substr($file,-4) != '.php') continue;
      // every .php file is a possible plugin so we check if it's a plugin
      $filepath = trailingslashit($result['destination']) . $file;
      $plugin_data = get_plugin_data( $filepath );
      if ($plugin_data['Name']) {
        $name = $plugin_data['Name'];
        $version = $plugin_data['Version'];
        // We get info from the first plugin in the package
        break;
      }
    }
    break;
  }

  if (empty($name))
    $name = $result['destination_name'];

  $commit_message = "$action $type `$name`";
  if ($version)
    $commit_message .= " version $version";

	$git->add($git_dir);
	$git->commit($commit_message);
  $git_changes = true;

  return $res;
}
add_filter('upgrader_post_install', 'git_upgrader_post_install', 10, 3);

//-----------------------------------------------------------------------------
function git_upgrader_process_complete($upgrader, $hook_extra) {
  global $git_changes;
  if ($git_changes) {
    _log("git push origin " . GIT_BRANCH);
  }
}
add_action('upgrader_process_complete', 'git_upgrader_process_complete', 11, 2);


//-----------------------------------------------------------------------------
function git_check_for_plugin_deletions() {
	if ($_GET['deleted'] == 'true')
		_log('git check for deletions in wp-content/plugins');
}
add_action('load-plugins.php', 'git_check_for_plugin_deletions');

//-----------------------------------------------------------------------------
function git_check_for_themes_deletions() {
	if ($_GET['deleted'] == 'true')
		_log('git check for deletions in wp-content/plugins');
}
add_action('load-themes.php', 'git_check_for_themes_deletions');

