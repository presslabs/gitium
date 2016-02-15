<?php

class Test_Gitium_Requirements extends WP_UnitTestCase {

	function test_get_status_php_version_true() {
		$req = new Gitium_Requirements();
		$this->assertEquals( true, $req->get_status() );
	}
}
