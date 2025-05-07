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

require_once("repl.php");

class Test_Gitium extends WP_UnitTestCase {
	private $test_gitium_is_activated = false;
	private $plugin = 'gitium/gitium.php';
	var $user_id;
	var $factory;

	function setup() {
		$this->factory = new WP_UnitTest_Factory();
		$this->test_gitium_is_activated = is_plugin_active( $this->plugin );

		set_transient(
			'gitium_versions',
			array(
				'plugins' => array(
					'autover/autover.php'             => array( 'name' => 'AutoVer', 'version' => '1.2.3' ),
					'gitium/gitium.php'               => array( 'name' => 'Gitium', 'version' => '1.0' ),
					'gitium-pltest/gitium-pltest.php' => array( 'name' => 'Gitium PL Test', 'version' => '2.1' ),
					'struto-camila/camila.php'        => array( 'name' => 'Camila', 'version' => '1.0.1' ),
					'struto-camila/strutul.php'       => array( 'name' => 'Strutul', 'version' => '3.2.1' ),
					'simple.php'                      => array( 'name' => 'Simple', 'version' => '9.0' )
				),
				'themes' => array(
					'hahaha' => array( 'name' => 'Ha ha ha hi', 'version' => '0.0.1' )
				)
			)
		);
	}

	function test_gitium_deactivation() {
		set_transient( 'gitium_git_version', '1.7.9');
		gitium_deactivation();
		$this->assertFalse( get_transient( 'gitium_git_version' ) );
	}

	function test_gitium_uninstall_hook() {
		$deleted_options = array(
			'gitium_keypair',
			'gitium_webhook_key'
		);
		foreach ( $deleted_options as $option ) {
			add_option( $option, true );
		}

		$deleted_transients = array(
			'gitium_remote_tracking_branch',
			'gitium_remote_disconnected',
			'gitium_uncommited_changes',
			'gitium_git_version',
			'gitium_versions',
			'gitium_menu_bubble',
			'gitium_is_status_working',
		);
		foreach ( $deleted_transients as $transient ) {
			set_transient( $transient, true );
		}

		gitium_uninstall_hook();

		foreach ( $deleted_options as $option ) {
			$this->assertFalse( get_option( $option ) );
		}
		foreach ( $deleted_transients as $transient ) {
			$this->assertFalse( get_transient( $transient ) );
		}
	}

	function test_gitium_is_activated() {
		$this->assertTrue( is_plugin_active( $this->plugin ) );
	}

	function test_has_action_gitium_update_versions() {
		$this->assertGreaterThan( 0, has_action( 'load-plugins.php', 'gitium_update_versions', 999 ) );
	}

	function test_has_filter_upgrader_post_install() {
		$this->assertGreaterThan( 0, has_filter( 'upgrader_post_install', 'gitium_upgrader_post_install' ) );
	}

	function test_has_action_upgrader_process_complete() {
		$this->assertGreaterThan( 0, has_action( 'upgrader_process_complete', 'gitium_auto_push' ) );
	}

	function test_has_action_activated_plugin_git_check_after_activate_modifications() {
		$this->assertGreaterThan( 0, has_action( 'activated_plugin','gitium_check_after_activate_modifications' ) );
	}

	function test_has_action_deactivated_plugin_git_check_after_deactivate_modifications() {
		$this->assertGreaterThan( 0, has_action( 'deactivated_plugin','gitium_check_after_deactivate_modifications' ) );
	}

	function test_has_action_git_check_for_plugin_deletions() {
		$this->assertGreaterThan( 0, has_action( 'load-plugins.php', 'gitium_check_for_plugin_deletions' ) );
	}

	function test_has_action_git_check_for_themes_deletions() {
		$this->assertGreaterThan( 0, has_action( 'load-themes.php','gitium_check_for_themes_deletions' ) );
	}

	function test_has_action_gitium_hook_plugin_and_theme_editor_page() {
		if ( version_compare( $GLOBALS['wp_version'], '4.9', '>=' ) )
			$this->assertGreaterThan( 0, has_action( 'wp_ajax_edit-theme-plugin-file','add_filter_for_ajax_save' ) );
		else
			$this->assertGreaterThan(0, has_action('admin_enqueue_scripts', 'gitium_hook_plugin_and_theme_editor_page'));
	}

