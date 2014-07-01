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

	/**
	 * Set merge conflict: AA -> unmerged, both added
	 *
	 * 1.create one file remotely with the content `remote` (add & commit)
	 * 2.create the same file locally with the content `local` (add & commit)
	 */
	function set_merge_conflict_aa() {
		global $git;

		// 1.add & commit (remote)
		exec( "cd {$this->repo_temp_dir} ; echo 'remote' > $this->file_name " );
		exec( "cd {$this->repo_temp_dir} ; git add $this->file_name ; git commit -q -m 'remote file' ; git push -q" );

		// 2.add & commit (local)
		file_put_contents( "$this->local_file", 'local' );
		$git->add();
		$git->commit( 'Add local file' );
	}

	/**
	 * Test merge conflict: AA -> unmerged, both added
	 *
	 * 1.set merge conflict AA (unmerged, both added)
	 * 2.test `merge with accept mine` conflicts
	 * 3.check if the content `local` is the final text after the `merge with accept mine` process
	 */
	function test_merge_conflict_aa() {
		global $git;

		// 1.set merge conflict AA (unmerged, both added)
		$this->set_merge_conflict_aa();

		// 2.test merge with accept mine conflicts
		$this->assertTrue( $git->merge_with_accept_mine() );

		// 3.check if the result is what it's supposed to be from merge with accept mine process
		$this->assertEquals( file_get_contents( $this->local_file ), 'local' );
	}

	/**
	 * Set merge conflict: UU -> unmerged, both modified
	 *
	 * 1.set merge conflict AA
	 * 2.merge with accept mine
	 * 3.modify the remote file with the content `remote:uu` (edit,add & commit)
	 * 4.modify the same file locally with the content `local:uu` (edit,add & commit)
	 */
	function set_merge_conflict_uu() {
		global $git;

		// 1.set merge conflict AA (unmerged, both added)
		$this->set_merge_conflict_aa();

		// 2.merge with accept mine
		$git->merge_with_accept_mine();

		// 3.modify the remote file with the content `remote:uu` (edit,add & commit)
		file_put_contents( "$this->remote_file", 'remote:uu' );
		$git->add();
		$git->commit( 'Change remote file' );

		// 4.modify the same file locally with the content `local:uu` (edit,add & commit)
		file_put_contents( "$this->local_file", 'local:uu' );
		$git->add();
		$git->commit( 'Change local file' );
	}

	/**
	 * Test merge conflict: UU -> unmerged, both modified
	 *
	 * 1.set merge conflict UU (unmerged, both modified)
	 * 2.test `merge with accept mine` conflicts
	 * 3.check if the content `local:uu` is the final text after the `merge with accept mine` process
	 */
	function test_merge_conflict_uu() {
		global $git;

		// 1.set merge conflict UU (unmerged, both modified)
		$this->set_merge_conflict_uu();

		// 2.test `merge with accept mine` conflicts
		$this->assertTrue( $git->merge_with_accept_mine() );

		// 3.check if the content `local:uu` is the final text after the `merge with accept mine` process
		$this->assertEquals( file_get_contents( $this->local_file ), 'local:uu' );
	}

	/**
	 * Set merge conflict: AU -> unmerged, added by us
	 *
	 * 1.create one file remotely with the content `remote` (add & commit)
	 * 2.merge with accept mine
	 * 3.change the remote file content with `remote:au` (edit,add & commit)
	 * 4.create the same file locally with the content `local:au` (add & commit)
	 */
	function set_merge_conflict_au() {
		global $git;

		// 1.create one file remotely with the content `remote` (add & commit)
		exec( "cd {$this->repo_temp_dir} ; echo 'remote' > $this->file_name " );
		exec( "cd {$this->repo_temp_dir} ; git add $this->file_name ; git commit -q -m 'Add remote file' ; git push -q" );

		// 2.merge with accept mine
		$git->merge_with_accept_mine();

		// 3.change the remote file content with `remote:au` (edit,add & commit)
		file_put_contents( "$this->remote_file", 'remote:au' );
		$git->add();
		$git->commit( 'Change remote file' );

		// 4.create the same file locally with the content `local:au` (edit,add & commit)
		file_put_contents( "$this->local_file", 'local:au' );
		$git->add();
		$git->commit( 'Change local file' );
	}

	/**
	 * Test merge conflict: AU -> unmerged, added by us
	 *
	 * 1.set merge conflict AU (unmerged, added by us)
	 * 2.test `merge with accept mine` conflicts
	 * 3.check if the content `local:au` is the final text after the `merge with accept mine` process
	 */
	function test_merge_conflict_au() {
		global $git;

		// 1.set merge conflict AU (unmerged, added by us)
		$this->set_merge_conflict_au();

		// 2.test `merge with accept mine` conflicts
		$this->assertTrue( $git->merge_with_accept_mine() );

		// 3.check if the content `local:au` is the final text after the `merge with accept mine` process
		$this->assertEquals( file_get_contents( $this->local_file ), 'local:au' );
	}

	/**
	 * Set merge conflict: UA -> unmerged, added by them
	 *
	 * 1.create one file locally with the content `local` (add & commit)
	 * 2.merge with accept mine
	 * 3.change the local file content with `local:ua` (edit,add & commit)
	 * 4.add the same file remotely with the content `remote:ua` (add & commit)
	 */
	function set_merge_conflict_ua() {
		global $git;

		// 1.create one file locally with the content `local` (add & commit)
		file_put_contents( "$this->local_file", 'local' );
		$git->add();
		$git->commit( 'Create local file' );

		// 2.test `merge with accept mine` conflicts
		$this->assertTrue( $git->merge_with_accept_mine() );

		// 3.change the local file content with `local:ua` (edit,add & commit)
		file_put_contents( "$this->local_file", 'local:ua' );
		$git->add();
		$git->commit( 'Change local file' );

		// 4.add the same file remotely with the content `remote:ua` (add & commit)
		exec( "cd {$this->repo_temp_dir} ; echo 'remote:ua' > $this->file_name " );
		exec( "cd {$this->repo_temp_dir} ; git add $this->file_name ; git commit -q -m 'Add remote file' ; git push -q" );
	}

	/**
	 * Test merge conflict: UA -> unmerged, added by them
	 *
	 * 1.set merge conflict UA (unmerged, added by them)
	 * 2.test `merge with accept mine` conflicts
	 * 3.check if the content `local:ua` is the final text after the `merge with accept mine` process
	 */
	function test_merge_conflict_ua() {
		global $git;

		// 1.set merge conflict UA (unmerged, added by them)
		$this->set_merge_conflict_ua();

		// 2.test `merge with accept mine` conflicts
		$this->assertTrue( $git->merge_with_accept_mine() );

		// 3.check if the content `local:ua` is the final text after the `merge with accept mine` process
		$this->assertEquals( file_get_contents( $this->local_file ), 'local:ua' );
	}
}
