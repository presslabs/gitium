<?php class Test_Git_Wrapper extends WP_UnitTestCase {
	private $remote_repo = null;
	private $local_file  = null;
	private $work_file   = null;
	private $work_fname  = 'work-file.txt';
	private $work_repo   = '/tmp/gitium-repo';

	private function _create_work_fresh_clone() {
		if ( $this->work_repo )
			exec( "rm -rf {$this->work_repo}" );

		// clone the repo
		exec( "git clone -q {$this->remote_repo} {$this->work_repo}" );

		// set git config data
		exec( "cd {$this->work_repo} ; git config user.email gitium@presslabs.com" );
		exec( "cd {$this->work_repo} ; git config user.name Gitium" );
		exec( "cd {$this->work_repo} ; git config push.default matching" );
	}

	private function assertmerge( $prefix = '' ) {
		global $git;

		$this->assertTrue( $git->fetch_ref(), "{$prefix}Fetch failed" );
		$this->assertTrue( $git->merge_with_accept_mine(), "{$prefix}Merge failed" );
		$this->assertTrue( $git->push(), "{$prefix}Push failed" );

		return TRUE;
	}

	function setup() {
		// create file with unique file name and with 0600 access permission
		$dir = tempnam( sys_get_temp_dir(), 'gitium-' );
		if ( file_exists( $dir ) ) unlink( $dir );
		mkdir( $dir );
		$this->remote_repo = $dir;

		$dir = tempnam( sys_get_temp_dir(), 'gitium-work-' );
		if ( file_exists( $dir ) ) unlink( $dir );
		mkdir( $dir );
		$this->work_repo = $dir;

		$this->local_file = dirname( WP_CONTENT_DIR ) . "/{$this->work_fname}";
		$this->work_file  = "{$this->work_repo}/{$this->work_fname}";

		// init git
		exec( "cd {$this->remote_repo}; git init --bare {$this->remote_repo}" );
		$this->gitium_init_process();

		$this->_create_work_fresh_clone();
	}

	function teardown() {
		if ( $this->remote_repo )
			exec( "rm -rf {$this->remote_repo}" );
		if ( $this->work_repo )
			exec( "rm -rf {$this->work_repo}" );

		exec( 'rm -rf ' . dirname( WP_CONTENT_DIR ) . '/.git' );
		exec( 'rm -rf ' . dirname( WP_CONTENT_DIR ) . '/.gitignore' );

		// remove the files of the test
		exec( "rm -rf {$this->local_file} ; rm -rf {$this->work_repo}" );
	}

	function gitium_init_process() {
		$admin = new Gitium_Admin();
		return $admin->init_process( $this->remote_repo );
	}

	function test_class_exists_git_wrapper() {
		$this->assertTrue( class_exists( 'Git_Wrapper' ) );
	}

	private function _add_uncommited_changes_locally( $change = 'local', $commit = FALSE ) {
		global $git;
		file_put_contents( "$this->local_file", $change . PHP_EOL );
		$git->add();
		if ( $commit ) $git->commit( 'Commit local file' );
	}

	private function _add_uncommited_changes_remotely( $change = 'remote', $commit = FALSE ) {
		exec( "cd {$this->work_repo} ; echo '$change' > $this->work_fname " );
		exec( "cd {$this->work_repo} ; git add $this->work_fname ; " );
		if ( $commit ) exec( "cd {$this->work_repo} ; git commit -q -m '[$change]: Commit remote file' ; git push -q" );
	}

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
		$this->_add_uncommited_changes_remotely();
		$this->_add_uncommited_changes_locally();

		// 3.test if repo has uncommited changes(TRUE expected)
		$this->assertTrue( $git->is_dirty() );

		// 4.commit all changes and test again(FALSE expected)
		exec( "cd {$this->work_repo} ; git commit -q -m 'Add remote file' ; git push -q" );
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
}