	function test_has_action_gitium_remote_disconnected_notice() {
		$this->assertGreaterThan( 0, has_action( 'admin_notices','gitium_remote_disconnected_notice' ) );
	}

	function test_gitium_module_by_path_for_void_path() {
		$path     = '';
		$result   = _gitium_module_by_path( $path );
		$expected = array(
			'base_path' => '',
			'type'      => 'file',
			'name'      => basename( $path ),
			'version'   => null,
		);
		$assert = $expected == $result;
		$this->assertTrue( $assert, print_r( compact( 'path', 'expected', 'result' ), true ) );
	}

	function test_gitium_module_by_path_for_normal_plugin() {
		$path     = 'wp-content/plugins/autover/autover.php';
		$result   = _gitium_module_by_path( $path );
		$expected = array(
			'base_path' => 'wp-content/plugins/autover',
			'type'      => 'plugin',
			'name'      => 'AutoVer',
			'version'   => '1.2.3',
		);
		$assert = $expected == $result;
		$this->assertTrue( $assert, print_r( compact( 'path', 'expected', 'result' ), true ) );
	}

	function test_gitium_module_by_path_for_one_file_plugin() {
		$path     = 'wp-content/plugins/simple.php';
		$result   = _gitium_module_by_path( $path );
		$expected = array(
			'base_path' => 'wp-content/plugins/simple.php',
			'type'      => 'plugin',
			'name'      => 'Simple',
			'version'   => '9.0',
		);
		$assert = $expected == $result;
		$this->assertTrue( $assert, print_r( compact( 'path', 'expected', 'result' ), true ) );
	}

	function test_gitium_module_by_path_for_normal_theme() {
		$path     = 'wp-content/themes/hahaha/style.css';
		$result   = _gitium_module_by_path( $path );
		$expected = array(
			'base_path' => 'wp-content/themes/hahaha',
			'type'      => 'theme',
			'name'      => 'Ha ha ha hi',
			'version'   => '0.0.1',
		);
		$assert = $expected == $result;
		$this->assertTrue( $assert, print_r( compact( 'path', 'expected', 'result' ), true ) );
	}

	function test_gitium_module_by_path_for_plugin_with_dir_inside() {
		set_transient(
			'gitium_versions',
			array(
				'themes'  => array(
					'hahaha' => array( 'name' => 'Ha ha ha hi', 'version' => '0.0.1' )
				),
				'plugins' => array(
					'theme-check/theme-check.php' => array( 'name' => 'Theme Check', 'version' => '20160523.1' ),
					'gitium/gitium.php'   => array( 'name' => 'Gitium', 'version' => '1.0' )
				)
			)
		);

		$path     = 'wp-content/plugins/theme-check/assets/simple-file.txt';
		$result   = _gitium_module_by_path( $path );
		$expected = array(
			'base_path' => 'wp-content/plugins/theme-check',
			'type'      => 'plugin',
			'name'      => 'Theme Check',
			'version'   => '20160523.1',
		);
		$assert = $expected == $result;
		$this->assertTrue( $assert, print_r( compact( 'path', 'expected', 'result' ), true ) );
	}

	function test_gitium_module_by_path_for_image_file_theme() {
		$path     = 'wp-content/themes/hahaha/img/logo.png';
		$result   = _gitium_module_by_path( $path );
		$expected = array(
			'base_path' => 'wp-content/themes/hahaha',
			'type'      => 'theme',
			'name'      => 'Ha ha ha hi',
			'version'   => '0.0.1',
		);
		$assert = $expected == $result;
		$this->assertTrue( $assert, print_r( compact( 'path', 'expected', 'result' ), true ) );
	}

