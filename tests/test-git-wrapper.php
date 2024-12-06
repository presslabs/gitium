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
require_once 'repl.php';

class Test_Git_Wrapper extends Gitium_UnitTestCase {

	/**
	 * Test is_dirty()
	 *
	 * 1.test if repo has uncommited changes(false expected)
	 * 2.add uncommited changes(local & remote)
	 * 3.test if repo has uncommited changes(true expected)
	 * 4.commit all changes and test again(false expected)
	 */
	function test_is_dirty() {
		global $git;

		// 1.test if repo has uncommited changes(false expected)
		$this->assertFalse( $git->is_dirty() );

		// 2.add uncommited changes(local & remote)
		$this->_add_changes_remotely( 'Add remote file', true );
		$this->_add_changes_locally();

		// 3.test if repo has uncommited changes(true expected)
		$this->assertTrue( $git->is_dirty() );

		// 4.commit all changes and test again(false expected)
		$git->commit( 'Add local file' );
		$this->assertFalse( $git->is_dirty() );
	}

	/**
	 * Test get_uncommited_changes()
	 *
	 * 1.test if repo has uncommited changes(EMPTY expected)
	 * 2.add uncommited changes(local & remote)
	 * 3.test if repo has uncommited changes(1 change expected)
	 * 4.commit all changes and test again(EMPTY expected)
	 */
	function test_get_uncommited_changes() {
		global $git;

		// 1.test if repo has uncommited changes(EMPTY expected)
		$this->assertEmpty( $git->get_uncommited_changes() );

		// 2.add uncommited changes(local)
		$this->_add_changes_locally();
		$this->_add_changes_remotely();

		// 3.test if repo has uncommited changes(1 change expected)
		$this->assertCount( 1, $git->get_uncommited_changes() );

		// 4.commit all changes and test again(EMPTY expected)
		exec( "cd {$this->work_repo} ; git commit -q -m 'Add remote file' ; git push -q" );
		$git->commit( 'Add local file' );
		$this->assertEmpty( $git->get_uncommited_changes() );
	}

	/**
	 * Test get_ahead_commits()
	 *
	 * 1.add local changes and commit them(add two ahead commits)
	 * 2.test if there are ahead commits(two expected)
	 */
	function test_get_ahead_commits() {
		global $git;

		// 1.add local changes and commit them(add two ahead commits)
		$this->_add_changes_locally( 'one', true );
		$this->_add_changes_locally( 'two', true );

		// 2.test if there are ahead commits(two expected)
		$this->assertCount( 2, $git->get_ahead_commits() );
	}

	/**
	 * Test get_behind_commits()
	 *
	 * 1.add remote changes and commit them(add three behind commits)
	 * 2.test if there are behind commits(three expected)
	 */
	function test_get_behind_commits() {
		global $git;

		// 1.add remote changes and commit them(add three behind commits)
		$this->_add_changes_remotely( 'one', true );
		$this->_add_changes_remotely( 'two', true );
		$this->_add_changes_remotely( 'three',true );

		$git->fetch_ref();

		// 2.test if there are behind commits(three expected)
		$this->assertCount( 3, $git->get_behind_commits() );
	}

	function test_can_exec_git() {
		global $git;
		$this->assertTrue( $git->can_exec_git() );
	}

	function test_is_status_working() {
		global $git;
		$this->assertTrue( $git->is_status_working() );
	}

	function test_get_version() {
		global $git;
		$this->assertNotEmpty( $git->get_version() );
	}

	function test_cleanup_with_dot_git() {
		global $git;
		$dot_git_dir = $git->repo_dir . '/.git';

		// make sure that .git dir is already initialized and it is a true .git dir
		$this->assertFileExists( $dot_git_dir );
		$this->assertFileExists( $dot_git_dir . '/config' );
		$this->assertFileExists( $dot_git_dir . '/index' );

		$this->assertTrue( $git->cleanup() );
		$this->assertFileNotExists( $dot_git_dir );
		$this->assertFileNotExists( $dot_git_dir . '/config' );
		$this->assertFileNotExists( $dot_git_dir . '/index' );
	}

	function test_cleanup_with_wrong_dot_git() {
		global $git;
		$wrong_dot_git_dir = $git->repo_dir . '/.git';
		$git->cleanup(); // remove the already initialized .git dir
		mkdir( $wrong_dot_git_dir ); // create a fake .git dir
		$this->assertFalse( $git->cleanup() );
		$this->assertFileExists( $wrong_dot_git_dir );
	}

	function test_get_remote_url() {
		global $git;
		$this->assertEquals( $git->get_remote_url(), $this->remote_repo );
	}

	function test_remove_remote() {
		global $git;
		$this->assertTrue( $git->remove_remote() );
		$this->assertEmpty( $git->get_remote_url() );
	}

	function test_remote_url() {
		global $git;

		$remote_url = $git->get_remote_url();
		if ( ! empty( $remote_url ) ) {
			$git->remove_remote();
		}

		$remote_url = 'http://my.server/username:password/repository.git';
		$git->add_remote_url( $remote_url );

		$this->assertEquals( $remote_url, $git->get_remote_url() );
	}
	function test_get_local_changes() {
		global $git;

		// 1.test if repo has local uncommited changes(EMPTY expected)
		$this->assertEmpty( $git->get_local_changes() );

		// 2.add uncommited changes(local)
		$this->_add_changes_locally();

		// 3.test if repo has uncommited changes(1 change expected)
		$this->assertCount( 1, $git->get_local_changes() );

		// 4.commit all changes and test again(EMPTY expected)
		$git->commit( 'Add local file' );
		$this->assertEmpty( $git->get_local_changes() );
	}


