<?php class Test_Gitium_Merge_Conflicts extends WP_UnitTestCase {
	private $remote_repo   = null;
	private $local_file    = null;
	private $remote_file   = null;
	private $file_name     = 'me';
	private $repo_temp_dir = '/tmp/gitium-repo';

	function setup() {
		// create file with unique file name and with 0600 access permission
		$repo = tempnam( sys_get_temp_dir(), 'gitium-' );
		if ( file_exists( $repo ) ) unlink( $repo );
		mkdir( $repo );
		$this->remote_repo = $repo;
		$this->local_file  = dirname( WP_CONTENT_DIR ) . "/{$this->file_name}";
		$this->remote_file = "{$this->repo_temp_dir}/{$this->file_name}";

		// init git
		exec( "cd $repo; git init --bare $repo" );
		$this->gitium_init_process();

		// clone the repo
		exec( "git clone -q {$this->remote_repo} {$this->repo_temp_dir}" );

		// set git config data
		exec( "cd {$this->repo_temp_dir} ; git config user.email gitium@presslabs.com" );
		exec( "cd {$this->repo_temp_dir} ; git config user.name Gitium" );
		exec( "cd {$this->repo_temp_dir} ; git config push.default matching" );
	}

	function teardown() {
		if ( $this->remote_repo )
			exec( "rm -rf {$this->remote_repo}" );
		exec( 'rm -rf ' . dirname( WP_CONTENT_DIR ) . '/.git' );
		exec( 'rm -rf ' . dirname( WP_CONTENT_DIR ) . '/.gitignore' );

		// remove the files of the test
		exec( "rm -rf {$this->local_file} ; rm -rf {$this->repo_temp_dir}" );
	}

	function gitium_init_process() {
		$admin = new Gitium_Admin();
		return $admin->init_process( $this->remote_repo );
	}

	function test_init_process() {
		$this->assertTrue( $this->gitium_init_process() );
	}

	function test_check_merge_conflicts_aa() { // AA unmerged, both added
		global $git;
		$this->gitium_init_process(); // init the git repo

		$file_name  = 'me';
		$local_file = dirname( WP_CONTENT_DIR ) . "/$file_name";

		// add & commit (remote)
		$gitium_repo_temp_dir = '/tmp/gitium-repo';
		exec( "git clone -q $this->remote_repo $gitium_repo_temp_dir" );
		exec( "cd $gitium_repo_temp_dir ; echo 'remote' > $file_name " );
		exec( "cd $gitium_repo_temp_dir ; git config user.email gitium@presslabs.com" );
		exec( "cd $gitium_repo_temp_dir ; git config user.name Gitium" );
		exec( "cd $gitium_repo_temp_dir ; git config push.default matching" );
		exec( "cd $gitium_repo_temp_dir ; git add $file_name ; git commit -q -m 'remote file' ; git push -q" );

		// add & commit (local)
		file_put_contents( "$local_file", 'local' );
		$git->add();
		$git->commit( 'Add local file' );

		// test merge with accept mine conflicts
		$this->assertTrue( $git->merge_with_accept_mine() );

		// check if the result is what it's supposed to be from merge with accept mine process
		$this->assertEquals( file_get_contents( $local_file ), 'local' );

		exec( "rm -rf $local_file ; rm -rf $gitium_repo_temp_dir" );
	}
}
