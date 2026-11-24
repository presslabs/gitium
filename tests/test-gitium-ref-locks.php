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

require_once 'gitium-unittestcase.php';

class Test_Gitium_Ref_Locks extends Gitium_UnitTestCase {

	private $git_dir = '';

	public function setup() {
		parent::setup();
		$this->git_dir = dirname( WP_CONTENT_DIR ) . '/.git';
	}

	// a lock like the one a killed git process leaves behind, $age seconds old
	private function _put_lock( $path, $age ) {
		$lock = "$this->git_dir/$path";
		if ( ! is_dir( dirname( $lock ) ) ) {
			mkdir( dirname( $lock ), 0777, true );
		}
		touch( $lock, time() - $age );
		return $lock;
	}

	private function _rev_parse( $repo, $rev ) {
		return trim( shell_exec( 'git -C ' . escapeshellarg( $repo ) . ' rev-parse ' . escapeshellarg( $rev ) ) );
	}

	private function _local_branches() {
		exec( 'git -C ' . escapeshellarg( dirname( WP_CONTENT_DIR ) ) . " for-each-ref --format='%(refname:short)' refs/heads", $branches );
		return $branches;
	}

	function test_remove_stale_locks() {
		global $git;

		$stale = array(
			$this->_put_lock( 'HEAD.lock', 900 ),
			$this->_put_lock( 'packed-refs.lock', 900 ),
			$this->_put_lock( 'CHERRY_PICK_HEAD.lock', 900 ),
			$this->_put_lock( 'refs/heads/merge_local.lock', 900 ),
			$this->_put_lock( 'refs/heads/release/1.0.lock', 900 ),
			$this->_put_lock( 'refs/remotes/origin/master.lock', 900 ),
			$this->_put_lock( 'refs/remotes/origin/feature/x.lock', 900 ),
			$this->_put_lock( 'refs/tags/v1.lock', 900 ),
		);
		$recent      = $this->_put_lock( 'refs/heads/master.lock', 10 );
		$maintenance = $this->_put_lock( 'gitium-maintenance.lock', 900 );

		$this->assertEquals( count( $stale ), $git->remove_stale_locks() );
		foreach ( $stale as $lock ) {
			$this->assertFalse( file_exists( $lock ), "$lock was not removed" );
		}
		$this->assertTrue( file_exists( $recent ) );
		$this->assertTrue( file_exists( $maintenance ) );
		unlink( $recent );
		unlink( $maintenance );
	}

	/**
	 * A stale `refs/heads/merge_local.lock` made every merge keep the local branch, behind the remote
	 * one, so the push was rejected as non-fast-forward and the remote changes never got on the site
	 */
	function test_merge_with_stale_merge_local_lock() {
		global $git;

		$this->_add_changes_remotely( 'remote', true );
		$lock = $this->_put_lock( 'refs/heads/merge_local.lock', 900 );
		$this->_start_new_request();

		$this->assertTrue( gitium_merge_and_push( array() ), 'Merge & push failed: ' . $git->get_last_error() );
		$this->assertFalse( file_exists( $lock ) );
		$this->assertStringEqualsFile( $this->local_file, 'remote' . PHP_EOL );
		$this->assertEquals( $this->_rev_parse( $this->remote_repo, 'master' ), $this->_rev_parse( dirname( WP_CONTENT_DIR ), 'HEAD' ) );
		$this->assertEquals( array( 'master' ), $this->_local_branches() );
	}

	/**
	 * A recent lock may belong to a git that is still working: the merge fails with the real error,
	 * nothing is pushed and the local branch is left as it was
	 */
	function test_merge_fails_with_recent_merge_local_lock() {
		global $git;

		$this->_add_changes_remotely( 'remote', true );
		$this->_add_changes_locally( 'local', true );
		$local_head  = $this->_rev_parse( dirname( WP_CONTENT_DIR ), 'HEAD' );
		$remote_head = $this->_rev_parse( $this->remote_repo, 'master' );
		$lock        = $this->_put_lock( 'refs/heads/merge_local.lock', 10 );

		$this->assertFalse( gitium_merge_and_push( array() ) );
		$this->assertContains( 'merge_local.lock', $git->get_last_error() );
		$this->assertNotContains( 'non-fast-forward', $git->get_last_error() );
		$this->assertEquals( $local_head, $this->_rev_parse( dirname( WP_CONTENT_DIR ), 'HEAD' ) );
		$this->assertEquals( $remote_head, $this->_rev_parse( $this->remote_repo, 'master' ) );
		$this->assertEquals( array( 'master' ), $this->_local_branches() );
		unlink( $lock );
	}

