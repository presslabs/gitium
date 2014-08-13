<?php

require_once 'gitium-unittestcase.php';

class Test_Gitium_Auto_Push extends Gitium_UnitTestCase {
	function test_auto_push() {
		global $git;
		$this->assertEmpty( $git->get_uncommited_changes() );
		$this->_add_changes_locally();
		$this->assertCount( 1, $git->get_uncommited_changes() );
		gitium_auto_push();
		$this->assertEmpty( $git->get_uncommited_changes() );
	}

	function test_gitium_status() {
		global $git;

		// 1.add local change
		$this->_add_changes_locally( 'local', true );

		// 2.add remote changes and commit them(add two behind commits)
		$this->_add_changes_remotely( 'one', true );
		$this->_add_changes_remotely( 'two', true );
		$git->fetch_ref();

		// 3.test if the changes are visible in status call
		$status = _gitium_status();
		$this->assertStringEndsWith( '[ahead 1, behind 2]', $status[0] );
	}
}