	function test_get_last_error() {
		global $git;
		$this->assertEmpty( $git->get_last_error() );
	}

	function test_commit_with_dif_user_and_email() {
		global $git;

		$this->_add_changes_locally();
		$git->add();
		$this->assertNotEquals( false, $git->commit( 'Add local changes', 'User', 'test@example.com' ) );
	}

	function test_repo_dir_init() {
		$repo_dir_name = '/my/repo/dir';
		$wrapper = new Git_Wrapper( $repo_dir_name );
		$this->assertEquals( $repo_dir_name, $wrapper->repo_dir );
	}

	function test_merge_initial_commit() {
		global $git;

		$this->_add_changes_locally();
		$git->add();
		$commit_id = $git->commit( 'Add local changes' );
		$this->assertTrue( $git->merge_initial_commit( $commit_id, 'master' ) );
	}

	function test_merge_with_accept_mine_1_ahead_and_1_behind_case_1() {
		global $git;

		$this->_add_changes_locally( 'local', true );
		$this->_add_changes_remotely( 'remote', true );
		$git->fetch_ref();

		$this->assertTrue( $git->merge_with_accept_mine() );
		$this->assertTrue( $git->successfully_merged() );
		$this->assertTrue( $git->push() );
	}

	function test_merge_with_accept_mine_1_ahead_and_1_behind_case_2() {
		global $git;

		$this->_add_changes_locally( 'local', true );
		$this->_add_changes_remotely( 'remote', true );
		$git->fetch_ref();
		$this->_add_untracked_changes_locally( 'local1' );

		$this->assertTrue( $git->merge_with_accept_mine() );
		$this->assertTrue( $git->successfully_merged() );
		$this->assertTrue( $git->push() );
	}

	function test_local_status() {
		global $git;

		$this->_add_changes_locally( 'local', true );
		$this->assertEquals( $git->local_status(), $git->status( true ) );
	}

	function test_local_status_one_change() {
		global $git;

		$this->_add_changes_locally( 'one', true ); // 1 commit ahead
		$this->_add_changes_locally( 'two' );
		list( $branch_status, $changes ) = $git->local_status();

		$this->assertStringEndsWith( '[ahead 1]', $branch_status );
		$this->assertEquals( $changes['work-file.txt'], 'M' );
	}

	function test_local_status_path_renamed_in_index() {
		/*
			Path renamed with git mv:

			echo 'some content' > some-file.txt
			git add --all
			git commit -m 'Add some file'
			git mv some-file.txt another-file.txt
			git status -s -b -u
		*/
		global $git;
		$filename     = 'some-file.txt';
		$new_filename = 'another-file.txt';
		file_put_contents( $git->repo_dir . '/' . $filename, 'some content' . PHP_EOL );
		$git->add();
		$git->commit('Add some file');
		$response = explode( "\n", shell_exec( "cd {$git->repo_dir} ; git mv {$filename} {$new_filename}" ) );
		$this->delete_on_teardown[] = $git->repo_dir . '/' . $new_filename;

		list( $branch_status, $changes ) = $git->local_status();

		$this->assertStringEndsWith( '[ahead 1]', $branch_status );
		$this->assertEquals( $changes[ $new_filename ], 'R  ' . $filename );
	}

	function test_status() {
		global $git;

		// 1.add local change
		$this->_add_changes_locally( 'local', true );

		// 2.add remote changes and commit them(add two behind commits)
		$this->_add_changes_remotely( 'one', true );
		$this->_add_changes_remotely( 'two', true );
		$git->fetch_ref();

		// 3.test if the changes are visible in status call
		$status = $git->status();
		$this->assertStringEndsWith( '[ahead 1, behind 2]', $status[0] );
	}

	function test_git_dir_constant() {
		global $git;
		$this->assertTrue( defined( 'GIT_DIR' ) );
		$this->assertEquals( GIT_DIR, $git->repo_dir );
	}

	/**
	 * Test paths with whitespaces get added and commited correctly
	 */
	function test_commit_path_with_whitespace() {
		global $git;
		$dir = $git->repo_dir . "/some dir/";
		$this->delete_on_teardown[] = $dir;
		try {
			mkdir( $dir, 0777, true );
		} catch (Exception $_) { }
		file_put_contents( $dir . "some file", "ana are mere" );
		$git->add();
		$changeset = $git->commit( "add path with whitespace" );
		// chdir( $git->repo_dir );
		$output = explode( "\n", shell_exec( "cd {$git->repo_dir} ; git show --name-status $changeset" ) );
		$expected = array(
			"    add path with whitespace",
			"",
			"A\tsome dir/some file");
		$out = array_slice( $output, 4, -1 );
		$this->assertEquals( $expected, $out );
	}

	/**
	 * Test is_dirty works for paths with whitespaces
	 */
	function test_is_dirty_with_whitespace() {
		global $git;
		$dir = $git->repo_dir . "/some dir/";
		$this->delete_on_teardown[] = $dir;
		try {
			mkdir( $dir, 0777, true );
		} catch (Exception $_) { }
		file_put_contents( $dir . "some file", "ana are mere" );
		$this->assertTrue( $git->is_dirty() );
	}

	/**
	 * Test get_local_changes for paths with whitespaces
	 */
	function test_get_local_changes_with_whitespace() {
		global $git;
		$dir = $git->repo_dir . "/some dir/";
		$this->delete_on_teardown[] = $dir;
		try {
			mkdir( $dir, 0777, true );
		} catch (Exception $_) { }
		file_put_contents( $dir . "some file", "ana are mere" );
		$git->add();
		// repl(get_defined_vars(), $this);
		$this->assertEquals(
			['some dir/some file' => 'modified'],
			$git->get_local_changes()
		);
	}

}
