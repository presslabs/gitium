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

class Test_Gitium_Index_Lock_Cron extends Gitium_UnitTestCase {

	private $index_lock = '';

	public function setup() {
		parent::setup();
		$this->index_lock = dirname( WP_CONTENT_DIR ) . '/.git/index.lock';
	}

	// an index.lock like the one a killed git process leaves behind, $age seconds old
	private function _put_index_lock( $age ) {
		touch( $this->index_lock, time() - $age );
	}

	function test_cron_is_scheduled() {
		wp_clear_scheduled_hook( 'gitium_remove_stale_index_lock' );

		gitium_schedule_remove_stale_index_lock();
		$this->assertNotFalse( wp_next_scheduled( 'gitium_remove_stale_index_lock' ) );
		$this->assertEquals( 'gitium_ten_minutes', wp_get_schedule( 'gitium_remove_stale_index_lock' ) );
		$this->assertEquals( 600, wp_get_schedules()['gitium_ten_minutes']['interval'] );

		gitium_deactivation();
		$this->assertFalse( wp_next_scheduled( 'gitium_remove_stale_index_lock' ) );
	}

	function test_cron_removes_stale_index_lock() {
		$this->_put_index_lock( 900 );
		do_action( 'gitium_remove_stale_index_lock' );
		$this->assertFalse( file_exists( $this->index_lock ) );

		$this->_put_index_lock( 10 );
		do_action( 'gitium_remove_stale_index_lock' );
		$this->assertTrue( file_exists( $this->index_lock ) );
		unlink( $this->index_lock );
	}
}