	function test_gitium_module_by_path_for_broken_or_unregistered_theme() {
		$path     = 'wp-content/themes/mobile_pack_red/style.css.nokia.css';
		$result   = _gitium_module_by_path( $path );
		$expected = array(
			'base_path' => 'wp-content/themes/mobile_pack_red',
			'type'      => 'theme',
			'name'      => basename( $path ),
			'version'   => null,  # this theme is not in the themes transient, so we can't determine version
		);
		$assert = $expected == $result;
		$this->assertTrue( $assert, print_r( compact( 'path', 'expected', 'result' ), true ) );
	}

	/**
	 * The struto-camila plugin represents in fact two plugins stored into the same directory
	 */
	function test_gitium_module_by_path_for_struto_camila_plugin() {
		$path     = 'wp-content/plugins/struto-camila/readme.txt';
		$result   = _gitium_module_by_path( $path );
		$expected = array(
			'base_path' => 'wp-content/plugins/struto-camila',
			'type'      => 'plugin',
			'name'      => 'Camila',
			'version'   => '1.0.1',
		);
		$assert = $expected == $result;
		$this->assertTrue( $assert, print_r( compact( 'path', 'expected', 'result' ), true ) );
	}

	function test_gitium_module_by_path_with_whitespaces_in_theme() {
		$path     = 'wp-content/themes/hahaha/white space.css';
		$result   = _gitium_module_by_path( $path );
		$expected = array(
			'base_path' => 'wp-content/themes/hahaha',
			'type'      => 'theme',
			'name'      => 'Ha ha ha hi',
			'version'   => '0.0.1',
		);
		$assert = $expected == $result;
		$this->assertTrue( $assert, print_r( compact( 'path', 'expected', 'result' ), true ) );
	}

	/**
	 * There are two similarly named plugins:
	 * gitium/gitium.php
	 * gitium-pltest/gitium-pltest.php
	 * This test is to assure that we catch the changes from the second plugin 'gitium-pltest'
	 */
	function test_gitium_module_by_path_for_similarly_named_plugins() {
		$path     = 'wp-content/plugins/gitium-pltest/readme.txt';
		$result   = _gitium_module_by_path( $path );
		$expected = array(
			'base_path' => 'wp-content/plugins/gitium-pltest',
			'type'      => 'plugin',
			'name'      => 'Gitium PL Test',
			'version'   => '2.1',
		);
		$assert = $expected == $result;
		$this->assertTrue( $assert, print_r( compact( 'path', 'expected', 'result' ), true ) );
	}

	function test_gitium_module_by_path_with_no_plugins() {
		set_transient(
			'gitium_versions',
			array(
				'themes'  => array(
					'hahaha' => array( 'name' => 'Ha ha ha hi', 'version' => '0.0.1' )
				)
			)
		);

		$path     = 'wp-content/plugins/simple-file-no-plugin.php';
		$result   = _gitium_module_by_path( $path );
		$expected = array(
			'base_path' => 'wp-content/plugins/simple-file-no-plugin.php',
			'type'      => 'file',
			'name'      => 'simple-file-no-plugin.php',
			'version'   => null,
		);
		$assert = $expected == $result;
		$this->assertTrue( $assert, print_r( compact( 'path', 'expected', 'result' ), true ) );
	}

	function test_gitium_module_by_path_with_no_themes() {
		set_transient(
			'gitium_versions',
			array(
				'plugins' => array(
					'autover/autover.php' => array( 'name' => 'AutoVer', 'version' => '1.2.3' ),
					'gitium/gitium.php'   => array( 'name' => 'Gitium', 'version' => '1.0' )
				)
			)
		);

		$path     = 'wp-content/themes/simple-file-no-theme.php';
		$result   = _gitium_module_by_path( $path );
		$expected = array(
			'base_path' => 'wp-content/themes/simple-file-no-theme.php',
			'type'      => 'file',
			'name'      => 'simple-file-no-theme.php',
			'version'   => null,
		);
		$assert = $expected == $result;
		$this->assertTrue( $assert, print_r( compact( 'path', 'expected', 'result' ), true ) );
	}

