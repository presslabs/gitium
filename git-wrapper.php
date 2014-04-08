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
		return $response;
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
