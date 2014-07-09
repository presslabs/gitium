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

	private function _add_uncommited_changes() {
		global $git;

		// 1.add changes remotely
		exec( "cd {$this->work_repo} ; echo 'remote' > $this->work_fname " );
		exec( "cd {$this->work_repo} ; git add $this->work_fname ; " );

		// 2.add chages locally
		file_put_contents( "$this->local_file", 'local' . PHP_EOL );
		$git->add();
	}

	/**
	 * Test is_dirty()
	 *
	 * 1.test if repo has uncommited changes(FALSE expected)
	 * 2.add uncommited changes
	 * 3.test if repo has uncommited changes(TRUE expected)
	 * 4.commit all changes and test again(FALSE expected)
	 */
	function test_is_dirty() {
		global $git;

		// 1.test if repo has uncommited changes(FALSE expected)
		$this->assertFalse( $git->is_dirty() );

		// 2.add uncommited changes
		$this->_add_uncommited_changes();

		// 3.test if repo has uncommited changes(TRUE expected)
		$this->assertTrue( $git->is_dirty() );

		// 4.commit all changes and test again(FALSE expected)
		exec( "cd {$this->work_repo} ; git commit -q -m 'Add remote file' ; git push -q" );
		$git->commit( 'Add local file' );
		$this->assertFalse( $git->is_dirty() );
	}
}
