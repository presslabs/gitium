<?php class Test_Git_Wrapper extends WP_UnitTestCase {
	function test_class_exists_git_wrapper() {
		$this->assertTrue( class_exists( 'Git_Wrapper' ) );
	}
}
