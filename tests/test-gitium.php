<?php class Test_Gitium_Sauce extends WP_UnitTestCase {
	private $test_gitium_is_activated = FALSE;
	private $plugin = 'gitium/gitium.php';

	function Test_Gitium_Sauce() {
		$this->test_gitium_is_activated = is_plugin_active( $this->plugin );
	}

	function test_has_filter_upgrader_post_install() {
		$this->assertGreaterThan( 0, has_filter( 'upgrader_post_install', 'gitium_upgrader_post_install' ) );
	}

	function test_has_action_upgrader_process_complete() {
		$this->assertGreaterThan( 0, has_action( 'upgrader_process_complete', 'gitium_auto_push' ) );
	}

	function test_has_action_activated_plugin_git_check_post_activate_modifications() {
		$this->assertGreaterThan( 0, has_action( 'activated_plugin','gitium_check_post_activate_modifications' ) );
	}

	function test_has_action_deactivated_plugin_git_check_post_deactivate_modifications() {
		$this->assertGreaterThan( 0, has_action( 'deactivated_plugin','gitium_check_post_deactivate_modifications' ) );
	}

	function test_has_action_git_check_for_plugin_deletions() {
		$this->assertGreaterThan( 0, has_action( 'load-plugins.php', 'gitium_check_for_plugin_deletions' ) );
	}

	function test_has_action_git_check_for_themes_deletions() {
		$this->assertGreaterThan( 0, has_action( 'load-themes.php','gitium_check_for_themes_deletions' ) );
	}

	function test_gitium_module_by_path() {
		set_transient(
			'gitium_versions',
			array(
				'plugins' => array(
					'struto-camila/strutul.php' => array( 'name' => 'Strutul', 'version' => '3.2.1' ),
					'struto-camila/camila.php'  => array( 'name' => 'Camila', 'version' => '1.0.1' ),
					'autover/autover.php'       => array( 'name' => 'AutoVer', 'version' => '1.2.3' ),
					'simple.php'                => array( 'name' => 'Simple', 'version' => '9.0' )
				),
				'themes' => array(
					'hahaha' => array( 'name' => 'Ha ha ha hi', 'version' => '0.0.1' )
				)
			)
		);

		// Case 1
		$path   = '';
		$assert = _gitium_module_by_path( $path ) == array(
			'base_path' => $path,
			'type'      => 'other',
			'name'      => basename( $path ),
			'version'   => null,
		);
		$this->assertTrue( $assert );

		// Case 2
		$path   = 'wp-content/plugins/autover/autover.php';
		$assert = _gitium_module_by_path( $path ) == array(
			'base_path' => 'wp-content/plugins/autover',
			'type'      => 'plugin',
			'name'      => 'AutoVer',
			'version'   => '1.2.3',
		);
		$this->assertTrue( $assert );

		// Case 3
		$path   = 'wp-content/plugins/simple.php';
		$assert = _gitium_module_by_path( $path ) == array(
			'base_path' => 'wp-content/plugins/simple.php',
			'type'      => 'plugin',
			'name'      => 'Simple',
			'version'   => '9.0',
		);
		$this->assertTrue( $assert );

		// Case 4
		$path   = 'wp-content/themes/hahaha/style.css';
		$assert = _gitium_module_by_path( $path ) == array(
			'base_path' => 'wp-content/themes/hahaha',
			'type'      => 'theme',
			'name'      => 'Ha ha ha hi',
			'version'   => '0.0.1',
		);
		$this->assertTrue( $assert );

		// Case 5
		$path   = 'wp-content/themes/hahaha/img/logo.png';
		$assert = _gitium_module_by_path( $path ) == array(
			'base_path' => 'wp-content/themes/hahaha',
			'type'      => 'theme',
			'name'      => 'Ha ha ha hi',
			'version'   => '0.0.1',
		);
		$this->assertTrue( $assert );

		// Case 6
		$path   = 'wp-content/themes/mobile_pack_red/style.css.nokia.css';
		$assert = _gitium_module_by_path( $path ) == array(
			'base_path' => $path,
			'type'      => 'theme',
			'name'      => basename( $path ),
			'version'   => null,
		);
		$this->assertTrue( $assert );

		// Case 7
		$path   = 'wp-content/plugins/struto-camila/camila.php';
		$assert = _gitium_module_by_path( $path ) == array(
			'base_path' => 'wp-content/plugins/struto-camila',
			'type'      => 'plugin',
			'name'      => 'Strutul',
			'version'   => '3.2.1',
		);
		$this->assertTrue( $assert );
	}
}
$test = new Test_Gitium_Sauce();