	/**
	 * A recent lock on the local branch makes the merge fail after the rename to 'merge_local':
	 * the local branch must get its name back, with its commits
	 */
	function test_merge_fails_with_recent_local_branch_lock() {
		global $git;

		$this->_add_changes_remotely( 'remote', true );
		$this->_add_changes_locally( 'local', true );
		$local_head = $this->_rev_parse( dirname( WP_CONTENT_DIR ), 'HEAD' );
		$lock       = $this->_put_lock( 'refs/heads/master.lock', 10 );

		$this->assertFalse( $git->merge_with_accept_mine() );
		$this->assertContains( 'master.lock', $git->get_last_error() );
		unlink( $lock );

		$this->assertEquals( 'master', $git->get_local_branch() );
		$this->assertEquals( $local_head, $this->_rev_parse( dirname( WP_CONTENT_DIR ), 'HEAD' ) );
		$this->assertEquals( array( 'master' ), $this->_local_branches() );
	}

	/**
	 * A 'merge_local' branch left by a killed merge is kept aside and the merge goes on
	 */
	function test_merge_with_leftover_merge_local_branch() {
		global $git;

		$this->_add_changes_remotely( 'remote', true );
		exec( 'git -C ' . escapeshellarg( dirname( WP_CONTENT_DIR ) ) . ' branch merge_local' );
		$leftover = $this->_rev_parse( dirname( WP_CONTENT_DIR ), 'merge_local' );

		$this->assertTrue( gitium_merge_and_push( array() ), 'Merge & push failed: ' . $git->get_last_error() );
		$this->assertStringEqualsFile( $this->local_file, 'remote' . PHP_EOL );

		$branches = $this->_local_branches();
		$this->assertCount( 2, $branches );
		$this->assertContains( 'master', $branches );
		$backup = current( array_diff( $branches, array( 'master' ) ) );
		$this->assertStringStartsWith( 'merge_local-', $backup );
		$this->assertEquals( $leftover, $this->_rev_parse( dirname( WP_CONTENT_DIR ), $backup ) );
	}

	/**
	 * A merge with conflicts it can't resolve gives the local branch its name and commits back, keeps
	 * the uncommitted edits and leaves no 'merge_local' behind (`checkout -b merge_local` always failed)
	 */
	function test_merge_conflict_restores_local_branch() {
		global $git;

		$edited = $this->_add_untracked_changes_locally( 'base' );
		$git->add();
		$git->commit( 'Add a file' );
		$this->assertTrue( gitium_merge_and_push( array() ), 'Merge & push failed: ' . $git->get_last_error() );

		$this->_add_changes_remotely( 'remote', true );
		$this->_add_changes_locally( 'local', true );
		$local_head = $this->_rev_parse( dirname( WP_CONTENT_DIR ), 'HEAD' );
		$git->fetch_ref();
		file_put_contents( $edited, 'edited' . PHP_EOL );

		// the conflicts of the cherry-pick are left unresolved
		$wrapper = new class( dirname( WP_CONTENT_DIR ) ) extends Git_Wrapper {
			function successfully_merged() {
				return false;
			}
		};
		$this->assertFalse( $wrapper->merge_with_accept_mine() );
		$this->assertNotEmpty( $wrapper->get_last_error() );
		$this->assertEquals( 'master', $wrapper->get_local_branch() );
		$this->assertEquals( $local_head, $this->_rev_parse( dirname( WP_CONTENT_DIR ), 'HEAD' ) );
		$this->assertEquals( array( 'master' ), $this->_local_branches() );
		$this->assertStringEqualsFile( $edited, 'edited' . PHP_EOL );
	}
	private function _git( $args ) {
		exec( 'git -C ' . escapeshellarg( dirname( WP_CONTENT_DIR ) ) . " $args 2>&1", $output, $return );
		return $return;
	}

