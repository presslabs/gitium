<?php require_once 'gitium-unittestcase.php';
class Test_Git_Wrapper extends Gitium_UnitTestCase {
	/**
	 * Test is_dirty()
	 *
	 * 1.test if repo has uncommited changes(FALSE expected)
	 * 2.add uncommited changes(local & remote)
	 * 3.test if repo has uncommited changes(TRUE expected)
	 * 4.commit all changes and test again(FALSE expected)
	 */
	function test_is_dirty() {
		global $git;

		// 1.test if repo has uncommited changes(FALSE expected)
		$this->assertFalse( $git->is_dirty() );

		// 2.add uncommited changes(local & remote)
		$this->_add_uncommited_changes_remotely( 'Add remote file', TRUE );
		$this->_add_uncommited_changes_locally();

		// 3.test if repo has uncommited changes(TRUE expected)
		$this->assertTrue( $git->is_dirty() );

		// 4.commit all changes and test again(FALSE expected)
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
		$this->_add_uncommited_changes_locally();
		$this->_add_uncommited_changes_remotely();

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
		$this->_add_uncommited_changes_locally( 'one', TRUE );
		$this->_add_uncommited_changes_locally( 'two', TRUE );

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
		$this->_add_uncommited_changes_remotely( 'one', TRUE );
		$this->_add_uncommited_changes_remotely( 'two', TRUE );
		$this->_add_uncommited_changes_remotely( 'three',TRUE );

		$git->fetch_ref();

		// 2.test if there are behind commits(three expected)
		$this->assertCount( 3, $git->get_behind_commits() );
	}

	function test_can_exec_git() {
		global $git;
		$this->assertTrue( $git->can_exec_git() );
	}

	function test_is_versioned() {
		global $git;
		$this->assertTrue( $git->is_versioned() );
	}

	function test_get_version() {
		global $git;
		$this->assertNotEmpty( $git->get_version() );
	}

	function test_cleanup() {
		global $git;
		$git->cleanup();
		$this->assertFalse( $git->is_versioned() );
	}

	function test_get_remote_url() {
		global $git;
		$this->assertEquals( $git->get_remote_url(), $this->remote_repo );
	}

	function test_get_local_changes() {
		global $git;

		// 1.test if repo has local uncommited changes(EMPTY expected)
		$this->assertEmpty( $git->get_local_changes() );

		// 2.add uncommited changes(local)
		$this->_add_uncommited_changes_locally();

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

	function test_create_branch() {
		global $git;
		$this->assertTrue( $git->create_branch( 'develop' ) );
	}

	function test_commit_with_dif_user_and_email() {
		global $git;

		$this->_add_uncommited_changes_locally();
		$git->add();
		$this->assertNotEquals( FALSE, $git->commit( 'Add local changes', 'User', 'test@example.com' ) );
	}

	function test_repo_dir_init() {
		$repo_dir_name = '/my/repo/dir';
		$wrapper = new Git_Wrapper( $repo_dir_name );
		$this->assertEquals( $repo_dir_name, $wrapper->repo_dir );
	}

	function test_merge_initial_commit() {
		global $git;

		$this->_add_uncommited_changes_locally();
		$git->add();
		$commit_id = $git->commit( 'Add local changes' );
		$this->assertTrue( $git->merge_initial_commit( $commit_id, 'master' ) );
	}

	function test_local_status() {
		global $git;

		$this->_add_uncommited_changes_locally( 'local', TRUE );
		$this->assertEquals( $git->local_status(), $git->status( TRUE ) );
	}

	function test_status() {
		global $git;

		// 1.add local change
		$this->_add_uncommited_changes_locally( 'locla', TRUE );

		// 2.add remote changes and commit them(add two behind commits)
		$this->_add_uncommited_changes_remotely( 'one', TRUE );
		$this->_add_uncommited_changes_remotely( 'two', TRUE );
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
}
