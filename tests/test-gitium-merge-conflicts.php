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

class Test_Gitium_Merge_Conflicts extends Gitium_UnitTestCase {
	/**
	 * Create merge conflict: AA -> unmerged, both added
	 *
	 * 1.create one file remotely with the content `remote` (add & commit)
	 * 2.create the same file locally with the content `local` (add & commit)
	 */
	private function _create_merge_conflict_aa() {
		global $git;

		// 1.add & commit (remote)
		exec( "cd {$this->work_repo} ; echo 'remote' > $this->work_fname " );
		exec( "cd {$this->work_repo} ; git add $this->work_fname ; git commit -q -m 'Add remote file' ; git push -q" );

		// 2.add & commit (local)
		file_put_contents( "$this->local_file", 'local' . PHP_EOL );
		$git->add();
		$git->commit( 'Add local file' );
	}

	/**
	 * Test merge conflict: AA -> unmerged, both added
	 *
	 * 1.create merge conflict AA (unmerged, both added)
	 * 2.test `merge with accept mine` conflict
	 * 3.check if the content `local` is the final text after the `merge` process
	 */
	function test_merge_conflict_aa() {
		global $git;

		// 1.set merge conflict AA (unmerged, both added)
		$this->_create_merge_conflict_aa();

		// 2.test merge with accept mine conflict
		$this->assertMerge( '[AA] ' );

		// 3.check if the result is what it's supposed to be from merge process
		$this->_create_work_fresh_clone();
		$this->assertFileEquals( $this->local_file, $this->work_file );
		$this->assertStringEqualsFile( $this->local_file, 'local' . PHP_EOL );
	}

	/**
	 * Create merge conflict: UU -> unmerged, both modified
	 *
	 * 1.create merge conflict AA
	 * 2.merge with accept mine
	 * 3.modify the remote file with the content `remote:uu` (edit,add & commit)
	 * 4.modify the same file locally with the content `local:uu` (edit,add & commit)
	 */
	private function _create_merge_conflict_uu() {
		global $git;

		// 1.create merge conflict AA (unmerged, both added)
		$this->_create_merge_conflict_aa();

		// 2.merge with accept mine
		$this->assertMerge( '[AA] ' );

		// 3.modify the remote file with the content `remote:uu` (edit,add & commit)
		file_put_contents( "$this->work_file", 'remote:uu' );
		$git->add();
		$git->commit( 'Change remote file' );

		// 4.modify the same file locally with the content `local:uu` (edit,add & commit)
		file_put_contents( "$this->local_file", 'local:uu' . PHP_EOL );
		$git->add();
		$git->commit( 'Change local file' );
	}

	/**
	 * Test merge conflict: UU -> unmerged, both modified
	 *
	 * 1.set merge conflict UU (unmerged, both modified)
	 * 2.test `merge with accept mine` conflict
	 * 3.check if the content `local:uu` is the final text after the `merge` process
	 */
	function test_merge_conflict_uu() {
		global $git;

		// 1.set merge conflict UU (unmerged, both modified)
		$this->_create_merge_conflict_uu();

		// 2.test `merge with accept mine` conflict
		$this->assertMerge( '[UU] ' );

		// 3.check if the content `local:uu` is the final text after the `merge` process
		$this->_create_work_fresh_clone();
		$this->assertFileEquals( $this->local_file, $this->work_file );
		$this->assertStringEqualsFile( $this->local_file, 'local:uu' . PHP_EOL );
	}

	/**
	 * Create merge conflict: AU -> unmerged, added by us
	 *
	 * 1.create one file remotely with the content `remote` (add & commit)
	 * 2.change the remote file content with `remote:au` (edit,add,commit & push)
	 * 3.create the same file locally with the content `local:au` (add & commit)
	 */
	private function _create_merge_conflict_au() {
		global $git;

		// 1.create one file remotely with the content `remote` (add & commit)
		exec( "cd {$this->work_repo} ; echo 'remote' > $this->work_fname " );
		exec( "cd {$this->work_repo} ; git add $this->work_fname ; git commit -q -m 'Add remote file'" );

		// 2.change the remote file content with `remote:au` (edit,add,commit & push)
		file_put_contents( "$this->work_file", 'remote:au' );
		exec( "cd {$this->work_repo} ; git add $this->work_fname ; git commit -q -m 'Change remote file' ; git push -q" );

		// 3.create the same file locally with the content `local:au` (edit,add & commit)
		file_put_contents( "$this->local_file", 'local:au' . PHP_EOL );
		$git->add();
		$git->commit( 'Change local file' );
	}

