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
.maintenance
EOF;

function _log() {
	if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) return;
	
	if ( func_num_args() == 1 && is_string( func_get_arg( 0 ) ) ) {
		error_log( func_get_arg( 0 ) );
	} else {
		ob_start();
		$args = func_get_args();
		foreach ( $args as $arg )
			var_dump( $arg );
		$out = ob_get_clean();
		error_log( $out );
	}
}

function _git_rrmdir( $dir ) {
	if ( ! empty( $dir ) && is_dir( $dir ) ) {
		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach ( $files as $file ) {
			( is_dir( "$dir/$file" ) ) ? _git_rrmdir( "$dir/$file" ) : unlink( "$dir/$file" );
		}
		return rmdir( $dir );
	}
}

function enable_maintenance_mode() {
	$file = ABSPATH . '/.maintenance';

	if ( FALSE === file_put_contents( $file, '<?php $upgrading = ' . time() .';' ) )
		return FALSE;
	else
		return TRUE;
}

function disable_maintenance_mode() {
	return unlink( ABSPATH . '/.maintenance' );
}

function _git_temp_key_file() {
	$key_file = tempnam( sys_get_temp_dir(), 'ssh-git' );
	return $key_file;
}

class Git_Wrapper {

	private $last_error = '';

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

		if ( defined( 'GIT_SSH' ) && GIT_SSH )
			$env['GIT_SSH'] = GIT_SSH;
		else
			$env['GIT_SSH'] = dirname( __FILE__ ) . '/ssh-git';

		if ( defined( 'GIT_KEY_FILE' ) && GIT_KEY_FILE ) {
			$env['GIT_KEY_FILE'] = GIT_KEY_FILE;
		} elseif ( $this->private_key ) {
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
		/* _log( $cmd, $env, $response, $return ); */
		_log( "$return $cmd", join( "\n", $response ) );
		if ( $key_file )
			unlink( $key_file );

		if ( 0 != $return )
			$this->last_error = join( "\n", $response );
		else
			$this->last_error = null;

		return array( $return, $response );
	}

	function get_last_error() {
		return $this->last_error;
	}

	function can_exec_git() {
		list( $return, $response ) = $this->_call( 'version' );
		return ( 0 == $return );
	}

	function is_versioned() {
		list( $return, $response ) = $this->_call( 'status', '-s' );
		return ( 0 == $return );
	}

	function get_version() {
		list( $return, $version ) = $this->_call( 'version' );
		if ( ! empty( $version[0] ) )
			return substr( $version[0], 12 );
		return '';
	}

	// git rev-list @{u}..
	function get_ahead_commits() {
		list( $return, $commits ) = $this->_call( 'rev-list', '@{u}..' );
		return $commits;
	}

	// git rev-list ..@{u}
	function get_behind_commits() {
		list( $return, $commits  ) = $this->_call( 'rev-list', '..@{u}' );
		return $commits;
	}

