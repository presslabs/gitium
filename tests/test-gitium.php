<?php

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

	function teardown() {
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
		$this->assertGreaterThan( 0, has_action( 'admin_enqueue_scripts','gitium_hook_plugin_and_theme_editor_page' ) );
	}

	function test_has_action_gitium_require_minimum_version() {
		$this->assertGreaterThan( 0, has_action( 'admin_notices','gitium_require_minimum_version' ) );
	}

	function test_has_action_gitium_remote_disconnected_notice() {
		$this->assertGreaterThan( 0, has_action( 'admin_notices','gitium_remote_disconnected_notice' ) );
	}

	function test_gitium_module_by_path_for_void_path() {
		$path   = '';
		$assert = _gitium_module_by_path( $path ) == array(
			'base_path' => $path,
			'type'      => 'file',
			'name'      => basename( $path ),
			'version'   => null,
		);
		$this->assertTrue( $assert );
	}

	function test_gitium_module_by_path_for_normal_plugin() {
		$path   = 'wp-content/plugins/autover/autover.php';
		$assert = _gitium_module_by_path( $path ) == array(
			'base_path' => 'wp-content/plugins/autover',
			'type'      => 'plugin',
			'name'      => 'AutoVer',
			'version'   => '1.2.3',
		);
		$this->assertTrue( $assert );
	}

	function test_gitium_module_by_path_for_one_file_plugin() {
		$path   = 'wp-content/plugins/simple.php';
		$assert = _gitium_module_by_path( $path ) == array(
			'base_path' => 'wp-content/plugins/simple.php',
			'type'      => 'plugin',
			'name'      => 'Simple',
			'version'   => '9.0',
		);
		$this->assertTrue( $assert );
	}

	function test_gitium_module_by_path_for_normal_theme() {
		$path   = 'wp-content/themes/hahaha/style.css';
		$assert = _gitium_module_by_path( $path ) == array(
			'base_path' => 'wp-content/themes/hahaha',
			'type'      => 'theme',
			'name'      => 'Ha ha ha hi',
			'version'   => '0.0.1',
		);
		$this->assertTrue( $assert );
	}

	function test_gitium_module_by_path_for_image_file_theme() {
		$path   = 'wp-content/themes/hahaha/img/logo.png';
		$assert = _gitium_module_by_path( $path ) == array(
			'base_path' => 'wp-content/themes/hahaha',
			'type'      => 'theme',
			'name'      => 'Ha ha ha hi',
			'version'   => '0.0.1',
		);
		$this->assertTrue( $assert );
	}

	function test_gitium_module_by_path_for_unregistered_theme() {
		$path   = 'wp-content/themes/mobile_pack_red/style.css.nokia.css';
		$assert = _gitium_module_by_path( $path ) == array(
			'base_path' => $path,
			'type'      => 'theme',
			'name'      => basename( $path ),
			'version'   => null,  # this theme is not in the themes transient, so we can't determine version
		);
		$this->assertTrue( $assert );
	}

	/**
	 * The struto-camila plugin represents in fact two plugins stored into the same directory
	 */
	function test_gitium_module_by_path_for_struto_camila_plugin() {
		$path   = 'wp-content/plugins/struto-camila/readme.txt';
		$assert = _gitium_module_by_path( $path ) == array(
			'base_path' => 'wp-content/plugins/struto-camila',
			'type'      => 'plugin',
			'name'      => 'Camila',
			'version'   => '1.0.1',
		);
		$this->assertTrue( $assert );
	}

	function test_gitium_module_by_path_with_whitespaces_in_theme() {
		$path   = 'wp-content/themes/hahaha/white space.css';
		$module = _gitium_module_by_path( $path );

		$expected = array(
			'base_path' => 'wp-content/themes/hahaha',
			'type'      => 'theme',
			'name'      => 'Ha ha ha hi',
			'version'   => '0.0.1',
		);
		$this->assertEquals( $expected, $module );
	}

	/**
	 * There are two similarly named plugins:
	 * gitium/gitium.php
	 * gitium-pltest/gitium-pltest.php
	 * This test is to assure that we catch the changes from the second plugin 'gitium-pltest'
	 */
	function test_gitium_module_by_path_for_similarly_named_plugins() {
		$path   = 'wp-content/plugins/gitium-pltest/readme.txt';
		$assert = _gitium_module_by_path( $path ) == array(
			'base_path' => 'wp-content/plugins/gitium-pltest',
			'type'      => 'plugin',
			'name'      => 'Gitium PL Test',
			'version'   => '2.1',
		);
		$this->assertTrue( $assert );
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
