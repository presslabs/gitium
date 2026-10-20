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

class Test_Gitium_Revert extends Gitium_UnitTestCase {

	/**
	 * The confirmed commit is reverted and the revert is pushed
	 */
	function test_revert_last_commit() {
		global $git;

		$this->_add_changes_locally( 'local', true );
		$commit_hash = key( $git->get_last_commits( 1 ) );

		list( $success, $message ) = gitium_revert_last_commit( $commit_hash );

		$this->assertTrue( $success, $message );
		$this->assertFalse( file_exists( $this->local_file ) );
		$this->assertFalse( $git->is_dirty() );
		$this->assertEmpty( $git->get_ahead_commits() );
	}

	/**
	 * A new commit landed after the page was loaded: nothing is reverted
	 */
	function test_revert_last_commit_refuses_when_last_commit_changed() {
		global $git;

		$this->_add_changes_locally( 'first', true );
		$confirmed_hash = key( $git->get_last_commits( 1 ) );
		$this->_add_changes_locally( 'second', true );
		$head_before = key( $git->get_last_commits( 1 ) );

		list( $success, $message ) = gitium_revert_last_commit( $confirmed_hash );

		$this->assertFalse( $success );
		$this->assertTrue( false !== strpos( $message, 'The latest commit changed' ), $message );
		$this->assertEquals( $head_before, key( $git->get_last_commits( 1 ) ) );
		$this->assertEquals( 'second' . PHP_EOL, file_get_contents( $this->local_file ) );
	}

	/**
	 * No commit hash was posted: nothing is reverted
	 */
	function test_revert_last_commit_refuses_without_hash() {
		global $git;

		$this->_add_changes_locally( 'local', true );
		$head_before = key( $git->get_last_commits( 1 ) );

		list( $success, ) = gitium_revert_last_commit( '' );

		$this->assertFalse( $success );
		$this->assertEquals( $head_before, key( $git->get_last_commits( 1 ) ) );
		$this->assertTrue( file_exists( $this->local_file ) );
	}

	/**
	 * Another Gitium operation holds the merge lock: nothing is reverted
	 */
	function test_revert_last_commit_refuses_while_merge_lock_is_held() {
		global $git;

		$this->_add_changes_locally( 'local', true );
		$commit_hash = key( $git->get_last_commits( 1 ) );

		// a separate handle on the lock file conflicts with gitium_acquire_merge_lock()
		$lock_path   = apply_filters( 'gitium_lock_path', sys_get_temp_dir() . '/.gitium-lock' );
		$lock_handle = fopen( $lock_path, 'w+' );
		$this->assertTrue( flock( $lock_handle, LOCK_EX | LOCK_NB ) );

		list( $success, $message ) = gitium_revert_last_commit( $commit_hash );

		flock( $lock_handle, LOCK_UN );
		fclose( $lock_handle );

		$this->assertFalse( $success );
		$this->assertTrue( false !== strpos( $message, 'Another Gitium operation is in progress' ), $message );
		$this->assertEquals( $commit_hash, key( $git->get_last_commits( 1 ) ) );
		$this->assertTrue( file_exists( $this->local_file ) );
	}
}
