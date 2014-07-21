<?php require_once 'gitium-unittestcase.php';
class Test_Gitium_Auto_Push extends Gitium_UnitTestCase {
	function test_auto_push() {
		global $git;
		$this->assertEmpty( $git->get_uncommited_changes() );
		$this->_add_uncommited_changes_locally();
		$this->assertCount( 1, $git->get_uncommited_changes() );
		gitium_auto_push();
		$this->assertEmpty( $git->get_uncommited_changes() );
	}
}
