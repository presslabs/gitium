<?php
/**
 * Gitium provides automatic git version control and deployment for
 * your plugins and themes integrated into wp-admin.
 *
 * Copyright (C) 2014-2025 PRESSINFRA SRL <ping@presslabs.com>
 *
 * Gitium is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Gitium is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Gitium. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package         Gitium
 */

if (!defined('GITIGNORE'))
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


if ( ! defined( 'GITIUM_INTERRUPTED_MERGE_ERROR' ) ) {
	define( 'GITIUM_INTERRUPTED_MERGE_ERROR', 'A previous merge was interrupted before it finished (the PHP process was stopped). Recover it from the Gitium notice before merging again.' );
}

class Git_Wrapper {

	private $last_error = '';
	private $gitignore  = GITIGNORE;

	private $repo_dir = '';
	private $private_key = '';

	// handle of the locked file that records the merge in progress
	private $merge_marker = null;

	function __construct( $repo_dir ) {
		$this->repo_dir = $repo_dir;
	}

	function _rrmdir( $dir ) {
		if ( empty( $dir ) || ! is_dir( $dir ) ) {
			return false;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach ( $files as $file ) {
			// do not resolve symlinks, otherwise the target of a link would be removed
			$filepath = "$dir/$file";
			( is_dir( $filepath ) && ! is_link( $filepath ) ) ? $this->_rrmdir( $filepath ) : unlink( $filepath );
		}
		return rmdir( $dir );
	}

	function _log(...$args) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) { return; }

		$output = '';
		if (isset($args) && $args) foreach ( $args as $arg ) {
			$output .= var_export($arg, true).'/n/n';
		}