	function test_gitium_module_by_path_with_no_themes_and_no_plugins() {
		set_transient( 'gitium_versions', array() );

		$path     = 'wp-content/simple-file-no-theme-and-no-plugin.php';
		$result   = _gitium_module_by_path( $path );
		$expected = array(
			'base_path' => 'wp-content/simple-file-no-theme-and-no-plugin.php',
			'type'      => 'file',
			'name'      => 'simple-file-no-theme-and-no-plugin.php',
			'version'   => null,
		);
		$assert = $expected == $result;
		$this->assertTrue( $assert, print_r( compact( 'path', 'expected', 'result' ), true ) );
	}

	/*	'??' => 'untracked',
		'rM' => 'modified to remote',
		'rA' => 'added to remote',
		'rD' => 'deleted from remote',
		'D'  => 'deleted from work tree',
		'M'  => 'updated in work tree',
		'A'  => 'added to work tree',
		'AM' => 'added to work tree',
		'R'  => 'deleted from work tree',
	 */
	function test_gitium_humanized_change_default_case() {
		$admin = new Gitium_Submenu_Status();
		$this->assertEquals( 'test', $admin->humanized_change( 'test' ) );
	}

	function test_gitium_humanized_change_void_case() {
		$admin = new Gitium_Submenu_Status();
		$this->assertEquals( '', $admin->humanized_change( '' ) );
	}

	function test_gitium_humanized_change_null_case() {
		$admin = new Gitium_Submenu_Status();
		$this->assertEquals( null, $admin->humanized_change( null ) );
	}

	function test_gitium_humanized_change_wonder_case() {
		$admin = new Gitium_Submenu_Status();
		$this->assertEquals( 'untracked', $admin->humanized_change( '??' ) );
	}

	function test_gitium_humanized_change_rM_case() {
		$admin = new Gitium_Submenu_Status();
		$this->assertEquals( 'modified on remote', $admin->humanized_change( 'rM' ) );
	}

	function test_gitium_humanized_change_rA_case() {
		$admin = new Gitium_Submenu_Status();
		$this->assertEquals( 'added to remote', $admin->humanized_change( 'rA' ) );
	}

	function test_gitium_humanized_change_rD_case() {
		$admin = new Gitium_Submenu_Status();
		$this->assertEquals( 'deleted from remote', $admin->humanized_change( 'rD' ) );
	}

	function test_gitium_humanized_change_D_case() {
		$admin = new Gitium_Submenu_Status();
		$this->assertEquals( 'deleted from work tree', $admin->humanized_change( 'D' ) );
	}

	function test_gitium_humanized_change_M_case() {
		$admin = new Gitium_Submenu_Status();
		$this->assertEquals( 'updated in work tree', $admin->humanized_change( 'M' ) );
	}

	function test_gitium_humanized_change_A_case() {
		$admin = new Gitium_Submenu_Status();
		$this->assertEquals( 'added to work tree', $admin->humanized_change( 'A' ) );
	}

	function test_gitium_humanized_change_AM_case() {
		$admin = new Gitium_Submenu_Status();
		$this->assertEquals( 'added to work tree', $admin->humanized_change( 'AM' ) );
	}

	function test_gitium_humanized_change_deleted_case() {
		$admin = new Gitium_Submenu_Status();
		$this->assertEquals( 'deleted from work tree', $admin->humanized_change( 'R' ) );
	}

	function test_gitium_humanized_change_renamed_case() {
		$admin = new Gitium_Submenu_Status();
		$this->assertEquals( 'renamed from `myfile.txt`', $admin->humanized_change( 'R myfile.txt' ) );
	}

	function test_gitium_humanized_change_number_case() {
		$admin = new Gitium_Submenu_Status();
		$this->assertEquals( 100, $admin->humanized_change( 100 ) );
	}

	function test_gitium_humanized_change_admin_role() {
		$this->user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->user_id );
		$this->assertTrue( current_user_can( 'manage_options' ) );
		$admin = new Gitium_Submenu_Status();
		$this->assertEquals( 'zz', $admin->humanized_change( 'zz' ) );
	}

	function test_gitium_options_page_check() {
		$this->assertTrue( gitium_options_page_check() );
	}
}
