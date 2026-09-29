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

	/**
	 * A failed fetch (e.g. ssh-git blocked by SELinux) must not be treated as an empty remote
	 */
	function test_init_process_with_unreachable_remote() {
		global $git;

		$git->cleanup();
		$config = new Gitium_Submenu_Configure();
		$this->assertFalse( $config->init_process( '/nonexistent/gitium-remote.git' ) );
		$this->assertFileNotExists( dirname( WP_CONTENT_DIR ) . '/.git' );
		$this->assertNotEmpty( $git->get_last_error() );
	}

	/**
	 * A failed init_process() must keep the history of an existing repository
	 */
	function test_init_process_with_unreachable_remote_keeps_existing_history() {
		global $git;

		$git->remove_remote();
		$head = $git->get_head_commit();
		$config = new Gitium_Submenu_Configure();
		$this->assertFalse( $config->init_process( '/nonexistent/gitium-remote.git' ) );
		$this->assertEquals( $head, $git->get_head_commit() );
		$this->assertEmpty( $git->get_remote_url() );
	}
}
