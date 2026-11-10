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
	 * A stale `refs/heads/merge_local.lock` made every merge keep the local branch, behind the remote
	 * one, so the push was rejected as non-fast-forward and the remote changes never got on the site
	 */
	function test_merge_with_stale_merge_local_lock() {
		global $git;

		$this->_add_changes_remotely( 'remote', true );
		$lock = $this->_put_lock( 'refs/heads/merge_local.lock', 900 );

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
}
