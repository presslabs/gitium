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

		$this->assertFalse( $git->remove_stale_index_lock() );
	}

	function test_remove_stale_index_lock() {
		global $git;

		$this->_put_index_lock( 900 );
		$this->assertTrue( $git->remove_stale_index_lock() );
		$this->assertFalse( file_exists( $this->index_lock ) );
	}

	function test_keep_recent_index_lock() {
		global $git;

		$this->_put_index_lock( 10 );
		$this->assertFalse( $git->remove_stale_index_lock() );
		$this->assertTrue( file_exists( $this->index_lock ) );
	}

	function test_min_age() {
		global $git;

		$this->_put_index_lock( 900 );
		$this->assertFalse( $git->remove_stale_index_lock( 3600 ) );
		$this->assertTrue( file_exists( $this->index_lock ) );
	}

	/**
	 * The webhook and "Save changes" start with add() and commit() on a dirty repo:
	 * an old index.lock must not make them fail
	 */
	function test_commit_dirty_repo_with_stale_index_lock() {
		global $git;

		file_put_contents( $this->local_file, 'local' . PHP_EOL );
		$this->_put_index_lock( 900 );
		$this->_start_new_request();

		$this->assertGreaterThan( 0, $git->add() );
		$this->assertNotEmpty( $git->commit( 'Commit after a stale index.lock' ) );
		$this->assertFalse( file_exists( $this->index_lock ) );
		$this->assertEmpty( $git->get_local_changes() );
	}

	/**
	 * A recent index.lock may belong to a git that is still working, so it's kept and the commit fails
	 */
	function test_commit_fails_with_recent_index_lock() {
		global $git;

		file_put_contents( $this->local_file, 'local' . PHP_EOL );
		$this->_put_index_lock( 10 );

		$git->add();
		$this->assertFalse( $git->commit( 'Commit with a recent index.lock' ) );
		$this->assertTrue( file_exists( $this->index_lock ) );
		unlink( $this->index_lock );
	}
}
