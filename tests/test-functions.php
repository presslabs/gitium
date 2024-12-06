<?php
/**
 * Gitium provides automatic git version control and deployment for
 * your plugins and themes integrated into wp-admin.
 *
 * Copyright (C) 2014-2025 PRESSINFRA SRL <ping@presslabs.com>
 *
 * Gitium is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Gitium is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Gitium. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package         Gitium
 */

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
