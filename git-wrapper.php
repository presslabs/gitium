<?php
class Git_Wrapper {
	function __construct($repo_dir) {
		$this->repo_dir = $repo_dir;
	}
	
	protected function _call() {
	  $args = func_get_args();
	  $args = join(' ',array_map('escapeshellarg',$args));
	  exec("cd $this->repo_dir ; git $args", $response, $return);
		_log($response, $return);
	  return array($return, $response);
	}

	function add() {
		$paths = func_get_args();
		foreach ($paths as $path) {
		  $this->_call('add','--no-ignore-removal', $path);
		}
	}

	function commit($message) {
    $this->_call('commit','-m', $message);		
	}

	function push($repo, $branch) {
	  //error_log('push');
	  $this->_call('push', $repo, $branch);
	}

	/*
	 * Get uncommited changes
	 * git status --porcelain
	 * This should return an array like:
	 array(
	    plugins => autover/autover.php = deleted
	               toplytics/toplytcs.php = modified
	    themes => twentyten = modified
	    others => nasty/cache/script.js = modified
	              foo/bar.css = deleted
	 )
	 * array (
	 *   'plugins' => array( OF MODIFIED PLUGINS ),
	 *   'themes' => array( OF MODIFIED THEMES ),
	 *   'others' => array( OF MODIFIED MISC FILES )
   * ) 
	 *
	 */
	function get_uncommited_changes() {
	  list($return, $response) = $this->_call('status', '--porcelain');
    $versions = get_option('git_all_versions', array());
	  $new_response = array();
	  foreach ( $response as $item ) :
	    $x = substr($item, 0, 1); // X shows the status of the index
	    $y = substr($item, 1, 1); // Y shows the status of the work tree
	    $file   = substr($item, 3);
	    $new_file   = str_replace('wp-content/plugins/', '', $file);
	    $new_file   = str_replace('wp-content/themes/', '', $new_file);

	    if ( 'D' == $y )
	      $action = 'deleted';
	    else
	      $action = 'modified';

	    if ( array_key_exists( $new_file, $versions['plugins'] ) )
	      $new_response['plugins'][ $new_file ] = $action;
	    else if ( array_key_exists( dirname($new_file), $versions['themes'] ) )
	      $new_response['themes'][ dirname($new_file) ] = $action;
	    else if ( $file == $new_file )
	      $new_response['others'][ $file ] = $action;
	  endforeach;
		return $new_response;
	}

	/*
	 * Checks if repo has uncommited changes
	 * git status --porcelain
	 */
	function is_dirty() {
		return ! empty( $this->get_uncommited_changes() );
	}

	/*
	 * Commit local changes
	 * git add --no-ignore-removal
	 */
	function commit_changes() {
		$paths = func_get_args();
		if ( 0 == func_num_args() )
		  $paths = array('.');
		foreach ($paths as $path) {
		  $this->add($path);
		}
	}

	/*
	 * Pull changes from remote. By default accept local changes on conflicts
	 */
	function pull() {
	  $this->_call('pull');
	}
}

$git = new Git_Wrapper('/home/mario/Documents/wp.lo');
