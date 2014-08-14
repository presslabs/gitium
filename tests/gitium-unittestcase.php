<?php

class Gitium_UnitTestCase extends WP_UnitTestCase {
	protected $remote_repo = null;
	protected $local_file  = null;
	protected $work_file   = null;
	protected $work_fname  = 'work-file.txt';
	protected $work_repo   = '/tmp/gitium-repo';

	protected function _create_work_fresh_clone() {
		if ( $this->work_repo ) {
			exec( "rm -rf {$this->work_repo}" );
		}

		// clone the repo
		exec( "git clone -q {$this->remote_repo} {$this->work_repo}" );

		// set git config data
		exec( "cd {$this->work_repo} ; git config user.email gitium@presslabs.com" );
		exec( "cd {$this->work_repo} ; git config user.name Gitium" );
		exec( "cd {$this->work_repo} ; git config push.default matching" );
	}

	protected function assertmerge( $prefix = '' ) {
		global $git;

		$this->assertTrue( $git->fetch_ref(), "{$prefix}Fetch failed" );
		$this->assertTrue( $git->merge_with_accept_mine(), "{$prefix}Merge failed" );
		$this->assertTrue( $git->push(), "{$prefix}Push failed" );

		return true;
	}

	protected function _add_untracked_changes_locally( $change = 'local' ) {
		file_put_contents( "$this->local_file" . rand( 1, 999 ), $change . PHP_EOL );
	}

	protected function _add_changes_locally( $change = 'local', $commit = false ) {
		global $git;

		file_put_contents( "$this->local_file", $change . PHP_EOL );
		$git->add();
		if ( $commit ) {
			$git->commit( 'Commit local file' );
		}
	}

	protected function _add_changes_remotely( $change = 'remote', $commit = false ) {
		exec( "cd {$this->work_repo} ; echo '$change' > $this->work_fname " );
		exec( "cd {$this->work_repo} ; git add $this->work_fname ; " );
		if ( $commit ) {
			exec( "cd {$this->work_repo} ; git commit -q -m '[$change]: Commit remote file' ; git push -q" );
		}
	}

	protected function gitium_init_process() {
		$admin = new Gitium_Admin();
		return $admin->init_process( $this->remote_repo );
	}

	public function setup() {
		// create file with unique file name and with 0600 access permission
		$dir = tempnam( sys_get_temp_dir(), 'gitium-' );
		if ( file_exists( $dir ) ) {
			unlink( $dir );
		}
		mkdir( $dir );
		$this->remote_repo = $dir;

		$dir = tempnam( sys_get_temp_dir(), 'gitium-work-' );
		if ( file_exists( $dir ) ) {
			unlink( $dir );
		}
		mkdir( $dir );
		$this->work_repo = $dir;

		$this->local_file = dirname( WP_CONTENT_DIR ) . "/{$this->work_fname}";
		$this->work_file  = "{$this->work_repo}/{$this->work_fname}";

		// init git
		exec( "cd {$this->remote_repo}; git init --bare {$this->remote_repo}" );
		$this->gitium_init_process();

		$this->_create_work_fresh_clone();
	}

	public function teardown() {
		if ( $this->remote_repo ) {
			exec( "rm -rf {$this->remote_repo}" );
		}

		if ( $this->work_repo ) {
			exec( "rm -rf {$this->work_repo}" );
		}

		exec( 'rm -rf ' . dirname( WP_CONTENT_DIR ) . '/.git' );
		exec( 'rm -rf ' . dirname( WP_CONTENT_DIR ) . '/.gitignore' );

		// remove the files of the test
		exec( "rm -rf {$this->local_file} ; rm -rf {$this->work_repo}" );
	}

	function test_class_exists_git_wrapper() {
		$this->assertTrue( class_exists( 'Git_Wrapper' ) );
	}
}
