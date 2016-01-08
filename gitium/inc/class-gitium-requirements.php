<?php
/*  Copyright 2014-2016 Presslabs SRL <ping@presslabs.com>

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class Gitium_Requirements {

	private $req = array();
	private $msg = array();
	private $status = false;

	/**
	 * Gitium requires:
	 * git version >= 1.7
	 * the function proc_open available
	 * PHP version >= 5.3
	 * can exec the file inc/ssh-git
	 */
	public function __construct() {
		list( $this->req['is_git_version_gt_1_7'], $this->msg['is_git_version_gt_1_7'] ) = $this->is_git_version_gt_1_7();
		list( $this->req['is_proc_open'],          $this->msg['is_proc_open']          ) = $this->is_proc_open();
		list( $this->req['is_php_verion_gt_5_3'],  $this->msg['is_php_verion_gt_5_3']  ) = $this->is_php_version_gt_5_3();
		list( $this->req['can_exec_ssh_git_file'], $this->msg['can_exec_ssh_git_file'] ) = $this->can_exec_ssh_git_file();

		if ( false === $this->status ) {
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		}
	}

	public function admin_notices() {
		foreach ( $this->req as $key => $value ) {
			if ( false === $value ) {
				echo "<div class='error-nag error'><p>Gitium Requirement: {$this->msg[$key]}</p></div>";
			}
		}
	}

	public function get_status() {
		$requirements = $this->req;

		foreach ( $requirements as $req ) :
			if ( false === $req ) :
				return false;
			endif;
		endforeach;

		return true;
	}

	private function is_git_version_gt_1_7() {
		$git_version = get_transient( 'gitium_git_version' );

		if ( '1.7' > substr( $git_version, 0, 3 ) ) {
			global $git;
			$git_version = $git->get_version();
			set_transient( 'gitium_git_version', $git_version );
			if ( empty( $git_version ) ) {
				return array( false, 'There is no git installed on this server.' );
			} else if ( '1.7' > substr( $git_version, 0, 3 ) ) {
				return array( false, "The git version is `$git_version` and must be greater than `1.7`!" );
			}
		}

		return array( true, "The git version is `$git_version`." );
	}

	private function is_proc_open() {
		if ( ! function_exists( 'proc_open' ) ) {
			return array( false, 'The function `proc_open` is disabled!' );
		} else {
			return array( true, 'The function `proc_open` is enabled!' );
		}
	}

	private function is_php_version_gt_5_3() {
		if ( ! function_exists( 'phpversion' ) ) {
			return array( false, 'The function `phpversion` is disabled!' );
		} else {
			$php_version = phpversion();
			if ( '5.3' <= substr( $php_version, 0, 3 ) ) {
				return array( true, "The PHP version is `$php_version`." );
			} else {
				return array( false, "The PHP version is `$php_version` and is not greater/equal than/with 5.3!" );
			}
		}
	}

	private function can_exec_ssh_git_file() {
		$filepath = dirname( __FILE__ ) . '/ssh-git';

		if ( ! function_exists( 'is_executable' ) ) {
			return array( false, 'The function `is_executable` is disabled!' );
		} else if ( is_executable( $filepath ) ) {
			return array( true, "The `$filepath` file can be executed!" );
		} else {
			return array( false, "The `$filepath` file is not executable" );
		}
	}
}