		if ($output) error_log($output);
	}

	function _git_temp_key_file() {
		$key_file = tempnam( sys_get_temp_dir(), 'ssh-git' );
		return $key_file;
	}

	function set_key( $private_key ) {
		$this->private_key = $private_key;
	}

	private function get_env() {
		$env      = array(
			'HOME' => getenv( 'HOME' ),
		);
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

	protected function _call(...$args) {
		$args     = join( ' ', array_map( 'escapeshellarg', $args ) );
		$return   = -1;
		$response = array();
		$env      = $this->get_env();

		$git_bin_path = apply_filters( 'gitium_git_bin_path', '' );
		$cmd = "{$git_bin_path}git $args 2>&1";

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

	function set_last_error( $error ) {
		$this->last_error = $error;
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
		list( $return, $commits ) = $this->_call( 'rev-list', '@{u}..' );
		return ( 0 == $return ) ? $commits : array();
	}

	// git rev-list ..@{u}
	function get_behind_commits() {
		list( $return, $commits ) = $this->_call( 'rev-list', '..@{u}' );
		return ( 0 == $return ) ? $commits : array();
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
		if ( empty( $dir ) ) {
			return false;
		}
		$realpath   = realpath( $dir );
		$git_config  = realpath( $realpath . '/config' );
		$git_index   = realpath( $realpath . '/index' );
		// a repository without any commit or staged file has no index yet
		$git_head    = realpath( $realpath . '/HEAD' );
		$git_objects = realpath( $realpath . '/objects' );
		$has_index   = file_exists( $git_index ) || ( file_exists( $git_head ) && is_dir( $git_objects ) );
		if ( ! empty( $realpath ) && is_dir( $realpath ) && file_exists( $git_config ) && $has_index ) {
			return true;
		}
		return false;
	}

	function has_dot_git() {
		return file_exists( $this->repo_dir . '/.git' );
	}

	function cleanup() {
		$dot_git_dir = realpath( $this->repo_dir . '/.git' );
		if ( $this->is_dot_git_dir( $dot_git_dir ) && $this->_rrmdir( $dot_git_dir ) ) {
			if ( WP_DEBUG ) {
				error_log( "Gitium cleanup successfull. Removed '$dot_git_dir'." );
			}
			return true;
		}
		if ( WP_DEBUG ) {
			error_log( "Gitium cleanup failed. '$dot_git_dir' is not a .git dir." );
		}
		return false;
	}

	function add_remote_url( $url ) {
		list( $return, ) = $this->_call( 'remote', 'add', 'origin', $url );
		return ( 0 == $return );
	}

	function set_remote_url( $url ) {
		list( $return, ) = $this->_call( 'remote', 'set-url', 'origin', $url );
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

	function get_head_commit() {
		list( $return, $response ) = $this->_call( 'rev-parse', '--verify', '-q', 'HEAD' );
		if ( 0 == $return && ! empty( $response[0] ) ) {
			return $response[0];
		}
		return false;
	}

	function branch_exists( $branch ) {
		list( $return, ) = $this->_call( 'rev-parse', '--verify', '-q', "refs/heads/$branch" );
		return ( 0 == $return );
	}

	/*
	 * Files tracked in $ref that are missing from $target.
	 * When $target is empty the files are searched in the working tree.
	 * Returns false if the files could not be listed.
	 */
	function get_missing_files( $ref, $target = '' ) {
		return $this->_diff_names( '--diff-filter=D', $ref, $target );
	}

	/*
	 * Names of the files that differ between $ref and $target (or the working tree), filtered by $filter.
	 * Returns false if the files could not be listed.
	 */
	private function _diff_names( $filter, $ref, $target = '' ) {
		$args = array( 'diff', '-z', '--name-only', '--no-renames', $filter, $ref );
		if ( ! empty( $target ) ) {
			$args[] = $target;
		}
		list( $return, $response ) = $this->_call( ...$args );
		if ( 0 != $return ) {
			return false;
		}
		return array_values( array_filter( explode( chr( 0 ), join( "\n", $response ) ), 'strlen' ) );
	}

	/*
	 * A git command killed in the middle (e.g. `git add` or `git commit` stopped by a PHP-FPM timeout)
	 * leaves the index.lock file behind and every next commit fails. Returns the age in seconds of an
	 * index.lock older than $min_age, false if there is none.
	 */
	function get_stale_index_lock( $min_age = 600 ) {
		$lock = $this->repo_dir . '/.git/index.lock';
		clearstatcache( true, $lock );
		if ( ! file_exists( $lock ) ) {
			return false;
		}
		$age = time() - filemtime( $lock );
		return ( $age >= $min_age ) ? $age : false;
	}

	function remove_stale_index_lock( $min_age = 600 ) {
		if ( false === $this->get_stale_index_lock( $min_age ) ) {
			return false;
		}
		return unlink( $this->repo_dir . '/.git/index.lock' );
	}

	/*
	 * Returns the $paths whose content on disk is the same as in $ref. It compares the content
	 * directly, so it works for files that are not in the index too.
	 */
	private function _files_same_as( $ref, $paths ) {
		$repo_dir = $this->repo_dir;
		$same     = array();
		foreach ( array_chunk( array_values( $paths ), 100 ) as $chunk ) {
			list( $return, $response ) = $this->_call( '--literal-pathspecs', 'ls-tree', '-z', $ref, '--', ...$chunk );
			if ( 0 != $return ) {
				continue;
			}
			// <mode> SP <type> SP <object> TAB <file>
			$blobs = array();
			foreach ( array_filter( explode( chr( 0 ), join( "\n", $response ) ), 'strlen' ) as $entry ) {
				list( $info, $path ) = explode( "\t", $entry, 2 );
				$info = explode( ' ', $info );
				if ( 'blob' == $info[1] ) {
					$blobs[ $path ] = $info[2];
				}
			}
			$files = array_values( array_filter( $chunk, function( $path ) use ( $blobs, $repo_dir ) {
				return isset( $blobs[ $path ] ) && is_file( "$repo_dir/$path" ) && ! is_link( "$repo_dir/$path" );
			} ) );
			if ( empty( $files ) ) {
				continue;
			}
			list( $return, $hashes ) = $this->_call( 'hash-object', '--', ...$files );
			if ( 0 != $return || count( $hashes ) != count( $files ) ) {
				continue;
			}
			foreach ( $files as $idx => $path ) {
				if ( $hashes[ $idx ] == $blobs[ $path ] ) {
					$same[] = $path;
				}
			}
		}
		return $same;
	}

	private function _merge_marker_path() {
		return $this->repo_dir . '/.git/gitium-merge';
	}

	/*
	 * Records the merge that starts. The file stays locked while this process runs: if PHP is killed in
	 * the middle of the merge (e.g. by the PHP-FPM request_terminate_timeout) the system releases the
	 * lock but the file is left behind, so the next requests know that the merge was interrupted.
	 */
	private function _start_merge( $backup_branch, $branch_name, $temp_branch ) {
		if ( $this->merge_marker || ! is_dir( $this->repo_dir . '/.git' ) ) {
			return;
		}
		$handle = @fopen( $this->_merge_marker_path(), 'c+' );
		if ( ! $handle ) {
			return;
		}
		if ( ! flock( $handle, LOCK_EX | LOCK_NB ) ) {
			fclose( $handle );
			return;
		}
		ftruncate( $handle, 0 );
		fwrite( $handle, json_encode( array(
			'backup'  => $backup_branch,
			'branch'  => $branch_name,
			'temp'    => $temp_branch,
			'started' => time(),
		) ) );
		fflush( $handle );
		$this->merge_marker = $handle;
	}

	private function _end_merge( $keep_record = false ) {
		if ( ! $this->merge_marker ) {
			return;
		}
		flock( $this->merge_marker, LOCK_UN );
		fclose( $this->merge_marker );
		$this->merge_marker = null;
		if ( ! $keep_record ) {
			@unlink( $this->_merge_marker_path() );
		}
	}

	/*
	 * Returns the merge that was interrupted before it could finish or undo its changes, as an array with
	 * the keys: backup (the branch that holds the local files), branch (the name of the local branch),
	 * temp (the branch used for the merge) and started (timestamp, 0 if unknown). Returns false if
	 * there is no interrupted merge, or if the merge is still running.
	 */
	function get_interrupted_merge( $check_branches = true ) {
		$path = $this->_merge_marker_path();
		if ( file_exists( $path ) ) {
			$handle = @fopen( $path, 'r' );
			if ( ! $handle ) {
				return false;
			}
			if ( ! flock( $handle, LOCK_SH | LOCK_NB ) ) {
				fclose( $handle ); // the merge is still running
				return false;
			}
			$merge = json_decode( stream_get_contents( $handle ), true );
			flock( $handle, LOCK_UN );
			fclose( $handle );
			if ( is_array( $merge ) && ! empty( $merge['backup'] ) && ! empty( $merge['branch'] ) && $this->branch_exists( $merge['backup'] ) ) {
				return $merge;
			}
			// the merge was interrupted before it changed anything or after it finished
			@unlink( $path );
			return false;
		}

		// a merge interrupted by a Gitium version that did not record it
		if ( ! $check_branches || ! is_dir( $this->repo_dir . '/.git' ) ) {
			return false;
		}
		list( $return, $branches ) = $this->_call( 'for-each-ref', '--format=%(refname:short)', 'refs/heads/merge_local', 'refs/heads/initial' );
		if ( 0 != $return || empty( $branches ) ) {
			return false;
		}
		$current = $this->get_local_branch();
		if ( in_array( 'merge_local', $branches ) ) {
			$backup = 'merge_local';
		} elseif ( ! $this->get_remote_tracking_branch() ) {
			$backup = 'initial'; // only used while the repository is set up
		} else {
			return false;
		}
		$branch = $current;
		if ( ! $current || 'HEAD' == $current || $backup == $current ) {
			list( $return, $upstream ) = $this->_call( 'rev-parse', '--abbrev-ref', "$backup@{u}" );
			$branch = ( 0 == $return && ! empty( $upstream[0] ) ) ? preg_replace( '#^[^/]+/#', '', $upstream[0] ) : 'master';
		}
		return array( 'backup' => $backup, 'branch' => $branch, 'temp' => $current, 'started' => 0 );
	}

	/*
	 * Recovers the files of an interrupted merge: the local branch is set back to the backup branch and
	 * the files that the merge removed or replaced are put back. Only the files that the merge brought
	 * from the remote branch (unchanged since then) are removed, the other files are left on disk and
	 * the branch used for the merge is kept under a new name.
	 * Returns the name of the branch that keeps the commits of the merge ('' if none), false on failure.
	 */
	function recover_interrupted_merge() {
		$merge = $this->get_interrupted_merge();
		if ( ! $merge ) {
			$this->last_error = 'There is no interrupted merge to recover.';
			return false;
		}
		$backup = $merge['backup'];
		$branch = $merge['branch'];
		$temp   = $merge['temp'];

		// if this recovery is interrupted too, it can be started again
		$this->_start_merge( $backup, $branch, $temp );
		$recovered = false;
		try {
			// leave the cherry-pick without touching the files
			if ( $this->_cherry_pick_in_progress() ) {
				list( $return, ) = $this->_call( 'cherry-pick', '--quit' );
				if ( 0 != $return ) {
					$this->_call( 'cherry-pick', '--abort' );
				}
			}

			$current = $this->get_local_branch();
			$temp_exists = ( $temp && 'HEAD' != $temp && $temp != $backup && $this->branch_exists( $temp ) );

			// files changed by the merge and not modified since then get back their local version
			$replaced = array();
			// files brought by the merge from the remote branch and not modified since then are removed,
			// otherwise they block the next merge (they are still in the remote branch)
			$brought  = array();
			if ( $temp_exists ) {
				$changed = $this->_diff_names( '--diff-filter=MT', $backup, $temp );
				$added   = $this->_diff_names( '--diff-filter=A', $backup, $temp );
				$touched = $this->_diff_names( '--diff-filter=MTD', $temp );
				if ( false !== $changed && false !== $touched ) {
					$replaced = array_diff( $changed, $touched );
				}
				list( $return, $upstream ) = $this->_call( 'rev-parse', '--abbrev-ref', "$temp@{u}" );
				if ( false !== $added && 0 == $return && ! empty( $upstream[0] ) ) {
					$not_on_remote = $this->_diff_names( '--diff-filter=ADMT', $temp, $upstream[0] );
					if ( false !== $not_on_remote ) {
						$brought = $this->_files_same_as( $temp, array_diff( $added, $not_on_remote ) );
					}
				}
			}

			if ( $current != $backup ) {
				// switch to the backup branch without touching the files on disk
				list( $return, ) = $this->_call( 'symbolic-ref', 'HEAD', "refs/heads/$backup" );
				if ( 0 != $return ) {
					return false;
				}
				list( $return, ) = $this->_call( 'reset', '-q' );
				if ( 0 != $return ) {
					return false;
				}
			}

			// put back the files removed by the merge
			$missing = $this->get_missing_files( 'HEAD' );
			if ( false === $missing ) {
				return false;
			}
			foreach ( array_chunk( array_values( array_unique( array_merge( $missing, $replaced ) ) ), 100 ) as $paths ) {
				list( $return, ) = $this->_call( '--literal-pathspecs', 'checkout', 'HEAD', '--', ...$paths );
				if ( 0 != $return ) {
					return false;
				}
			}

			foreach ( $brought as $path ) {
				$file = $this->repo_dir . '/' . $path;
				if ( is_file( $file ) || is_link( $file ) ) {
					unlink( $file );
				}
				// remove the directories left empty
				for ( $dir = dirname( $file ); strlen( $dir ) > strlen( $this->repo_dir ) && @rmdir( $dir ); $dir = dirname( $dir ) );
			}

			// keep the commits of the merge branch
			$saved = '';
			if ( $temp_exists ) {
				$saved = 'gitium-interrupted-' . gmdate( 'Ymd-His' );
				list( $return, ) = $this->_call( 'branch', '-m', $temp, $saved );
				if ( 0 != $return ) {
					return false;
				}
			}
			if ( $branch != $backup ) {
				list( $return, ) = $this->_call( 'branch', '-m', $branch );
				if ( 0 != $return ) {
					return false;
				}
			}
			$recovered = true;
		} finally {
			// on failure keep the record, the merge still needs to be recovered
			$this->_end_merge( ! $recovered );
		}
		$this->last_error = null;
		return $saved;
	}

	private function _cherry_pick_in_progress() {
		list( $return, ) = $this->_call( 'rev-parse', '--verify', '-q', 'CHERRY_PICK_HEAD' );
		return ( 0 == $return );
	}

	private function _is_merge_commit( $commit ) {
		list( $return, ) = $this->_call( 'rev-parse', '--verify', '-q', "$commit^2" );
		return ( 0 == $return );
	}

	/*
	 * Puts back the branch that was saved as $backup_branch before a merge,
	 * together with its files, and removes the $temp_branch used for the merge.
	 */
	private function _restore_branch( $backup_branch, $branch_name, $temp_branch ) {
		$error = $this->last_error;

		if ( $this->_cherry_pick_in_progress() ) {
			$this->_call( 'cherry-pick', '--abort' );
		}
		list( $return, ) = $this->_call( 'checkout', $backup_branch );
		if ( 0 != $return ) {
			list( $return, ) = $this->_call( 'checkout', '-f', $backup_branch );
		}
		if ( 0 != $return ) {
			$this->last_error = "$error\nCould not restore the '$backup_branch' branch: {$this->last_error}";
			return false;
		}
		if ( $temp_branch != $backup_branch ) {
			$this->_call( 'branch', '-D', $temp_branch );
		}
		if ( $branch_name != $backup_branch ) {
			$this->_call( 'branch', '-m', $branch_name );
		}
		$this->last_error = $error;
		return true;
	}

	/*
	 * Returns the files of $backup_branch that the merge would remove (from HEAD or from the working
	 * tree), except the ones listed in $allowed_deletions. $missing_before are the files that were
	 * already missing from the working tree before the merge started.
	 */
	private function _get_lost_files( $backup_branch, $missing_before, $allowed_deletions = array() ) {
		$missing_from_head = $this->get_missing_files( $backup_branch, 'HEAD' );
		$missing_from_disk = $this->get_missing_files( 'HEAD' );
		if ( false === $missing_from_head || false === $missing_from_disk ) {
			return array( '(could not list the files)' );
		}
		$lost = array_diff( $missing_from_head, $allowed_deletions );
		$lost = array_merge( $lost, array_diff( $missing_from_disk, $missing_before ) );
		return array_values( array_unique( $lost ) );
	}

	// A new merge must not start while another one runs, or before an interrupted one is recovered
	private function _can_start_merge( $backup_branch ) {
		if ( $this->get_interrupted_merge() ) {
			$this->last_error = GITIUM_INTERRUPTED_MERGE_ERROR;
			return false;
		}
		if ( $this->branch_exists( $backup_branch ) ) {
			$this->last_error = "Another merge is in progress (the branch '$backup_branch' exists), please try again later.";
			return false;
		}
		return true;
	}

	private function _lost_files_error( $lost ) {
		$list = join( ', ', array_slice( $lost, 0, 10 ) );
		if ( count( $lost ) > 10 ) {
			$list .= sprintf( ' and %d more', count( $lost ) - 10 );
		}
		return "The merge was cancelled because it would remove local files: $list";
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
			// the changes of a merge commit are picked with the commits it merged
			if ( $this->_is_merge_commit( $commit ) ) { continue; }

			list( $return, $response ) = $this->_call( 'cherry-pick', $commit );

			// abort the cherry-pick if the changes are already pushed
			if ( false !== $this->strpos_haystack_array( $response, 'previous cherry-pick is now empty' ) ) {
				$this->_call( 'cherry-pick', '--abort' );
				continue;
			}

			if ( $return != 0 ) {
				$error = $this->last_error;
				if ( ! $this->_cherry_pick_in_progress() ) {
					// cherry-pick failed without starting (not a conflict), nothing was picked
					$this->last_error = $error;
					return false;
				}
				$this->_resolve_merge_conflicts( $this->get_commit_message( $commit ) );
				if ( $this->_cherry_pick_in_progress() ) {
					list( $staged, ) = $this->_call( 'diff', '--cached', '--quiet', 'HEAD' );
					if ( 0 != $staged ) {
						$this->last_error = $error;
						return false;
					}
					// the conflicts were resolved with no changes left to commit
					$this->_call( 'cherry-pick', '--abort' );
				}
			}
		}
		return true;
	}

	function merge_with_accept_mine(...$commits) {
		do_action( 'gitium_before_merge_with_accept_mine' );

		if ( 1 == count($commits) && is_array( $commits[0] ) ) {
			$commits = $commits[0];
		}

		// get the remote branch
		$remote_branch = $this->get_remote_tracking_branch();

		// get the local branch
		$local_branch  = $this->get_local_branch();

		if ( ! $remote_branch || ! $local_branch || 'HEAD' == $local_branch ) {
			return false;
		}

		if ( ! $this->_can_start_merge( 'merge_local' ) ) {
			return false;
		}

		// get ahead commits
		$ahead_commits = $this->get_ahead_commits();

		// combine all commits with the ahead commits
		$commits = array_unique( array_merge( array_reverse( $commits ), $ahead_commits ) );
		$commits = array_reverse( $commits );
		$commits = array_values( array_filter( $commits, function( $commit ) {
			return is_string( $commit ) && preg_match( '/^[0-9a-f]{7,64}$/i', $commit );
		} ) );

		// files deleted on the remote branch are allowed to be removed by the merge
		$remote_deletions = array();
		list( $return, $merge_base ) = $this->_call( 'merge-base', 'HEAD', $remote_branch );
		if ( 0 == $return && ! empty( $merge_base[0] ) ) {
			$remote_deletions = $this->get_missing_files( $merge_base[0], $remote_branch );
		}
		$missing_before = $this->get_missing_files( 'HEAD' );
		if ( false === $remote_deletions || false === $missing_before ) {
			return false;
		}

		$this->_start_merge( 'merge_local', $local_branch, $local_branch );
		try {
			return $this->_merge_with_accept_mine( $commits, $local_branch, $remote_branch, $remote_deletions, $missing_before );
		} finally {
			$this->_end_merge();
		}
	}

	private function _merge_with_accept_mine( $commits, $local_branch, $remote_branch, $remote_deletions, $missing_before ) {
		// rename the local branch to 'merge_local'
		list( $return, ) = $this->_call( 'branch', '-m', 'merge_local' );
		if ( $return != 0 ) {
			return false;
		}

		// local branch set up to track remote branch
		list( $return, ) = $this->_call( 'branch', $local_branch, $remote_branch );
		if ( $return != 0 ) {
			$this->_call( 'branch', '-m', $local_branch );
			return false;
		}

		// checkout to the $local_branch
		list( $return, ) = $this->_call( 'checkout', $local_branch );
		if ( $return != 0 ) {
			$this->_restore_branch( 'merge_local', $local_branch, $local_branch );
			return false;
		}

		// don't cherry pick if there are no commits
		$picked = ( count( $commits ) > 0 ) ? $this->cherry_pick( $commits ) : true;

		// git status without states: AA, DD, UA, AU ...
		if ( ! $picked || $this->_cherry_pick_in_progress() || ! $this->successfully_merged() ) {
			$this->_restore_branch( 'merge_local', $local_branch, $local_branch );
			return false;
		}

		// never finish a merge that removes files the remote did not delete
		$lost = $this->_get_lost_files( 'merge_local', $missing_before, $remote_deletions );
		if ( ! empty( $lost ) ) {
			$this->last_error = $this->_lost_files_error( $lost );
			$this->_restore_branch( 'merge_local', $local_branch, $local_branch );
			return false;
		}

		// delete the 'merge_local' branch
		$this->_call( 'branch', '-D', 'merge_local' );
		return true;
	}

	function successfully_merged() {
		list( , $response ) = $this->status( true );
		$changes = array_values( $response );
		return ( 0 == count( array_intersect( $changes, array( 'DD', 'AU', 'UD', 'UA', 'DU', 'AA', 'UU' ) ) ) );
	}

	function merge_initial_commit( $commit, $branch ) {
		$local_branch = $this->get_local_branch();
		if ( ! $local_branch || 'HEAD' == $local_branch ) {
			return false;
		}

		// cherry-pick replays only the changes of a commit relative to its parent. When the local
		// history has more commits (e.g. the site was disconnected and connected again) the files
		// added by the older commits would be removed by the checkout and never put back, so we
		// cherry-pick a parentless commit holding the complete snapshot of $commit instead.
		list( $return, $author ) = $this->_call( 'log', '-n', '1', '--format=%an%n%ae', $commit );
		if ( 0 != $return || count( $author ) < 2 ) {
			return false;
		}
		list( $return, $snapshot ) = $this->_call(
			'-c', "user.name={$author[0]}", '-c', "user.email={$author[1]}",
			'commit-tree', "$commit^{tree}", '-m', $this->get_commit_message( $commit )
		);
		if ( 0 != $return || empty( $snapshot[0] ) ) {
			return false;
		}
		$snapshot = $snapshot[0];

		$missing_before = $this->get_missing_files( 'HEAD' );
		if ( false === $missing_before || ! $this->_can_start_merge( 'initial' ) ) {
			return false;
		}

		$this->_start_merge( 'initial', $local_branch, $branch );
		try {
			return $this->_merge_initial_commit( $commit, $branch, $local_branch, $snapshot, $missing_before );
		} finally {
			$this->_end_merge();
		}
	}

	private function _merge_initial_commit( $commit, $branch, $local_branch, $snapshot, $missing_before ) {
		list( $return, ) = $this->_call( 'branch', '-m', 'initial' );
		if ( 0 != $return ) {
			return false;
		}
		list( $return, ) = $this->_call( 'checkout', $branch );
		if ( 0 != $return ) {
			$this->_restore_branch( 'initial', $local_branch, $branch );
			return false;
		}
		list( $return, ) = $this->_call(
			'cherry-pick', '--strategy', 'recursive', '--strategy-option', 'theirs', $snapshot
		);
		if ( $return != 0 ) {
			$error = $this->last_error;
			$this->_resolve_merge_conflicts( $this->get_commit_message( $commit ) );
			if ( $this->_cherry_pick_in_progress() || ! $this->successfully_merged() ) {
				$this->last_error = $error;
				$this->_restore_branch( 'initial', $local_branch, $branch );
				return false;
			}
		}

		// every file of the local site must still be there after the merge
		$lost = $this->_get_lost_files( 'initial', $missing_before );
		if ( ! empty( $lost ) ) {
			$this->last_error = $this->_lost_files_error( $lost );
			$this->_restore_branch( 'initial', $local_branch, $branch );
			return false;
		}

		$this->_call( 'branch', '-D', 'initial' );
		return true;
	}

	function get_remote_branches() {
		list( $return, $response ) = $this->_call( 'branch', '-r' );
		if ( 0 != $return ) {
			return array();
		}
		// skip symbolic refs like 'origin/HEAD -> origin/master'
		$response = array_filter( array_map( 'trim', $response ), function( $b ) { return false === strpos( $b, '->' ); } );
		$response = array_map( function( $b ) { return preg_replace( '#^origin/#', '', $b ); }, $response );
		return array_values( $response );
	}

	function add(...$args) {
		if ( 1 == count($args) && is_array( $args[0] ) ) {
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
			list( $return, ) = $this->_call( 'push', '--porcelain', '-u', 'origin', 'HEAD' );
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

	function revert_commit( $commit_hash ) {
		list( $return, ) = $this->_call( 'revert', '--no-edit', $commit_hash );
		return ( $return === 0 );
	}

	function remove_wp_content_from_version_control() {
		$dot_git = WP_CONTENT_DIR . '/.git';
		if ( is_link( $dot_git ) || is_file( $dot_git ) ) {
			return unlink( $dot_git );
		}
		return $this->_rrmdir( $dot_git );
	}
}

if ( ! defined( 'GIT_DIR' ) ) {
	define( 'GIT_DIR', dirname( WP_CONTENT_DIR ) );
}

# global is needed here for wp-cli as it includes/exec files inside a function scope
# this forces the context to really be global :\.
global $git;
$git = new Git_Wrapper( GIT_DIR );
