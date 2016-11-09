<?php

class Test_Functions extends WP_UnitTestCase
{
	function setup() {
		set_transient( 'gitium_remote_tracking_branch', 'some_branch' );
		set_transient( 'gitium_is_status_working', true );
	}

	function teardown() {
	}

	function test_gitium_get_remote_tracking_branch() {
		$this->assertEquals( 'some_branch', _gitium_get_remote_tracking_branch() );
	}

	function test_gitium_get_remote_tracking_branch_true() {
		$this->assertEquals( '', _gitium_get_remote_tracking_branch(True) );
	}

	function test_gitium_update_remote_tracking_branch() {
		$this->assertEquals( '', gitium_update_remote_tracking_branch() );
	}

	function test_gitium_is_status_working() {
		$this->assertTrue( _gitium_is_status_working() );
	}

	function test_gitium_is_status_working_true() {
		$this->assertFalse( _gitium_is_status_working(True) );
	}

	function test_gitium_update_is_status_working() {
		$this->assertFalse( gitium_update_is_status_working() );
	}
}
