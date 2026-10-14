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
		$this->assertEquals( '', _gitium_get_remote_tracking_branch(true) );
	}

	function test_gitium_update_remote_tracking_branch() {
		$this->assertEquals( '', gitium_update_remote_tracking_branch() );
	}

	function test_gitium_is_status_working() {
		$this->assertTrue( _gitium_is_status_working() );
	}

	function test_gitium_is_status_working_true() {
		$this->assertFalse( _gitium_is_status_working(true) );
	}

	function test_gitium_update_is_status_working() {
		$this->assertFalse( gitium_update_is_status_working() );
	}

	function test_gitium_maintenance_mode_can_be_enabled_and_disabled() {
		$file = ABSPATH . '/.maintenance';
		if ( file_exists( $file ) ) {
			unlink( $file );
		}

		$this->assertTrue( gitium_enable_maintenance_mode() );
		$this->assertFileExists( $file );
		$this->assertRegExp( '/^\<\?php \$upgrading = \d+;$/', trim( file_get_contents( $file ) ) );
		$this->assertTrue( gitium_disable_maintenance_mode() );
		$this->assertFileNotExists( $file );
	}

	function test_gitium_get_versions_returns_cached_versions() {
		$versions = array(
			'themes' => array(
				'example' => array(
					'name'    => 'Example',
					'version' => '1.0',
					'msg'     => '`Example` version 1.0',
				),
			),
		);
		set_transient( 'gitium_versions', $versions );

		$this->assertEquals( $versions, gitium_get_versions() );

		delete_transient( 'gitium_versions' );
	}

	function test_gitium_update_versions_includes_installed_gitium_plugin() {
		$versions = gitium_update_versions();

		$this->assertArrayHasKey( 'plugins', $versions );
		$this->assertArrayHasKey( 'gitium/gitium.php', $versions['plugins'] );
		$this->assertEquals( 'Gitium', $versions['plugins']['gitium/gitium.php']['name'] );
		$this->assertEquals( '1.2.5', $versions['plugins']['gitium/gitium.php']['version'] );
		$this->assertEquals( '`Gitium` version 1.2.5', $versions['plugins']['gitium/gitium.php']['msg'] );
		$this->assertEquals( $versions, get_transient( 'gitium_versions' ) );
	}

	function test_gitium_merge_lock_can_be_acquired_and_released() {
		$lock_path = tempnam( sys_get_temp_dir(), 'gitium-lock-' );
		unlink( $lock_path );
		add_filter( 'gitium_lock_path', function() use ( $lock_path ) {
			return $lock_path;
		} );

		$lock = gitium_acquire_merge_lock();
		$this->assertInternalType( 'array', $lock );
		$this->assertEquals( $lock_path, $lock[0] );
		$this->assertTrue( is_resource( $lock[1] ) );

		gitium_release_merge_lock( $lock );
		$this->assertFileExists( $lock_path );

		remove_all_filters( 'gitium_lock_path' );
		unlink( $lock_path );
	}

	function test_gitium_format_message() {
		$this->assertEquals( '`Plugin Name`', _gitium_format_message( 'Plugin Name' ) );
		$this->assertEquals( '`Plugin Name` version 1.2.3', _gitium_format_message( 'Plugin Name', '1.2.3' ) );
		$this->assertEquals( 'Updated `Plugin Name` version 1.2.3', _gitium_format_message( 'Plugin Name', '1.2.3', 'Updated' ) );
	}

	function test_gitium_ssh_encode_buffer() {
		$buffer = "\x01\x02\x03";
		$encoded = _gitium_ssh_encode_buffer( $buffer );

		$this->assertEquals( pack( 'Na*', 3, $buffer ), $encoded );

		$buffer = "\x80\x01";
		$encoded = _gitium_ssh_encode_buffer( $buffer );
		$this->assertEquals( pack( 'Na*', 3, "\x00" . $buffer ), $encoded );
	}

	function test_gitium_get_webhook_key_persists_and_regenerates() {
		delete_option( 'gitium_webhook_key' );

		$key = gitium_get_webhook_key();
		$this->assertRegExp( '/^[a-f0-9]{32}$/', $key );
		$this->assertEquals( $key, gitium_get_webhook_key() );

		$new_key = gitium_get_webhook_key( true );
		$this->assertRegExp( '/^[a-f0-9]{32}$/', $new_key );
		$this->assertNotEquals( $key, $new_key );
		$this->assertEquals( $new_key, gitium_get_webhook_key() );

		delete_option( 'gitium_webhook_key' );
	}

	function test_gitium_get_webhook_contains_persisted_key() {
		delete_option( 'gitium_webhook_key' );
		$key = gitium_get_webhook_key();
		$webhook = gitium_get_webhook();

		$this->assertContains( 'gitium-webhook.php', $webhook );
		$this->assertContains( 'key=' . $key, $webhook );

		delete_option( 'gitium_webhook_key' );
	}
}
