<?php
class Git_Wrapper {
	function __construct( $repo_dir ) {
		$this->repo_dir = $repo_dir;
	}

	protected function _call() {
		$args = func_get_args();
		$args = join( ' ', array_map( 'escapeshellarg', $args ) );
		$cmd  = "cd $this->repo_dir ; git $args";
		exec( $cmd, $response, $return );
		_log( $cmd, $response, $return );

		return array( $return, $response );
	}

	function can_exec_git() {
		list( $return, $response ) = $this->_call( 'version' );
		return ( 0 == $return );
	}

	function is_versioned() {
		list( $return, $response ) = $this->_call( 'status' );
		return ( 0 == $return );
	}

	function has_remote() {
		list( $return, $response ) = $this->_call( 'remote', 'show', '-n' );
		return ( 0 == $return && in_array( 'origin', $response ) );
	}

	function init() {
		list( $return, $response ) = $this->_call( 'init' );
		return ( 0 == $return );
	}

	function add_remote_url( $url ) {
		if ( FALSE === strpos( $url, 'http://' ) ) $url = "ssh://$url";
		list( $return, $response ) = $this->_call( 'remote', 'add', 'origin', $url );
		return ( 0 == $return );
	}

	function get_remote_url() {
		list( $return, $response ) = $this->_call( 'config', '--get', 'remote.origin.url' );
		if ( isset( $response[0] ) )
			return $response[0];
		return '';
	}

	function fetch_ref() {
		list( $return, $response ) = $this->_call( 'fetch', 'origin' );
		return ( 0 == $return );
	}

	function merge_with_accept_mine() {
		list( $return, $response ) = $this->_call( 'merge', '-s', 'recursive', '-X', 'ours' );
		return ( 0 == $return );
	}

	function add_wp_content() {
		list( $return, $response ) = $this->_call( 'add', 'wp-content' );
		return ( 0 == $return );
	}

	function get_remote_branches() {
		list( $return, $response ) = $this->_call( 'branch', '-r' );
		return $response;
	}

	function add() {
		$paths = func_get_args();
		if ( ! empty( $paths ) ) {
			foreach ( $paths as $path ) {
				$this->_call( 'add', '--no-ignore-removal', $path );
			}
		}
	}

	function commit( $message ) {
		$this->_call( 'commit','-m', $message );
	}

	function push( $repo, $branch ) {
		$this->_call( 'push', $repo, $branch );
	}

	function track_branch( $branch_name ) {
		list( $return, $response ) = $this->_call( 'branch', '-t', $branch_name );
		return ( 0 == $return );
	}

	/*
	 * Get uncommited changes
	 * git status --porcelain
	 * This should return an array like:
	
	 array(
	    plugins => autover/autover.php = deleted
	               toplytics/toplytcs.php = modified
	    themes => twentyten/style.css = modified
	              twentyten/foo.php = deleted
	    others => nasty/cache/script.js = modified
	              foo/bar.css = deleted
	 )
	 * array (
	 *   'plugins' => array( OF MODIFIED PLUGINS ),
	 *   'themes' => array( OF MODIFIED THEMES ),
	 *   'others' => array( OF MODIFIED MISC FILES )
	 * )
	 */
	function get_uncommited_changes() {
		list( $return, $response ) = $this->_call( 'status', '--porcelain' );
		$new_response = array();
		if ( ! empty( $response ) ) {
			foreach ( $response as $item ) :
				$x    = substr( $item, 0, 1 ); // X shows the status of the index
				$y    = substr( $item, 1, 1 ); // Y shows the status of the work tree
				$file = substr( $item, 3 );

				if ( 'D' == $y )
					$action = 'deleted';
				else
					$action = 'modified';

				$new_response[ $file ] = $action;
				continue;

				$group = 'others';
				if ( 0 === strpos( $file, 'wp-content/plugins/' ) )
					$group = 'plugins';

				if ( 0 === strpos( $file, 'wp-content/themes/' ) )
					$group = 'themes';

				switch ( $group ) {
					case 'plugins':
						$new_file = trim( substr( $file, strlen( 'wp-content/plugins/' ) ), '/' );
					break;
					case 'themes';
						$new_file = trim( substr( $file, strlen( 'wp-content/themes/' ) ), '/' );
					break;
					case 'others';
						$new_file = trim( $file, '/' );
					break;
				}
				$new_response[ $group ][ $new_file ] = $action;
			endforeach;
		}
		return $new_response;
	}

	/*
	 * Checks if repo has uncommited changes
	 * git status --porcelain
	 */
	function is_dirty() {
		$changes = $this->get_uncommited_changes();
		return ! empty( $changes );
	}

	/*
	 * Commit local changes
	 * git add --no-ignore-removal
	 */
	function commit_changes() {
		$paths = func_get_args();
		if ( 0 == func_num_args() )
			$paths = array( '.' );
		foreach ( $paths as $path ) {
			$this->add( $path );
		}
	}

	/*
	 * Pull changes from remote. By default accept local changes on conflicts
	 */
	function pull() {
		$this->_call( 'pull' );
	}
}
$git = new Git_Wrapper( dirname( WP_CONTENT_DIR ) );
