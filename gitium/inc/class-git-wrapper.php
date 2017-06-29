<?php
/*  Copyright 2014-2016 Presslabs SRL <ping@presslabs.com>

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define('GITIGNORE', <<<EOF
*.log
*.swp
*.back
*.bak
*.sql
*.sql.gz
~*

.htaccess
.maintenance

wp-config.php
sitemap.xml
sitemap.xml.gz
wp-content/uploads/
wp-content/blogs.dir/
wp-content/upgrade/
wp-content/backup-db/
wp-content/cache/
wp-content/backups/

wp-content/advanced-cache.php
wp-content/object-cache.php
wp-content/wp-cache-config.php
wp-content/db.php

wp-admin/
wp-includes/
/index.php
/license.txt
/readme.html

# de_DE
/liesmich.html

# it_IT
/LEGGIMI.txt
/licenza.html

# da_DK
/licens.html

# es_ES, es_PE
/licencia.txt

# hu_HU
/licenc.txt
/olvasdel.html

# sk_SK
/licencia-sk_SK.txt

# sv_SE
/licens-sv_SE.txt

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
EOF
);


class Git_Wrapper {

	private $last_error = '';
	private $gitignore  = GITIGNORE;

	function __construct( $repo_dir ) {
		$this->repo_dir    = $repo_dir;
		$this->private_key = '';
	}

	function _rrmdir( $dir ) {
		if ( empty( $dir ) || ! is_dir( $dir ) ) {
			return false;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach ( $files as $file ) {
			$filepath = realpath("$dir/$file");
			( is_dir( $filepath ) ) ? $this->_rrmdir( $filepath ) : unlink( $filepath );
		}
		return rmdir( $dir );
	}

	function _log() {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) { return; }

		$output = '';
		if ( func_num_args() == 1 && is_string( func_get_arg( 0 ) ) ) {
			$output .= var_export(func_get_arg( 0 ), true);
		} else {
			$args = func_get_args();
			foreach ( $args as $arg ) {
				$output .= var_export($arg, true).'/n/n';
			}
		}

		error_log($output);
	}

	function _git_temp_key_file() {
		$key_file = tempnam( sys_get_temp_dir(), 'ssh-git' );
		return $key_file;
	}

	function set_key( $private_key ) {
		$this->private_key = $private_key;
	}

	private function get_env() {
		$env      = array();
		$key_file = null;

		if ( defined( 'GIT_SSH' ) && GIT_SSH ) {
			$env['GIT_SSH'] = GIT_SSH;
		} else {
			$env['GIT_SSH'] = dirname( __FILE__ ) . '/ssh-git';
		}

		if ( defined( 'GIT_KEY_FILE' ) && GIT_KEY_FILE ) {
			$env['GIT_KEY_FILE'] = GIT_KEY_FILE;
		} elseif ( $this->private_key ) {
			$key_file = $this->_git_temp_key_file();
			chmod( $key_file, 0600 );
			file_put_contents( $key_file, $this->private_key );
			$env['GIT_KEY_FILE'] = $key_file;
		}

		return $env;
	}

	protected function _call() {
		$args     = func_get_args();
		$args     = join( ' ', array_map( 'escapeshellarg', $args ) );
		$cmd      = "git $args 2>&1";
		$return   = -1;
		$response = array();
		$env      = $this->get_env();

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
		if ( is_resource( $proc ) ) {
			fclose( $pipes[0] );
			while ( $line = fgets( $pipes[1] ) ) {
				$response[] = rtrim( $line, "\n\r" );
			}
			$return = (int)proc_close( $proc );
		}
		$this->_log( "$return $cmd", join( "\n", $response ) );
		if ( ! defined( 'GIT_KEY_FILE' ) && isset( $env['GIT_KEY_FILE'] ) ) {
			unlink( $env['GIT_KEY_FILE'] );
		}
		if ( 0 != $return ) {
			$this->last_error = join( "\n", $response );
		} else {
			$this->last_error = null;
		}
		return array( $return, $response );
	}

	function get_last_error() {
		return $this->last_error;
	}

	function can_exec_git() {
		list( $return, ) = $this->_call( 'version' );
		return ( 0 == $return );
	}

	function is_status_working() {
		list( $return, ) = $this->_call( 'status', '-s' );
		return ( 0 == $return );
	}

	function get_version() {
		list( $return, $version ) = $this->_call( 'version' );
		if ( 0 != $return ) { return ''; }
		if ( ! empty( $version[0] ) ) {
			return substr( $version[0], 12 );
		}
		return '';
	}

	// git rev-list @{u}..
	function get_ahead_commits() {
		list( , $commits ) = $this->_call( 'rev-list', '@{u}..' );
		return $commits;
	}

	// git rev-list ..@{u}
	function get_behind_commits() {
		list( , $commits  ) = $this->_call( 'rev-list', '..@{u}' );
		return $commits;
	}

	function init() {
		file_put_contents( "$this->repo_dir/.gitignore", $this->gitignore );
		list( $return, ) = $this->_call( 'init' );
		$this->_call( 'config', 'user.email', 'gitium@presslabs.com' );
		$this->_call( 'config', 'user.name', 'Gitium' );
		$this->_call( 'config', 'push.default', 'matching' );
		return ( 0 == $return );
	}

	function is_dot_git_dir( $dir ) {
		$realpath   = realpath( $dir );
		$git_config = realpath( $realpath . '/config' );
		$git_index  = realpath( $realpath . '/index' );
		if ( ! empty( $realpath ) && is_dir( $realpath ) && file_exists( $git_config ) && file_exists( $git_index ) ) {
			return True;
		}
		return False;
	}

	function cleanup() {
		$dot_git_dir = realpath( $this->repo_dir . '/.git' );
		if ( $this->is_dot_git_dir( $dot_git_dir ) && $this->_rrmdir( $dot_git_dir ) ) {
			if ( WP_DEBUG ) {
				error_log( "Gitium cleanup successfull. Removed '$dot_git_dir'." );
			}
			return True;
		}
		if ( WP_DEBUG ) {
			error_log( "Gitium cleanup failed. '$dot_git_dir' is not a .git dir." );
		}
		return False;
	}

	function add_remote_url( $url ) {
		list( $return, ) = $this->_call( 'remote', 'add', 'origin', $url );
		return ( 0 == $return );
	}

	function get_remote_url() {
		list( , $response ) = $this->_call( 'config', '--get', 'remote.origin.url' );
		if ( isset( $response[0] ) ) {
			return $response[0];
		}
		return '';
	}

	function remove_remote() {
		list( $return, ) = $this->_call( 'remote', 'rm', 'origin');
		return ( 0 == $return );
	}

	function get_remote_tracking_branch() {
		list( $return, $response ) = $this->_call( 'rev-parse', '--abbrev-ref', '--symbolic-full-name', '@{u}' );
		if ( 0 == $return ) {
			return $response[0];
		}
		return false;
	}

	function get_local_branch() {
		list( $return, $response ) = $this->_call( 'rev-parse', '--abbrev-ref', 'HEAD' );
		if ( 0 == $return ) {
			return $response[0];
		}
		return false;
	}

	function fetch_ref() {
		list( $return, ) = $this->_call( 'fetch', 'origin' );
		return ( 0 == $return );
	}

	protected function _resolve_merge_conflicts( $message ) {
		list( , $changes ) = $this->status( true );
		$this->_log( $changes );
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

	private function strpos_haystack_array( $haystack, $needle, $offset=0 ) {
		if ( ! is_array( $haystack ) ) { $haystack = array( $haystack ); }

		foreach ( $haystack as $query ) {
			if ( strpos( $query, $needle, $offset) !== false ) { return true; }
		}
		return false;
	}

	private function cherry_pick( $commits ) {
		foreach ( $commits as $commit ) {
			if ( empty( $commit ) ) { return false; }

			list( $return, $response ) = $this->_call( 'cherry-pick', $commit );

			// abort the cherry-pick if the changes are already pushed
			if ( false !== $this->strpos_haystack_array( $response, 'previous cherry-pick is now empty' ) ) {
				$this->_call( 'cherry-pick', '--abort' );
				continue;
			}

			if ( $return != 0 ) {
				$this->_resolve_merge_conflicts( $this->get_commit_message( $commit ) );
			}
		}
	}

	function merge_with_accept_mine() {
		do_action( 'gitium_before_merge_with_accept_mine' );

		// get all commits given by arguments
		$commits = func_get_args();
		if ( 1 == func_num_args() && is_array( $commits[0] ) ) {
			$commits = $commits[0];
		}

		// get ahead commits
		$ahead_commits = $this->get_ahead_commits();

		// combine all commits with the ahead commits
		$commits = array_unique( array_merge( array_reverse( $commits ), $ahead_commits ) );
		$commits = array_reverse( $commits );

		// get the remote branch
		$remote_branch = $this->get_remote_tracking_branch();

		// get the local branch
		$local_branch  = $this->get_local_branch();

		// rename the local branch to 'merge_local'
		$this->_call( 'branch', '-m', 'merge_local' );

		// local branch set up to track remote branch
		$this->_call( 'branch', $local_branch, $remote_branch );

		// checkout to the $local_branch
		list( $return, ) = $this->_call( 'checkout', $local_branch );
		if ( $return != 0 ) {
			$this->_call( 'branch', '-M', $local_branch );
			return false;
		}

		// don't cherry pick if there are no commits
		if ( count( $commits ) > 0 ) {
			$this->cherry_pick( $commits );
		}

		if ( $this->successfully_merged() ) { // git status without states: AA, DD, UA, AU ...
			// delete the 'merge_local' branch
			$this->_call( 'branch', '-D', 'merge_local' );
			return true;
		} else {
			$this->_call( 'cherry-pick', '--abort' );
			$this->_call( 'checkout', '-b', 'merge_local' );
			$this->_call( 'branch', '-M', $local_branch );
			return false;
		}
	}

	function successfully_merged() {
		list( , $response ) = $this->status( true );
		$changes = array_values( $response );
		return ( 0 == count( array_intersect( $changes, array( 'DD', 'AU', 'UD', 'UA', 'DU', 'AA', 'UU' ) ) ) );
	}

	function merge_initial_commit( $commit, $branch ) {
		list( $return, ) = $this->_call( 'branch', '-m', 'initial' );
		if ( 0 != $return ) {
			return false;
		}
		list( $return, ) = $this->_call( 'checkout', $branch );
		if ( 0 != $return ) {
			return false;
		}
		list( $return, ) = $this->_call(
			'cherry-pick', '--strategy', 'recursive', '--strategy-option', 'theirs', $commit
		);
		if ( $return != 0 ) {
			$this->_resolve_merge_conflicts( $this->get_commit_message( $commit ) );
			if ( ! $this->successfully_merged() ) {
				$this->_call( 'cherry-pick', '--abort' );
				$this->_call( 'checkout', 'initial' );
				return false;
			}
		}
		$this->_call( 'branch', '-D', 'initial' );
		return true;
	}

	function get_remote_branches() {
		list( , $response ) = $this->_call( 'branch', '-r' );
		$response = array_map( 'trim', $response );
		$response = array_map( create_function( '$b', 'return str_replace("origin/","",$b);' ), $response );
		return $response;
	}

	function add() {
		$args = func_get_args();
		if ( 1 == func_num_args() && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$params = array_merge( array( 'add', '-n', '--all' ), $args );
		list ( , $response ) = call_user_func_array( array( $this, '_call' ), $params );
		$count = count( $response );

		$params = array_merge( array( 'add', '--all' ), $args );
		list ( , $response ) = call_user_func_array( array( $this, '_call' ), $params );

		return $count;
	}

	function commit( $message, $author_name = '', $author_email = '' ) {
		$author = '';
		if ( $author_email ) {
			if ( empty( $author_name ) ) {
				$author_name = $author_email;
			}
			$author = "$author_name <$author_email>";
		}

		if ( ! empty( $author ) ) {
			list( $return, $response ) = $this->_call( 'commit', '-m', $message, '--author', $author );
		} else {
			list( $return, $response ) = $this->_call( 'commit', '-m', $message );
		}
		if ( $return !== 0 ) { return false; }

		list( $return, $response ) = $this->_call( 'rev-parse', 'HEAD' );

		return ( $return === 0 ) ? $response[0] : false;
	}

	function push( $branch = '' ) {
		if ( ! empty( $branch ) ) {
			list( $return, ) = $this->_call( 'push', '--porcelain', '-u', 'origin', $branch );
		} else {
			list( $return, ) = $this->_call( 'push', '--porcelain', '-u', 'origin' );
		}
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

		if ( 0 !== $return ) {
			return array();
		}
		$new_response = array();
		if ( ! empty( $response ) ) {
			foreach ( $response as $line ) :
				$work_tree_status = substr( $line, 1, 1 );
				$path = substr( $line, 3 );

				if ( ( '"' == $path[0] ) && ('"' == $path[strlen( $path ) - 1] ) ) {
					// git status --porcelain will put quotes around paths with whitespaces
					// we don't want the quotes, let's get rid of them
					$path = substr( $path, 1, strlen( $path ) - 2 );
				}

				if ( 'D' == $work_tree_status ) {
					$action = 'deleted';
				} else {
					$action = 'modified';
				}
				$new_response[ $path ] = $action;
			endforeach;
		}
		return $new_response;
	}

	function get_uncommited_changes() {
		list( , $changes ) = $this->status();
		return $changes;
	}

	function local_status() {
		list( $return, $response ) = $this->_call( 'status', '-s', '-b', '-u' );
		if ( 0 !== $return ) {
			return array( '', array() );
		}

		$new_response = array();
		if ( ! empty( $response ) ) {
			$branch_status = array_shift( $response );
			foreach ( $response as $idx => $line ) :
				unset( $index_status, $work_tree_status, $path, $new_path, $old_path );

				if ( empty( $line ) ) { continue; } // ignore empty lines like the last item
				if ( '#' == $line[0] ) { continue; } // ignore branch status

				$index_status     = substr( $line, 0, 1 );
				$work_tree_status = substr( $line, 1, 1 );
				$path             = substr( $line, 3 );

				$old_path = '';
				$new_path = explode( '->', $path );
				if ( ( 'R' === $index_status ) && ( ! empty( $new_path[1] ) ) ) {
					$old_path = trim( $new_path[0] );
					$path     = trim( $new_path[1] );
				}
				$new_response[ $path ] = trim( $index_status . $work_tree_status . ' ' . $old_path );
			endforeach;
		}

		return array( $branch_status, $new_response );
	}

	function status( $local_only = false ) {
		list( $branch_status, $new_response ) = $this->local_status();

		if ( $local_only ) { return array( $branch_status, $new_response ); }

		$behind_count = 0;
		$ahead_count  = 0;
		if ( preg_match( '/## ([^.]+)\.+([^ ]+)/', $branch_status, $matches ) ) {
			$local_branch  = $matches[1];
			$remote_branch = $matches[2];

			list( , $response ) = $this->_call( 'rev-list', "$local_branch..$remote_branch", '--count' );
			$behind_count = (int)$response[0];

			list( , $response ) = $this->_call( 'rev-list', "$remote_branch..$local_branch", '--count' );
			$ahead_count = (int)$response[0];
		}

		if ( $behind_count ) {
			list( , $response ) = $this->_call( 'diff', '-z', '--name-status', "$local_branch~$ahead_count", $remote_branch );
			$response = explode( chr( 0 ), $response[0] );
			array_pop( $response );
			for ( $idx = 0 ; $idx < count( $response ) / 2 ; $idx++ ) {
				$file   = $response[ $idx * 2 + 1 ];
				$change = $response[ $idx * 2 ];
				if ( ! isset( $new_response[ $file ] ) ) {
					$new_response[ $file ] = "r$change";
				}
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

	/**
	 * Return the last n commits
	 */
	function get_last_commits( $n = 20 ) {
		list( $return, $message )  = $this->_call( 'log', '-n', $n, '--pretty=format:%s' );
		if ( 0 !== $return ) { return false; }

		list( $return, $response ) = $this->_call( 'log', '-n', $n, '--pretty=format:%h|%an|%ae|%ad|%cn|%ce|%cd' );
		if ( 0 !== $return ) { return false; }

		foreach ( $response as $index => $value ) {
			$commit_info = explode( '|', $value );
			$commits[ $commit_info[0] ] = array(
				'subject'         => $message[ $index ],
				'author_name'     => $commit_info[1],
				'author_email'    => $commit_info[2],
				'author_date'     => $commit_info[3],
			);
			if ( $commit_info[1] != $commit_info[4] && $commit_info[2] != $commit_info[5] ) {
				$commits[ $commit_info[0] ]['committer_name']  = $commit_info[4];
				$commits[ $commit_info[0] ]['committer_email'] = $commit_info[5];
				$commits[ $commit_info[0] ]['committer_date']  = $commit_info[6];
			}
		}
		return $commits;
	}

	public function set_gitignore( $content ) {
		file_put_contents( $this->repo_dir . '/.gitignore', $content );
		return true;
	}

	public function get_gitignore() {
		return file_get_contents( $this->repo_dir . '/.gitignore' );
	}

	/**
	 * Remove files in .gitignore from version control
	 */
	function rm_cached( $path ) {
		list( $return, ) = $this->_call( 'rm', '--cached', $path );
		return ( $return == 0 );
	}

	function remove_wp_content_from_version_control() {
		$process = proc_open(
			'rm -rf ' . ABSPATH . '/wp-content/.git',
			array(
				0 => array( 'pipe', 'r' ),  // stdin
				1 => array( 'pipe', 'w' ),  // stdout
			),
			$pipes
		);
		if ( is_resource( $process ) ) {
			fclose( $pipes[0] );
			proc_close( $process );
			return true;
		}
		return false;
	}
}

if ( ! defined( 'GIT_DIR' ) ) {
	define( 'GIT_DIR', dirname( WP_CONTENT_DIR ) );
}

# global is needed here for wp-cli as it includes/exec files inside a function scope
# this forces the context to really be global :\.
global $git;
$git = new Git_Wrapper( GIT_DIR );
