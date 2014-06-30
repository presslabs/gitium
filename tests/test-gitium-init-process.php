<?php class Test_Gitium_Init_Process extends WP_UnitTestCase {
	private $remote_repo = null;

	function setup() {
		// create file with unique file name and with 0600 access permission
		$repo = tempnam( sys_get_temp_dir(), 'gitium-' );
		$this->remote_repo = $repo;

		if ( file_exists( $repo ) ) unlink( $repo );
		mkdir( $repo );

		exec( "cd $repo; git init --bare $repo" );
	}

	function teardown() {
		if ( $this->remote_repo )
			exec( "rm -rf {$this->remote_repo}" );
		exec( 'rm -rf ' . dirname( WP_CONTENT_DIR ) . '/.git' );
		exec( 'rm -rf ' . dirname( WP_CONTENT_DIR ) . '/.gitignore' );
	}

	function test_repo_dir() {
		global $git;
		$this->assertEquals( $git->repo_dir, dirname( WP_CONTENT_DIR ) );
	}

	function gitium_init_process() {
		$admin = new Gitium_Admin();
		return $admin->init_process( $this->remote_repo );
	}

	function test_init_process() {
		$this->assertTrue( $this->gitium_init_process() );
	}
}
