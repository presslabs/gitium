<?php
require_once 'gitium-unittestcase.php';

class Test_Gitium_Init_Process extends Gitium_UnitTestCase {
	function test_repo_dir() {
		global $git;
		$this->assertEquals( $git->repo_dir, dirname( WP_CONTENT_DIR ) );
	}

	function gitium_init_process() {
		$config = new Gitium_Submenu_Configure();
		return $config->init_process( $this->remote_repo );
	}

	function test_init_process() {
		$this->assertTrue( $this->gitium_init_process() );
	}
}
