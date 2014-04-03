<?php
/*
 * Plugin Name: git sauce
 */

define('GIT_SAUCE_VIEW_HOOKS_INFO', FALSE);
define('REPO','/home/mario/Documents/wp.lo');
define('GIT_BRANCH', 'master');

//-----------------------------------------------------------------------------
function log_actions() {
    $fname = '/home/mario/www/action_log/';
    $fname .= str_replace(array('.','/'),'_',$_SERVER['REQUEST_METHOD'].$_SERVER['REQUEST_URI']);
    file_put_contents($fname,current_filter()."($args)\n",FILE_APPEND);
}

function git_upgrader_pre_download($reply, $package, $upgrader) {
  var_dump("upgrader_pre_download", $reply, $package, $upgrader);
  return $reply;
}

function git_upgrader_pre_install($res, $hook_extra) {
  var_dump("upgrader_pre_install", $res, $hook_extra);
  return $res;
}

function git_upgrader_source_selection($source, $remote_source, $upgrader) {
  var_dump("upgrader_source_selection", $source, $remoute_source, $upgrader);
  return $source;
}

function git_upgrader_post_install($res, $hook_extra, $upgrader_result) {
  var_dump("upgrader_post_install", $res, $hook_extra, $upgrader_result);
  return $res;
}

//-----------------------------------------------------------------------------
function git_init() {
  var_dump(ABSPATH);
}
//add_action('init', 'git_init');

//-----------------------------------------------------------------------------
if ( defined('GIT_SAUCE_VIEW_HOOKS_INFO') ) {
  add_action('all','log_actions');
  add_filter('upgrader_pre_download',     'git_upgrader_pre_download', 10, 3);
  add_filter('upgrader_pre_install',      'git_upgrader_pre_install', 10, 2);
  add_filter('upgrader_source_selection', 'git_upgrader_source_selection', 10, 3);
  add_filter('upgrader_post_install',     'git_upgrader_post_install', 10, 2);
}

//-----------------------------------------------------------------------------
function git_upgrader_process_complete($upgrader, $hook_extra) {
  $result = $upgrader->result;
  $relative_dir = str_replace(ABSPATH,'', $result['destination']);
  $git_dir = $relative_dir;

  ob_start();

  $type = $hook_extra['type'];
  if ( 'plugin' == $type ) {
    $filepath    = WP_PLUGIN_DIR . '/' . $hook_extra['plugin'];
    $plugin_data = get_plugin_data( $filepath );
    $name        = $plugin_data['Name'];
    $version     = $plugin_data['Version'];
  } else if ( 'theme' == $type ) {
    $dirpath    = $hook_extra['theme'];
    $theme_data = wp_get_theme( $dirpath );
    $name       = $theme_data->get('Name');
    $version    = $theme_data->get('Version');
  }
  $action = ucwords( $hook_extra['action'] );

  // Action type `Plugin Name` to version #.
  $commit_message = "$action $type `$name` to version $version";

  echo "git add $git_dir\n";
  echo "git commit -m '$commit_message'\n";
  echo "git push origin " . GIT_BRANCH . "\n";

  var_dump("upgrader_process_complete", $upgrader, $hook_extra, $git_dir);
  var_dump($dirpath);
  $out = ob_get_clean();
  error_log($out);
}
add_action('upgrader_process_complete', 'git_upgrader_process_complete', 11, 2);

