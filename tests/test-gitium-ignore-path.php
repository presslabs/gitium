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

class Test_Gitium_Ignore_Path extends Gitium_UnitTestCase {

	private $test_dir = '';

	public function setup() {
		parent::setup();
		$this->test_dir = 'gitium-ignore-test-' . rand( 1, 99999 );
		$this->delete_on_teardown[] = dirname( WP_CONTENT_DIR ) . '/' . $this->test_dir;
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	private function _put_file( $path, $content = 'content' ) {
		$file = dirname( WP_CONTENT_DIR ) . '/' . $path;
		if ( ! is_dir( dirname( $file ) ) ) {
			mkdir( dirname( $file ), 0777, true );
		}
		file_put_contents( $file, $content . PHP_EOL );
	}

	private function _is_tracked( $path ) {
		exec( 'git -C ' . escapeshellarg( dirname( WP_CONTENT_DIR ) ) . ' ls-files --error-unmatch -- ' . escapeshellarg( ':(literal)' . $path ) . ' 2>/dev/null', $output, $return );
		return 0 === $return;
	}

	private function _is_ignored( $path ) {
		exec( 'git -C ' . escapeshellarg( dirname( WP_CONTENT_DIR ) ) . ' check-ignore -q --no-index -- ' . escapeshellarg( $path ), $output, $return );
		return 0 === $return;
	}

	function test_gitignore_escape() {
		$this->assertEquals( 'plugins/evil/\*', gitium_gitignore_escape( 'plugins/evil/*' ) );
		$this->assertEquals( 'a\?b\[c].txt', gitium_gitignore_escape( 'a?b[c].txt' ) );
		$this->assertEquals( 'a\\\\b.txt', gitium_gitignore_escape( 'a\b.txt' ) );
		$this->assertEquals( '\!important.txt', gitium_gitignore_escape( '!important.txt' ) );
		$this->assertEquals( '\#notes.txt', gitium_gitignore_escape( '#notes.txt' ) );
		$this->assertEquals( 'file.txt\ \ ', gitium_gitignore_escape( 'file.txt  ' ) );
		$this->assertEquals( 'plugins/a!b#c.txt', gitium_gitignore_escape( 'plugins/a!b#c.txt' ) );
	}

	/**
	 * rm_cached() removes only the file with that name, `*` is not a glob
	 */
	function test_rm_cached_is_literal() {
		global $git;

		$this->_put_file( "{$this->test_dir}/good/a.php" );
		$this->_put_file( "{$this->test_dir}/good/b.php" );
		$git->add();
		$git->commit( 'Add tracked files' );

		$git->rm_cached( "{$this->test_dir}/*" );

		$this->assertTrue( $this->_is_tracked( "{$this->test_dir}/good/a.php" ) );
		$this->assertTrue( $this->_is_tracked( "{$this->test_dir}/good/b.php" ) );
	}

	/**
	 * A planted file named `*` next to tracked files: ignoring it leaves the tracked files in git
	 */
	function test_ignore_path_with_glob_name_keeps_tracked_files() {
		global $git;

		$this->_put_file( "{$this->test_dir}/good/a.php" );
		$this->_put_file( "{$this->test_dir}/good/b.php" );
		$git->add();
		$git->commit( 'Add tracked files' );
		$this->_put_file( "{$this->test_dir}/*", 'planted' );

		$this->assertTrue( gitium_ignore_path( "{$this->test_dir}/*" ) );

		$this->assertTrue( $this->_is_tracked( "{$this->test_dir}/good/a.php" ) );
		$this->assertTrue( $this->_is_tracked( "{$this->test_dir}/good/b.php" ) );
		$this->assertFalse( $this->_is_ignored( "{$this->test_dir}/good/a.php" ) );
		$this->assertTrue( $this->_is_ignored( "{$this->test_dir}/*" ) );
		$this->assertContains( "{$this->test_dir}/\\*", explode( "\n", $git->get_gitignore() ) );
	}

	/**
	 * Ignoring a tracked file removes only that file from version control
	 */
	function test_ignore_path_untracks_the_file() {
		global $git;

		$this->_put_file( "{$this->test_dir}/x');alert(1);('.txt" );
		$this->_put_file( "{$this->test_dir}/other.txt" );
		$git->add();
		$git->commit( 'Add tracked files' );

		$this->assertTrue( gitium_ignore_path( "{$this->test_dir}/x');alert(1);('.txt" ) );

		$this->assertFalse( $this->_is_tracked( "{$this->test_dir}/x');alert(1);('.txt" ) );
		$this->assertTrue( $this->_is_ignored( "{$this->test_dir}/x');alert(1);('.txt" ) );
		$this->assertTrue( $this->_is_tracked( "{$this->test_dir}/other.txt" ) );
	}
}