	/**
	 * A merge killed after `git branch -m merge_local` and `git branch master origin/master` left
	 * HEAD on 'merge_local': every merge failed and the remote changes never got on the site
	 */
	function test_merge_with_head_left_on_merge_local() {
		global $git;

		$this->_add_changes_remotely( 'remote', true );
		$this->_add_changes_locally( 'local', true );
		$git->fetch_ref();
		$this->_git( 'branch -m merge_local' );
		$this->_git( 'branch master origin/master' );
		$this->assertEquals( 'merge_local', $git->get_local_branch() );

		$this->assertTrue( gitium_merge_and_push( array() ), 'Merge & push failed: ' . $git->get_last_error() );
		$this->assertEquals( 'master', $git->get_local_branch() );
		$this->assertEquals( array( 'master' ), $this->_local_branches() );
		$this->assertStringEqualsFile( $this->local_file, 'local' . PHP_EOL ); // accept mine
		$this->assertEquals( $this->_rev_parse( $this->remote_repo, 'master' ), $this->_rev_parse( dirname( WP_CONTENT_DIR ), 'HEAD' ) );
	}

	/**
	 * Killed right after `git branch -m merge_local`, before the local branch was created again
	 */
	function test_merge_with_head_left_on_merge_local_without_local_branch() {
		global $git;

		$this->_add_changes_remotely( 'remote', true );
		$this->_git( 'branch -m merge_local' );

		$this->assertTrue( gitium_merge_and_push( array() ), 'Merge & push failed: ' . $git->get_last_error() );
		$this->assertEquals( array( 'master' ), $this->_local_branches() );
		$this->assertStringEqualsFile( $this->local_file, 'remote' . PHP_EOL );
	}

	/**
	 * A local branch with commits of its own next to a 'merge_local' HEAD isn't replaced
	 */
	function test_merge_stops_when_local_branch_has_own_commits() {
		global $git;

		$this->_add_changes_locally( 'local', true );
		$this->_git( 'branch -m merge_local' );
		$this->_git( 'checkout -q -b master origin/master' );
		$this->_add_changes_locally( 'other', true );
		$master = $this->_rev_parse( dirname( WP_CONTENT_DIR ), 'master' );
		$this->_git( 'checkout -q merge_local' );
		$merge_local = $this->_rev_parse( dirname( WP_CONTENT_DIR ), 'merge_local' );

		$this->assertFalse( $git->merge_with_accept_mine() );
		$this->assertContains( 'checked by hand', $git->get_last_error() );
		$this->assertEquals( 'merge_local', $git->get_local_branch() );
		$this->assertEquals( $master, $this->_rev_parse( dirname( WP_CONTENT_DIR ), 'master' ) );
		$this->assertEquals( $merge_local, $this->_rev_parse( dirname( WP_CONTENT_DIR ), 'merge_local' ) );
	}

	/**
	 * A cherry-pick left in progress with conflicts made every checkout, so every merge, fail
	 */
	function test_merge_with_interrupted_cherry_pick() {
		global $git;

		$this->_add_changes_remotely( 'remote', true );
		$this->_add_changes_locally( 'local', true );
		$git->fetch_ref();
		$this->assertNotEquals( 0, $this->_git( 'cherry-pick origin/master' ) );
		$this->assertFileExists( dirname( WP_CONTENT_DIR ) . '/.git/CHERRY_PICK_HEAD' );

		$this->assertTrue( gitium_merge_and_push( array() ), 'Merge & push failed: ' . $git->get_last_error() );
		$this->assertFileNotExists( dirname( WP_CONTENT_DIR ) . '/.git/CHERRY_PICK_HEAD' );
		$this->assertEquals( array( 'master' ), $this->_local_branches() );
		$this->assertStringEqualsFile( $this->local_file, 'local' . PHP_EOL );
	}
}