	/**
	 * Test merge conflict: AU -> unmerged, added by us
	 *
	 * 1.create merge conflict AU (unmerged, added by us)
	 * 2.test `merge with accept mine` conflict
	 * 3.check if the content `local:au` is the final text after the `merge` process
	 */
	function test_merge_conflict_au() {
		global $git;

		// 1.create merge conflict AU (unmerged, added by us)
		$this->_create_merge_conflict_au();

		// 2.test `merge with accept mine` conflict
		$this->assertMerge( '[AU] ' );

		// 3.check if the content `local:au` is the final text after the `merge` process
		$this->_create_work_fresh_clone();
		$this->assertFileEquals( $this->local_file, $this->work_file );
		$this->assertStringEqualsFile( $this->local_file, 'local:au' . PHP_EOL );
	}

	/**
	 * Create merge conflict: UA -> unmerged, added by them
	 *
	 * 1.create one file locally with the content `local` (add & commit)
	 * 2.change the local file content with `local:ua` (edit,add & commit)
	 * 3.add the same file remotely with the content `remote:ua` (add & commit)
	 */
	private function _create_merge_conflict_ua() {
		global $git;

		// 1.create one file locally with the content `local` (add & commit)
		file_put_contents( "$this->local_file", 'local' );
		$git->add();
		$git->commit( 'Create local file' );

		// 2.change the local file content with `local:ua` (edit,add & commit)
		file_put_contents( "$this->local_file", 'local:ua' . PHP_EOL );
		$git->add();
		$git->commit( 'Change local file' );

		// 3.add the same file remotely with the content `remote:ua` (add,commit & push)
		exec( "cd {$this->work_repo} ; echo 'remote:ua' > $this->work_fname " );
		exec( "cd {$this->work_repo} ; git add $this->work_fname ; git commit -q -m 'Add remote file' ; git push -q" );
	}

	/**
	 * Test merge conflict: UA -> unmerged, added by them
	 *
	 * 1.create merge conflict UA (unmerged, added by them)
	 * 2.test `merge with accept mine` conflicts
	 * 3.check if the content `local:ua` is the final text after the `merge with accept mine` process
	 */
	function test_merge_conflict_ua() {
		global $git;

		// 1.create merge conflict UA (unmerged, added by them)
		$this->_create_merge_conflict_ua();

		// 2.test `merge with accept mine` conflicts
		$this->assertMerge( '[UA] ' );

		// 3.check if the content `local:ua` is the final text after the `merge with accept mine` process
		$this->_create_work_fresh_clone();
		$this->assertFileEquals( $this->local_file, $this->work_file );
		$this->assertStringEqualsFile( $this->local_file, 'local:ua' . PHP_EOL );
	}

	/**
	 * Create merge conflict: DU -> unmerged, deleted by us
	 *
	 * 1.create merge conflict AA (unmerged, both added)
	 * 2.merge with accept mine
	 * 3.change the remote file content with `remote:du` (edit,add & commit)
	 * 4.remove the file locally (rm & commit)
	 */
	private function _create_merge_conflict_du() {
		global $git;

		// 1.create merge conflict AA (unmerged, both added)
		$this->_create_merge_conflict_aa();

		// 2.merge with accept mine
		$this->assertMerge( '[AA] ' );

		// 3.change the remote file content with `remote:du` (edit,add & commit)
		$this->_create_work_fresh_clone();
		file_put_contents( "$this->work_file", 'remote:du' . PHP_EOL );
		exec( "cd {$this->work_repo} ; git add $this->work_fname ; git commit -q -m 'Change remote file' ; git push -q" );

		// 4.remove the file locally (rm & commit)
		exec( 'cd ' . dirname( WP_CONTENT_DIR ) . " ; git rm -f -q $this->local_file ; git commit -q -m 'Remove local file'" );

		// 2.change the remote file content with `remote:au` (edit,add,commit & push)
		file_put_contents( "$this->work_file", 'remote:au' );
		exec( "cd {$this->work_repo} ; git add $this->work_fname ; git commit -q -m 'Change remote file' ; git push -q" );
	}

