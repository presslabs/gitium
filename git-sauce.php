<?php
/*
 * Plugin Name: git sauce
 */

define('GIT_BRANCH', 'master');

function git_upgrader_post_install($res, $hook_extra, $result) {
  global $git_changes;
  ob_start();

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

  echo "git add $git_dir\n";
  echo "git commit -m '$commit_message'\n";
  $git_changes = true;
  $out = ob_get_clean();

  error_log($out);
  return $res;
}
add_filter('upgrader_post_install', 'git_upgrader_post_install', 10, 3);

//-----------------------------------------------------------------------------
function git_upgrader_process_complete($upgrader, $hook_extra) {
  global $git_changes;
  ob_start();
  if ($git_changes) {
    echo "git push origin " . GIT_BRANCH . "\n"; 
  }
  $out = ob_get_clean();
  error_log($out);
}
add_action('upgrader_process_complete', 'git_upgrader_process_complete', 11, 2);

