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
			$this->_put_lock( 'refs/heads/merge_local.lock', 900 ),
			$this->_put_lock( 'refs/remotes/origin/master.lock', 900 ),
		);
		$recent = $this->_put_lock( 'refs/heads/master.lock', 10 );

		$this->assertEquals( 4, $git->remove_stale_locks() );
		foreach ( $stale as $lock ) {
			$this->assertFalse( file_exists( $lock ), "$lock was not removed" );
		}
		$this->assertTrue( file_exists( $recent ) );
		unlink( $recent );
	}

	/**
	 * A stale lock on the local branch made every merge fail, so the remote changes never got
	 * on the site: it's removed and the merge goes on
	 */
	function test_merge_with_stale_branch_lock() {
		global $git;

		$this->_add_changes_remotely( 'remote', true );
		$lock = $this->_put_lock( 'refs/heads/master.lock', 900 );

		$this->assertTrue( gitium_merge_and_push( array() ), 'Merge & push failed: ' . $git->get_last_error() );
		$this->assertFalse( file_exists( $lock ) );
		$this->assertStringEqualsFile( $this->local_file, 'remote' . PHP_EOL );
		$this->assertEquals( $this->_rev_parse( $this->remote_repo, 'master' ), $this->_rev_parse( dirname( WP_CONTENT_DIR ), 'HEAD' ) );
		$this->assertEquals( array( 'master' ), $this->_local_branches() );
		$this->assertFalse( $this->_ref_exists( Git_Wrapper::PRE_MERGE_REF ) );
	}

	/**
	 * A recent lock may belong to a git that is still working: the merge fails with the real error,
	 * nothing is pushed and the local branch is left as it was
	 */
	function test_merge_fails_with_recent_branch_lock() {
		global $git;

		$this->_add_changes_remotely( 'remote', true );
		$this->_add_changes_locally( 'local', true );
		$local_head  = $this->_rev_parse( dirname( WP_CONTENT_DIR ), 'HEAD' );
		$remote_head = $this->_rev_parse( $this->remote_repo, 'master' );
		$lock        = $this->_put_lock( 'refs/heads/master.lock', 10 );

		$this->assertFalse( gitium_merge_and_push( array() ) );
		$this->assertContains( 'master.lock', $git->get_last_error() );
		$this->assertNotContains( 'non-fast-forward', $git->get_last_error() );
		unlink( $lock );

		$this->assertEquals( 'master', $git->get_local_branch() );
		$this->assertEquals( $local_head, $this->_rev_parse( dirname( WP_CONTENT_DIR ), 'HEAD' ) );
		$this->assertEquals( $remote_head, $this->_rev_parse( $this->remote_repo, 'master' ) );
		$this->assertEquals( array( 'master' ), $this->_local_branches() );
		$this->assertFalse( $this->_ref_exists( Git_Wrapper::PRE_MERGE_REF ) );
	}

	/**
	 * A 'merge_local' branch left by a killed merge of an older Gitium doesn't block the merge
	 */
	function test_merge_with_leftover_merge_local_branch() {
		global $git;

		$this->_add_changes_remotely( 'remote', true );
		$this->_git( 'branch merge_local' );

		$this->assertTrue( gitium_merge_and_push( array() ), 'Merge & push failed: ' . $git->get_last_error() );
		$this->assertStringEqualsFile( $this->local_file, 'remote' . PHP_EOL );
		$this->assertEquals( array( 'master' ), $this->_local_branches() );
	}

	/**
	 * A merge killed after the local branch was moved on the remote one leaves the local commits
	 * only in the pre-merge ref: the next merge pushes them, with the ones made in the meantime
	 */
	function test_merge_after_killed_merge_with_pre_merge_ref() {
		global $git;

		$this->_add_changes_remotely( 'remote', true );
		$git->fetch_ref();
		$this->_add_untracked_changes_locally( 'local' );
		$git->add();
		$git->commit( 'Local commit' );
		$this->_git( 'update-ref ' . Git_Wrapper::PRE_MERGE_REF . ' HEAD' );
		$this->_git( 'checkout -q -B master origin/master' );
		$this->_add_untracked_changes_locally( 'local2' );
		$git->add();
		$git->commit( 'Commit after the killed merge' );

		$this->assertTrue( gitium_merge_and_push( array() ), 'Merge & push failed: ' . $git->get_last_error() );
		exec( 'git -C ' . escapeshellarg( $this->remote_repo ) . ' log -3 --format=%s master', $log );
		$this->assertEquals( array( 'Commit after the killed merge', 'Local commit', '[remote]: Commit remote file' ), $log );
		$this->assertEquals( array( 'master' ), $this->_local_branches() );
		$this->assertFalse( $this->_ref_exists( Git_Wrapper::PRE_MERGE_REF ) );
	}

	private function _ref_exists( $ref ) {
		return 0 == $this->_git( 'rev-parse --verify --quiet ' . escapeshellarg( $ref ) );
	}

	private function _git( $args ) {
		exec( 'git -C ' . escapeshellarg( dirname( WP_CONTENT_DIR ) ) . " $args 2>&1", $output, $return );
		return $return;
	}

	/**
	 * A merge killed right after `git branch -m merge_local` leaves HEAD on 'merge_local':
	 * the next merge must get the site back on its branch, with its local commits
	 */
	function test_merge_with_head_left_on_merge_local() {
		global $git;

		$this->_add_changes_remotely( 'remote', true );
		$this->_add_untracked_changes_locally( 'local' );
		$git->add();
		$git->commit( 'Local commit' );
		$this->_git( 'branch -m merge_local' );

		$this->assertTrue( gitium_merge_and_push( array() ), 'Merge & push failed: ' . $git->get_last_error() );
		$this->assertEquals( 'master', $git->get_local_branch() );
		$this->assertEquals( $this->_rev_parse( $this->remote_repo, 'master' ), $this->_rev_parse( dirname( WP_CONTENT_DIR ), 'HEAD' ) );
		$this->assertEquals( 'Local commit', trim( shell_exec( 'git -C ' . escapeshellarg( $this->remote_repo ) . ' log -1 --format=%s master' ) ) );
		$this->assertStringEqualsFile( $this->local_file, 'remote' . PHP_EOL );
	}

	/**
	 * A merge killed after the checkout of the remote based branch leaves the local commits
	 * only on 'merge_local': the next merge must push them, not leave them aside
	 */
	function test_merge_with_leftover_merge_local_holding_local_commits() {
		global $git;

		$this->_add_changes_remotely( 'remote', true );
		$git->fetch_ref();
		$this->_add_untracked_changes_locally( 'local' );
		$git->add();
		$git->commit( 'Local commit' );
		$this->_git( 'branch -m merge_local' );
		$this->_git( 'branch master origin/master' );
		$this->_git( 'checkout -q master' );
		$this->_git( 'branch --set-upstream-to=origin/master master' );

		$this->assertTrue( gitium_merge_and_push( array() ), 'Merge & push failed: ' . $git->get_last_error() );
		$this->assertEquals( 'master', $git->get_local_branch() );
		$this->assertEquals( 'Local commit', trim( shell_exec( 'git -C ' . escapeshellarg( $this->remote_repo ) . ' log -1 --format=%s master' ) ) );
	}

	/**
	 * When the merge can't be completed the local branch is restored, with its commits
	 */
	function test_failed_merge_restores_local_branch() {
		global $git;

		$this->_add_changes_remotely( 'remote', true );
		$this->_add_untracked_changes_locally( 'local' );
		$git->add();
		$git->commit( 'Local commit' );
		$local_head = $this->_rev_parse( dirname( WP_CONTENT_DIR ), 'HEAD' );
		$git->fetch_ref();

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
		$this->assertFalse( $this->_ref_exists( Git_Wrapper::PRE_MERGE_REF ) );
	}

	function test_remove_stale_nested_locks() {
		global $git;

		mkdir( "$this->git_dir/refs/heads/release", 0777, true );
		mkdir( "$this->git_dir/refs/remotes/origin/feature", 0777, true );
		$locks = array(
			$this->_put_lock( 'refs/heads/release/1.0.lock', 900 ),
			$this->_put_lock( 'refs/remotes/origin/feature/x.lock', 900 ),
			$this->_put_lock( 'refs/tags/v1.lock', 900 ),
		);
		$git->remove_stale_locks();
		foreach ( $locks as $lock ) {
			$this->assertFalse( file_exists( $lock ), "$lock was not removed" );
		}
	}

	function test_merge_without_upstream_has_an_error() {
		global $git;

		$this->_git( 'branch --unset-upstream' );
		$this->assertFalse( $git->merge_with_accept_mine() );
		$this->assertNotEmpty( $git->get_last_error() );
	}
}
