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

class Test_Gitium_Index_Lock extends Gitium_UnitTestCase {

	private $index_lock = '';

	public function setup() {
		parent::setup();
		$this->index_lock = dirname( WP_CONTENT_DIR ) . '/.git/index.lock';
	}

	// an index.lock like the one a killed git process leaves behind, $age seconds old
	private function _put_index_lock( $age ) {
		touch( $this->index_lock, time() - $age );
	}

	function test_no_index_lock() {
		global $git;

		$this->assertFalse( $git->remove_stale_index_lock( 300 ) );
	}

	function test_remove_stale_index_lock() {
		global $git;

		$this->_put_index_lock( 600 );
		$this->assertTrue( $git->remove_stale_index_lock( 300 ) );
		$this->assertFalse( file_exists( $this->index_lock ) );

		// the index can be written again
		$this->_add_changes_locally( 'local', false );
		$this->assertNotEmpty( $git->commit( 'Commit after the stale index.lock was removed' ) );
	}

	function test_keep_recent_index_lock() {
		global $git;

		$this->_put_index_lock( 10 );
		$this->assertFalse( $git->remove_stale_index_lock( 300 ) );
		$this->assertTrue( file_exists( $this->index_lock ) );
	}

	/**
	 * An old index.lock of a git process that is still working is not removed
	 */
	function test_keep_index_lock_while_git_is_running() {
		global $git;

		if ( ! is_dir( '/proc/self' ) ) {
			$this->markTestSkipped( 'needs /proc to see the running git processes' );
		}

		// `git cat-file --batch` runs in the repo until its stdin is closed
		$proc = proc_open(
			'exec git cat-file --batch',
			array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ) ),
			$pipes,
			dirname( WP_CONTENT_DIR )
		);
		$this->assertTrue( is_resource( $proc ) );
		$this->_put_index_lock( 600 );

		$this->assertTrue( $git->is_git_running() );
		$this->assertFalse( $git->remove_stale_index_lock( 300 ) );
		$this->assertTrue( file_exists( $this->index_lock ) );

		fclose( $pipes[0] );
		fclose( $pipes[1] );
		proc_close( $proc );

		$this->assertFalse( $git->is_git_running() );
		$this->assertTrue( $git->remove_stale_index_lock( 300 ) );
	}

	/**
	 * A git that exited but was not reaped yet (a zombie) holds no lock: in a container nobody may reap it
	 */
	function test_zombie_git_is_not_running() {
		global $git;

		if ( ! is_dir( '/proc/self' ) ) {
			$this->markTestSkipped( 'needs /proc to see the running git processes' );
		}

		// not closed yet, so the exited git stays a zombie
		$proc = proc_open( 'exec git --version', array( 1 => array( 'pipe', 'w' ) ), $pipes, dirname( WP_CONTENT_DIR ) );
		usleep( 300000 );
		$this->_put_index_lock( 600 );

		$this->assertFalse( $git->is_git_running() );
		$this->assertTrue( $git->remove_stale_index_lock( 300 ) );

		fclose( $pipes[1] );
		proc_close( $proc );
	}

	/**
	 * Every locked operation (webhook merge, auto push, revert) starts with the stale index.lock removed
	 */
	function test_merge_lock_removes_stale_index_lock() {
		$this->_put_index_lock( 600 );

		$lock = gitium_acquire_merge_lock();
		$this->assertNotFalse( $lock );
		gitium_release_merge_lock( $lock );

		$this->assertFalse( file_exists( $this->index_lock ) );
	}

	function test_merge_lock_respects_stale_index_lock_age_filter() {
		$this->_put_index_lock( 600 );
		$max_age = function() { return 3600; };
		add_filter( 'gitium_stale_index_lock_age', $max_age );

		$lock = gitium_acquire_merge_lock();
		gitium_release_merge_lock( $lock );
		remove_filter( 'gitium_stale_index_lock_age', $max_age );

		$this->assertTrue( file_exists( $this->index_lock ) );
	}
}
