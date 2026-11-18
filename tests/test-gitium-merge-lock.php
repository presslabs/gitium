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

/**
 * The operations that write the index run under the merge lock, so two Gitium requests
 * (webhook, Save changes, auto-push) never compete for .git/index.lock
 */
class Test_Gitium_Merge_Lock extends Gitium_UnitTestCase {

	function test_commit_changes_releases_the_lock() {
		file_put_contents( $this->local_file, 'local' . PHP_EOL );

		$this->assertNotFalse( _gitium_commit_changes( 'Commit local changes' ) );

		// the lock is free again
		$lock = gitium_acquire_merge_lock();
		$this->assertNotFalse( $lock );
		gitium_release_merge_lock( $lock );
	}

	/**
	 * While another request holds the lock nothing is committed (flock conflicts between two
	 * opened handles, even in the same process, so this test plays the other request)
	 */
	function test_commit_changes_waits_for_the_lock() {
		global $git;

		file_put_contents( $this->local_file, 'local' . PHP_EOL );
		$lock = gitium_acquire_merge_lock();

		$this->assertFalse( _gitium_commit_changes( 'Commit local changes' ) );
		$this->assertNotEmpty( $git->get_local_changes() );

		gitium_release_merge_lock( $lock );
	}

}
