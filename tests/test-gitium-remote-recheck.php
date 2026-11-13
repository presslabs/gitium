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

class Test_Gitium_Remote_Recheck extends Gitium_UnitTestCase {

	private $moved_remote = '';

	public function setup() {
		parent::setup();
		delete_transient( 'gitium_remote_disconnected' );
		wp_clear_scheduled_hook( 'gitium_remote_recheck' );
	}

	public function teardown() {
		$this->_remote_up();
		delete_transient( 'gitium_remote_disconnected' );
		wp_clear_scheduled_hook( 'gitium_remote_recheck' );
		parent::teardown();
	}

	// make `git fetch origin` fail, like a Gitea outage
	private function _remote_down() {
		$this->moved_remote = $this->remote_repo . '-down';
		rename( $this->remote_repo, $this->moved_remote );
	}

	private function _remote_up() {
		if ( $this->moved_remote && file_exists( $this->moved_remote ) ) {
			rename( $this->moved_remote, $this->remote_repo );
		}
		$this->moved_remote = '';
	}

	// what WP-Cron does: drop the event, then run the hook
	private function _run_recheck_event() {
		$timestamp = wp_next_scheduled( 'gitium_remote_recheck' );
		$this->assertNotFalse( $timestamp, 'No recheck scheduled' );
		wp_unschedule_event( $timestamp, 'gitium_remote_recheck' );
		do_action( 'gitium_remote_recheck' );
	}

	function test_has_action_gitium_remote_recheck() {
		$this->assertGreaterThan( 0, has_action( 'gitium_remote_recheck', 'gitium_remote_recheck' ) );
	}

	function test_failed_fetch_schedules_recheck() {
		$this->_remote_down();
		_gitium_status( true );

		$this->assertNotEmpty( get_transient( 'gitium_remote_disconnected' ) );
		$next = wp_next_scheduled( 'gitium_remote_recheck' );
		$this->assertNotFalse( $next );
		$this->assertGreaterThan( time(), $next );
	}

	function test_successful_fetch_schedules_nothing() {
		_gitium_status( true );

		$this->assertFalse( get_transient( 'gitium_remote_disconnected' ) );
		$this->assertFalse( wp_next_scheduled( 'gitium_remote_recheck' ) );
	}

	function test_recheck_clears_notice_when_remote_is_back() {
		$this->_remote_down();
		_gitium_status( true );
		$this->assertNotEmpty( get_transient( 'gitium_remote_disconnected' ) );

		$this->_remote_up();
		$this->_run_recheck_event();

		$this->assertFalse( get_transient( 'gitium_remote_disconnected' ) );
		$this->assertFalse( wp_next_scheduled( 'gitium_remote_recheck' ), 'Recheck kept running after recovery' );
	}

	function test_recheck_keeps_notice_and_reschedules_while_down() {
		$this->_remote_down();
		_gitium_status( true );

		$this->_run_recheck_event();

		$this->assertNotEmpty( get_transient( 'gitium_remote_disconnected' ) );
		$this->assertNotFalse( wp_next_scheduled( 'gitium_remote_recheck' ) );
	}

	function test_recheck_without_notice_does_nothing() {
		do_action( 'gitium_remote_recheck' );

		$this->assertFalse( get_transient( 'gitium_remote_disconnected' ) );
		$this->assertFalse( wp_next_scheduled( 'gitium_remote_recheck' ) );
	}

	// a notice stored by an older Gitium, with no recheck scheduled yet
	function test_notice_schedules_recheck_for_old_notice() {
		set_transient( 'gitium_remote_disconnected', 'fatal: Could not read from remote repository.' );
		$this->assertFalse( wp_next_scheduled( 'gitium_remote_recheck' ) );

		ob_start();
		gitium_remote_disconnected_notice();
		ob_end_clean();

		$this->assertNotFalse( wp_next_scheduled( 'gitium_remote_recheck' ) );
	}

	function test_notice_without_error_schedules_nothing() {
		ob_start();
		gitium_remote_disconnected_notice();
		ob_end_clean();

		$this->assertFalse( wp_next_scheduled( 'gitium_remote_recheck' ) );
	}

	function test_deactivation_clears_recheck() {
		gitium_schedule_remote_recheck();
		$this->assertNotFalse( wp_next_scheduled( 'gitium_remote_recheck' ) );

		gitium_deactivation();

		$this->assertFalse( wp_next_scheduled( 'gitium_remote_recheck' ) );
	}
}
