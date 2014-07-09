<?php class Test_Gitium_Merge_Conflicts extends WP_UnitTestCase {
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

	private function gitium_init_process() {
		$admin = new Gitium_Admin();
		return $admin->init_process( $this->remote_repo );
	}

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