	/**
	 * Test merge conflict: DU -> unmerged, deleted by us
	 *
	 * 1.create merge conflict DU (unmerged, deleted by us)
	 * 2.merge with accept mine
	 * 3.check if the local file exists(FALSE expected)
	 */
	function test_merge_conflict_du() {
		global $git;

		// 1.create merge conflict DU (unmerged, deleted by us)
		$this->_create_merge_conflict_du();

		// 2.merge with accept mine
		$this->assertMerge( '[DU] ' );

		// 3.check if the local and remote file exists
		$this->_create_work_fresh_clone();
		$this->assertFileNotExists( $this->local_file );
		$this->assertFileNotExists( $this->work_file );
	}

	/**
	 * Create merge conflict: UD -> unmerged, deleted by them
	 *
	 * 1.create merge conflict AA (unmerged, both added)
	 * 2.change the local file content with `local:ud` (edit,add & commit)
	 * 3.remove the file remotely (rm,commit & push)
	 */
	private function _create_merge_conflict_ud() {
		global $git;

		// 1.create merge conflict AA (unmerged, both added)
		$this->_create_merge_conflict_aa();

		// 2.change the local file content with `local:ud` (edit,add & commit)
		file_put_contents( "$this->local_file", 'local:ud' . PHP_EOL );
		$git->add();
		$git->commit( 'Change local file' );

		// 3.remove the file remotely (rm,commit & push)
		$this->_create_work_fresh_clone();
		exec( "cd {$this->work_repo} ; git rm -f -q $this->work_file ; git commit -m 'Remove remote file' ; git push -q" );
	}

	/**
	 * Test merge conflict: UD -> unmerged, deleted by them
	 *
	 * 1.create merge conflict UD (unmerged, deleted by them)
	 * 2.merge with accept mine
	 * 3.check if the remote file exists(TRUE expected)
	 */
	function test_merge_conflict_ud() {
		global $git;

		// 1.create merge conflict UD (unmerged, deleted by them)
		$this->_create_merge_conflict_ud();

		// 2.merge with accept mine
		$this->assertMerge( '[UD] ' );

		// 3.check if the remote file exists & the content is `local:ud`
		$this->_create_work_fresh_clone();
		$this->assertFileEquals( $this->local_file, $this->work_file );
		$this->assertStringEqualsFile( $this->local_file, 'local:ud' . PHP_EOL );
	}

	/**
	 * Create merge conflict: DD -> unmerged, both deleted
	 *
	 * 1.create merge conflict AA (unmerged, both added)
	 * 2.remove the local file (rm & commit)
	 * 3.remove the file remotely (rm,commit & push)
	 */
	private function _create_merge_conflict_dd() {
		global $git;

		// 1.set merge conflict AA (unmerged, both added)
		$this->_create_merge_conflict_aa();

		// 2.remove the local file (rm & commit)
		exec( 'cd ' . dirname( WP_CONTENT_DIR ) . " ; git rm -f -q $this->local_file ; git commit -q -m 'Remove local file'" );

		// 3.remove the file remotely (rm,commit & push)
		$this->_create_work_fresh_clone();
		exec( "cd {$this->work_repo} ; git rm -f -q $this->work_file ; git commit -m 'Remove remote file' ; git push -q" );
	}

	/**
	 * Test merge conflict: DD -> unmerged, both deleted
	 *
	 * 1.create merge conflict DD (unmerged, both deleted)
	 * 2.merge with accept mine
	 * 3.check if the local and remote file exists(FALSE expected)
	 */
	function test_merge_conflict_dd() {
		global $git;

		// 1.create merge conflict DD (unmerged, both deleted)
		$this->_create_merge_conflict_dd();

		// 2.merge with accept mine
		$this->assertMerge( '[DD] ' );

		// 3.check if the local and remote file exists
		$this->_create_work_fresh_clone();
		$this->assertFileNotExists( $this->local_file );
		$this->assertFileNotExists( $this->work_file );
	}
}
