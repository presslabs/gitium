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

	private function _git( $args ) {
		exec( 'git -C ' . escapeshellarg( dirname( WP_CONTENT_DIR ) ) . " $args 2>&1", $output, $return );
		return $return;
	}

	private function _rev_parse( $repo, $rev ) {
		return trim( shell_exec( 'git -C ' . escapeshellarg( $repo ) . ' rev-parse ' . escapeshellarg( $rev ) ) );
	}

	private function _local_branches() {
		exec( 'git -C ' . escapeshellarg( dirname( WP_CONTENT_DIR ) ) . " for-each-ref --format='%(refname:short)' refs/heads", $branches );
		return $branches;
	}

	private function _local_commit( $message ) {
		global $git;

		$this->_add_untracked_changes_locally( $message );
		$git->add();
		$git->commit( $message );
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
	 * The production case: a stale `refs/heads/merge_local.lock` made every merge keep the local
	 * branch, behind the remote one, so the push was rejected as non-fast-forward
	 */
	function test_merge_with_stale_merge_local_lock() {
		global $git;

		$this->_add_changes_remotely( 'remote', true );
		$lock = $this->_put_lock( 'refs/heads/merge_local.lock', 900 );
		$this->_start_new_request();

		$this->assertMergeAndPush();
		$this->assertFalse( file_exists( $lock ) );
		$this->assertStringEqualsFile( $this->local_file, 'remote' . PHP_EOL );
	}

	/**
	 * A recent lock may belong to a git that is still working: the merge fails with the real error,
	 * nothing is pushed and the local branch is left as it was
	 */
	function test_merge_fails_with_recent_merge_local_lock() {
		global $git;

		$this->_add_changes_remotely( 'remote', true );
		$this->_local_commit( 'Local commit' );
		$local_head  = $this->_rev_parse( dirname( WP_CONTENT_DIR ), 'HEAD' );
		$remote_head = $this->_rev_parse( $this->remote_repo, 'master' );
		$lock        = $this->_put_lock( 'refs/heads/merge_local.lock', 10 );
		$this->_start_new_request();

		$this->assertFalse( gitium_merge_and_push( array() ) );
		$this->assertContains( 'merge_local.lock', $git->get_last_error() );
		$this->assertNotContains( 'non-fast-forward', $git->get_last_error() );
		$this->assertTrue( file_exists( $lock ) );
		unlink( $lock );

		$this->assertEquals( 'master', $git->get_local_branch() );
		$this->assertEquals( $local_head, $this->_rev_parse( dirname( WP_CONTENT_DIR ), 'HEAD' ) );
		$this->assertEquals( $remote_head, $this->_rev_parse( $this->remote_repo, 'master' ) );
		$this->assertEquals( array( 'master' ), $this->_local_branches() );
	}

	// merge & push, then the site is on 'master', equal to the remote one, with no other branch
	private function assertMergeAndPush() {
		global $git;

		$this->assertTrue( gitium_merge_and_push( array() ), 'Merge & push failed: ' . $git->get_last_error() );
		$this->assertEquals( 'master', $git->get_local_branch() );
		$this->assertEquals( $this->_rev_parse( $this->remote_repo, 'master' ), $this->_rev_parse( dirname( WP_CONTENT_DIR ), 'HEAD' ) );
	}

	private function _remote_log( $count ) {
		exec( 'git -C ' . escapeshellarg( $this->remote_repo ) . " log -$count --format=%s master", $log );
		return $log;
	}

	/**
	 * A cherry-pick killed mid-way leaves CHERRY_PICK_HEAD and conflicts in the index
	 */
	function test_merge_after_interrupted_cherry_pick() {
		global $git;

		$this->_add_changes_remotely( 'remote', true );
		$this->_add_changes_locally( 'local', true );
		$git->fetch_ref();
		$this->_git( 'cherry-pick origin/master' );
		$this->assertTrue( file_exists( "$this->git_dir/CHERRY_PICK_HEAD" ) );

		$this->assertMergeAndPush();
		$this->assertFalse( file_exists( "$this->git_dir/CHERRY_PICK_HEAD" ) );
		$this->assertStringEqualsFile( $this->local_file, 'local' . PHP_EOL );
		$this->assertEquals( array( 'master' ), $this->_local_branches() );
	}

	/**
	 * A merge killed right after `git branch -m merge_local` leaves HEAD on 'merge_local'
	 */
	function test_merge_with_head_left_on_merge_local() {
		$this->_add_changes_remotely( 'remote', true );
		$this->_local_commit( 'Local commit' );
		$this->_git( 'branch -m merge_local' );

		$this->assertMergeAndPush();
		$this->assertEquals( array( 'Local commit', '[remote]: Commit remote file' ), $this->_remote_log( 2 ) );
		$this->assertEquals( array( 'master' ), $this->_local_branches() );
	}

	/**
	 * Same, killed after the local branch was created from the remote one
	 */
	function test_merge_with_head_left_on_merge_local_and_remote_based_branch() {
		global $git;

		$this->_add_changes_remotely( 'remote', true );
		$this->_local_commit( 'Local commit' );
		$git->fetch_ref();
		$this->_git( 'branch -m merge_local' );
		$this->_git( 'branch master origin/master' );

		$this->assertMergeAndPush();
		$this->assertEquals( array( 'Local commit', '[remote]: Commit remote file' ), $this->_remote_log( 2 ) );
		$this->assertEquals( array( 'master' ), $this->_local_branches() );
	}

	/**
	 * A 'merge_local' whose commits are all merged is deleted
	 */
	function test_merge_with_merged_leftover_merge_local() {
		$this->_local_commit( 'Local commit' );
		gitium_merge_and_push( array() );
		$this->_git( 'branch merge_local' );
		$this->_add_changes_remotely( 'remote', true );

		$this->assertMergeAndPush();
		$this->assertEquals( array( 'master' ), $this->_local_branches() );
		$this->assertEmpty( gitium_get_kept_merge_commits() );
	}

	/**
	 * A 'merge_local' with commits that aren't merged is kept aside, not replayed, and reported
	 */
	function test_merge_with_unmerged_leftover_merge_local() {
		$this->_local_commit( 'Kept commit' );
		$this->_git( 'branch merge_local' );
		$this->_git( 'reset -q --hard HEAD~1' );
		$this->_add_changes_remotely( 'remote', true );

		$this->assertMergeAndPush();
		$this->assertEquals( array( '[remote]: Commit remote file' ), $this->_remote_log( 1 ) );

		$branches = $this->_local_branches();
		$this->assertCount( 2, $branches );
		$backup = current( array_diff( $branches, array( 'master' ) ) );
		$this->assertStringStartsWith( 'merge_local-', $backup );
		$this->assertEquals( 'Kept commit', trim( shell_exec( 'git -C ' . escapeshellarg( dirname( WP_CONTENT_DIR ) ) . " log -1 --format=%s $backup" ) ) );
		$this->assertEquals( array( $backup => 1 ), gitium_get_kept_merge_commits() );

		// the notice goes away with the branch
		$this->_git( "branch -D $backup" );
		$this->assertEmpty( gitium_get_kept_merge_commits() );
	}

	/**
	 * When the merge can't be completed the local branch is restored, with its commits and the
	 * uncommitted edits
	 */
	function test_failed_merge_restores_local_branch_and_keeps_edits() {
		global $git;

		$edited = $this->_add_untracked_changes_locally( 'base' );
		$git->add();
		$git->commit( 'Add a file' );
		$this->assertMergeAndPush();

		$this->_add_changes_remotely( 'remote', true );
		$this->_local_commit( 'Local commit' );
		$local_head = $this->_rev_parse( dirname( WP_CONTENT_DIR ), 'HEAD' );
		$git->fetch_ref();
		file_put_contents( $edited, 'edited' . PHP_EOL );

		// the merge fails: the first check (before the merge) passes, the one after it doesn't
		$wrapper = new class( dirname( WP_CONTENT_DIR ) ) extends Git_Wrapper {
			private $checks = 0;
			function successfully_merged() {
				return ( 1 == ++$this->checks ) ? parent::successfully_merged() : false;
			}
		};
		$this->assertFalse( $wrapper->merge_with_accept_mine() );
		$this->assertNotEmpty( $wrapper->get_last_error() );
		$this->assertEquals( 'master', $wrapper->get_local_branch() );
		$this->assertEquals( $local_head, $this->_rev_parse( dirname( WP_CONTENT_DIR ), 'HEAD' ) );
		$this->assertEquals( array( 'master' ), $this->_local_branches() );
		$this->assertStringEqualsFile( $edited, 'edited' . PHP_EOL );
	}

	function test_merge_without_upstream_has_an_error() {
		global $git;

		$this->_git( 'branch --unset-upstream' );
		$this->assertFalse( $git->merge_with_accept_mine() );
		$this->assertNotEmpty( $git->get_last_error() );
	}
}
