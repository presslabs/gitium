<?php
/**
 * Gitium provides automatic git version control and deployment for
 * your plugins and themes integrated into wp-admin.
 *
 * Copyright (C) 2014-2017 Presslabs SRL <ping@presslabs.com>
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