	function is_already_up_to_date() {
		$ahead  = count( $git->get_ahead_commits() );
		$behind = count( $git->get_behind_commits() );
		return ( ! $ahead && ! $behind );
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

	function get_local_branch() {
		list( $return, $response ) = $this->_call( 'rev-parse', '--abbrev-ref', 'HEAD' );
		if ( 0 == $return )
			return $response[0];
		return false;
	}

	function fetch_ref() {
		list( $return, $response ) = $this->_call( 'fetch', 'origin' );
		return ( 0 == $return );
	}

	protected function _resolve_merge_conflicts( $message ) {
		$changes = $this->status( true );
		_log( $changes );
		foreach ( $changes as $path => $change ) {
			if ( in_array( $change, array( 'UD', 'DD' ) ) ) {
				$this->_call( 'rm', $path );
				$message .= "\n\tConflict: $path [removed]";
			} elseif ( 'DU' == $change ) {
				$this->_call( 'add', $path );
				$message .= "\n\tConflict: $path [added]";
			} elseif ( in_array( $change, array( 'AA', 'UU', 'AU', 'UA' ) ) ) {
				$this->_call( 'checkout', '--theirs', $path );
				$this->_call( 'add', '--all', $path );
				$message .= "\n\tConflict: $path [local version]";
			}
		}
		$this->commit( $message );
	}

	function get_commit_message( $commit ) {
		list( $return, $response ) = $this->_call( 'log', '--format=%B', '-n', '1', $commit );
		return ( $return !== 0 ? false : join( "\n", $response ) );
	}

	function merge_with_accept_mine() {
		$commits = func_get_args();
		if ( 1 == func_num_args() && is_array( $commits[0] ) )
			$commits = $commits[0];

		$ahead_commits = $this->get_ahead_commits();
		$commits = array_unique( array_merge( array_reverse( $commits ), $ahead_commits ) );
		$commits = array_reverse( $commits );

		$remote_branch = $this->get_remote_tracking_branch();
		$local_branch  = $this->get_local_branch();

		$this->_call( 'branch', '-m', 'merge_local' );
		$this->_call( 'branch', $local_branch, $remote_branch );
		$this->_call( 'checkout', $local_branch );
		foreach ( $commits as $commit ) {
			if ( empty( $commit ) ) return FALSE;

			list( $return, $response ) = $this->_call(
				'cherry-pick', '--strategy', 'recursive', '--strategy-option', 'theirs', $commit
			);
			if ( $return != 0 ) {
				$this->_resolve_merge_conflicts( $this->get_commit_message( $commit ) );
			}
		}

		if ( $this->successfully_merged() ) { // git status without states: AA, DD, UA, AU ...
			$this->_call( 'branch', '-D', 'merge_local' );
			return TRUE;
		} else {
			$this->_call( 'cherry-pick', '--abort' );
			$this->checkout( 'merge_local' );
			$this->_call( 'branch', '-D', $local_branch );
			$this->_call( 'branch', '-m', $local_branch );
			return FALSE;
		}
	}

	function successfully_merged() {
		$changes = array_values( $this->status( true ) );
		return ( 0 == count( array_intersect( $changes, array( 'DD', 'AU', 'UD', 'UA', 'DU', 'AA', 'UU' ) ) ) );
	}

	function merge_initial_commit( $commit, $branch ) {
		list( $return, $response ) = $this->_call( 'branch', '-m', 'initial' );
		if ( 0 != $return )
			return false;

		list( $return, $response ) = $this->_call( 'checkout', $branch );
		if ( 0 != $return )
			return false;

		list( $return, $response ) = $this->_call(
			'cherry-pick', '--strategy', 'recursive', '--strategy-option', 'theirs', $commit
		);
		if ( $return != 0 ) {
			$this->_resolve_merge_conflicts( $this->get_commit_message( $commit ) );
			if ( ! $this->successfully_merged() ) {
				$this->_call( 'cherry-pick', '--abort' );
				$this->_call( 'checkout', 'initial' );
				return FALSE;
			}
		}
		$this->_call( 'branch', '-D', 'initial' );
		return TRUE;
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

	function add() {
		$args = func_get_args();
		if ( 1 == func_num_args() && is_array( $args[0] ) )
			$args = $args[0];

		$params = array_merge( array( 'add', '-n', '--all' ), $args );
		list ( $return, $response ) = call_user_func_array( array( $this, '_call' ), $params );
		$count = count( $response );

		$params = array_merge( array( 'add', '--all' ), $args );
		list ( $return, $response ) = call_user_func_array( array( $this, '_call' ), $params );

		return $count;
	}

	function commit( $message, $author_name = '', $author_email = '' ) {
		$author = '';
		if ( $author_email ) {
			if ( empty( $author_name ) )
				$author_name = $author_email;
			$author = "$author_name <$author_email>";
		}

		if ( ! empty( $author ) )
			list( $return, $response ) = $this->_call( 'commit', '-m', $message, '--author', $author );
		else
			list( $return, $response ) = $this->_call( 'commit', '-m', $message );

		if ( $return !== 0 ) return false;

		list( $return, $response ) = $this->_call( 'rev-parse', 'HEAD' );

		return ( $return === 0 ) ? $response[0] : false;
	}

	function push( $branch = '' ) {
		if ( ! empty( $branch ) )
			list( $return, $response ) = $this->_call( 'push', '--porcelain', '-u', 'origin', $branch );
		else
			list( $return, $response ) = $this->_call( 'push', '--porcelain', '-u', 'origin' );
		return ( $return == 0 );
	}

	/*
	 * Get uncommited changes with status porcelain
	 * git status --porcelain
	 * It returns an array like this:
	
	 array(
		file => deleted|modified
		...
	)
	 */
	function get_local_changes() {
		list( $return, $response ) = $this->_call( 'status', '--porcelain'  );

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

	function get_uncommited_changes() {
		list( $branch_status, $changes ) = $this->status();
		return $changes;
	}

	function status( $local_only = false ) {
		list( $return, $response ) = $this->_call( 'status', '-z', '-b', '-u' );
		if ( 0 !== $return )
			return array( '', array() );

		$response     = $response[0];
		$new_response = array();

		if ( ! empty( $response ) ) {
			$response = explode( chr( 0 ), $response );
			$branch_status = array_shift( $response );
			foreach ( $response as $idx => $item ) :
				if ( ! empty( $from ) ) {
					unset( $from );
					continue;
				}
				unset($x, $y, $to, $from);
				if ( empty($item) ) continue; // ignore empty elements like the last item
				if ( '#' == $item[0] ) continue; // ignore branch status

				$x    = substr( $item, 0, 1 ); // X shows the status of the index
				$y    = substr( $item, 1, 1 ); // Y shows the status of the work tree
				$to   = substr( $item, 3 );
				$from = '';
				if ( 'R' == $x )
					$from = $response[ $idx + 1 ];

				$new_response[ $to ] = trim( "$x$y $from" );
			endforeach;
		}
		if ( $local_only ) return $new_response;

		if ( preg_match( '/## ([^.]+)\.+([^ ]+)/', $branch_status, $matches ) ) {
			$local_branch  = $matches[1];
			$remote_branch = $matches[2];

			list( $retrn, $response ) = $this->_call( 'rev-list', "$local_branch..$remote_branch", '--count' );
			$behind_count = (int)$response[0];

			list( $retrn, $response ) = $this->_call( 'rev-list', "$remote_branch..$local_branch", '--count' );
			$ahead_count = (int)$response[0];
		}

		if ( $behind_count ) {
			list( $retrn, $response ) = $this->_call( 'diff', '-z', '--name-status', "$local_branch~$ahead_count", $remote_branch );
			$response = explode( chr( 0 ), $response[0] );
			array_pop( $response );
			for ( $idx = 0 ; $idx < count( $response ) / 2 ; $idx++ ) {
				$file   = $response[ $idx * 2 + 1 ];
				$change = $response[ $idx * 2 ];
				if ( ! isset( $new_response[$file] ) )
					$new_response[$file] = "r$change";
			}
		}

		return array( $branch_status, $new_response );
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
	 * Pull changes from remote. By default accept local changes on conflicts
	 */
	function pull() {
		$this->_call( 'pull', '-s', 'recursive', '-X', 'ours' );
	}
}

if ( ! defined( 'GIT_DIR' ) )
	define( 'GIT_DIR', dirname( WP_CONTENT_DIR ) );

$git = new Git_Wrapper( GIT_DIR );
