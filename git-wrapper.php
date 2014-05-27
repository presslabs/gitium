<?php
$gitignore = <<<EOF
wp-admin/
wp-includes/
.htaccess
wp-content/uploads/
wp-content/blogs.dir/
wp-content/upgrade/
wp-content/backup-db/
wp-content/advanced-cache.php
wp-content/wp-cache-config.php
sitemap.xml
*.log
wp-content/cache/
wp-content/backups/
sitemap.xml.gz
wp-config.php

/index.php
/license.txt
/readme.html
/wp-activate.php
/wp-blog-header.php
/wp-comments-post.php
/wp-config-sample.php
/wp-cron.php
/wp-links-opml.php
/wp-load.php
/wp-login.php
/wp-mail.php
/wp-settings.php
/wp-signup.php
/wp-trackback.php
/xmlrpc.php
EOF;

function _git_rrmdir( $dir ) {
	if ( ! empty( $dir ) && is_dir( $dir ) ) {
		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach ( $files as $file ) {
			( is_dir( "$dir/$file" ) ) ? _git_rrmdir( "$dir/$file" ) : unlink( "$dir/$file" );
		}
		return rmdir( $dir );
	}
}

function _git_temp_key_file() {
	$key_file = tempnam( sys_get_temp_dir(), 'ssh-git' );
	return $key_file;
}

class Git_Wrapper {
	function __construct( $repo_dir ) {
		$this->repo_dir    = $repo_dir;
		$this->private_key = '';
	}

	function set_key( $private_key ) {
		$this->private_key = $private_key;
	}

	protected function _call() {
		$args     = func_get_args();
		$args     = join( ' ', array_map( 'escapeshellarg', $args ) );
		$cmd      = "git $args 2>&1";
		$env      = array();
		$return   = -1;
		$response = array();
		$key_file = null;
		
		$env['GIT_SSH'] = dirname( __FILE__ ) . '/ssh-git';
		if ( $this->private_key ) {
			$key_file = _git_temp_key_file();
			chmod( $key_file, 0600 );
			file_put_contents( $key_file, $this->private_key );
			$env['GIT_KEY_FILE'] = $key_file;
		}

		$proc = proc_open(
			$cmd,
			array(
				0 => array( 'pipe', 'r' ),  // stdin
				1 => array( 'pipe', 'w' ),  // stdout
			),
			$pipes,
			$this->repo_dir,
			$env
		);
		fclose( $pipes[0] );

		while ( $line = fgets( $pipes[1] ) )
			$response[] = rtrim( $line, "\n\r" );

		$return = (int)proc_close( $proc );
		_log( $cmd, $env, $response, $return );
		if ( $key_file )
			unlink( $key_file );

		return array( $return, $response );
	}

	protected function _call2() {
		$args = func_get_args();
		$args = join( ' ', array_map( 'escapeshellarg', $args ) );
		$cmd  = "cd $this->repo_dir ; git $args 2>&1";
		exec( $cmd, $response, $return );
		_log( $cmd, $response, $return );

		return array( $return, $response );
	}

	function can_exec_git() {
		list( $return, $response ) = $this->_call( 'version' );
		return ( 0 == $return );
	}

	function is_versioned() {
		list( $return, $response ) = $this->_call( 'status', '-s' );
		return ( 0 == $return );
	}

	function has_remote() {
		list( $return, $response ) = $this->_call( 'remote', 'show', '-n' );
		return ( 0 == $return && in_array( 'origin', $response ) );
	}

	function init() {
		global $gitignore;
		file_put_contents( "$this->repo_dir/.gitignore", $gitignore );
		list( $return, $response ) = $this->_call( 'init' );
		$this->_call( 'config', 'user.email', 'git-sauce@presslabs.com' );
		$this->_call( 'config', 'user.name', 'Git Sauce' );
		$this->_call( 'config', 'push.default', 'matching' );
		return ( 0 == $return );
	}

	function cleanup() {
		_log( "Cleaning up $this->repo_dir/.git" );
		_git_rrmdir( $this->repo_dir . '/.git' );
	}

	function add_remote_url( $url ) {
		list( $return, $response ) = $this->_call( 'remote', 'add', 'origin', $url );
		return ( 0 == $return );
	}

	function get_remote_url() {
		list( $return, $response ) = $this->_call( 'config', '--get', 'remote.origin.url' );
		if ( isset( $response[0] ) )
			return $response[0];
		return '';
	}

	function get_remote_tracking_branch() {
		list( $return, $response ) = $this->_call( 'rev-parse', '--abbrev-ref', '--symbolic-full-name', '@{u}' );
		if ( 0 == $return )
			return $response[0];
		return false;
	}

	function fetch_ref() {
		list( $return, $response ) = $this->_call( 'fetch', 'origin' );
		return ( 0 == $return );
	}

	function merge_with_accept_ours( $branch ) {
		list( $return, $response ) = $this->_call( 'merge', '-s', 'recursive', '-X', 'ours', $branch );
		return ( 0 == $return );
	}

	function merge_with_accept_theirs( $branch ) {
		list( $return, $response ) = $this->_call( 'merge', '-s', 'recursive', '-X', 'theirs', $branch );
		return ( 0 == $return );
	}

	function add_initial_content() {
		list( $return, $response ) = $this->_call( 'add', 'wp-content', '.gitignore' );
		return ( 0 == $return );
	}

	function get_remote_branches() {
		list( $return, $response ) = $this->_call( 'branch', '-r' );
		$response = array_map( 'trim', $response );
		$response = array_map( create_function( '$b', 'return str_replace("origin/","",$b);' ), $response );
		return $response;
	}

	function checkout( $branch ) {
		list( $return, $response ) = $this->_call( 'checkout', '-b', $branch );
		return ( $return == 0 );	
	}

	function checkout_merge( $branch ) {
		list( $return, $response ) = $this->_call( 'checkout', '-m', $branch );
		return ( $return == 0 );	
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
		$this->_call( 'commit', '-m', $message );
	}

	function push( $branch = '' ) {
		if ( $branch )
			list( $return, $response ) = $this->_call( 'push', '--porcelain', '-u', 'origin', $branch );
		else
			list( $return, $response ) = $this->_call( 'push', '--porcelain', '-u', 'origin' );
		return ( $return == 0 );
	}

	function track_branch( $branch_name ) {
		list( $return, $response ) = $this->_call( 'branch', '--track', 'master' );
		return ( 0 == $return );
	}

	/*
	 * Get uncommited changes
	 * git status --porcelain
	 * It returns an array like this:
	
	 array(
		file => deleted|modified
		...
	)

	 */
	function get_uncommited_changes() {
		list( $return, $response ) = $this->_call( 'status', '--porcelain' );
		if ( 0 !== $return )
			return array();
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
		$this->_call( 'pull', '-s', 'recursive', '-X', 'ours' );
	}
}
$git = new Git_Wrapper( dirname( WP_CONTENT_DIR ) );
