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

	/**
	 * Gitium requires:
	 * git min version
	 * the function proc_open available
	 * PHP min version
	 * can exec the file inc/ssh-git
	 */
	public function __construct() {
		$this->_check_req();
		add_action( GITIUM_ADMIN_NOTICES_ACTION, array( $this, 'admin_notices' ) );
	}

	private function _check_req() {
		list($this->req['is_git_version'],       $this->msg['is_git_version']       ) = $this->is_git_version();
		list($this->req['is_proc_open'],         $this->msg['is_proc_open']         ) = $this->is_proc_open();
		list($this->req['is_php_verion'],        $this->msg['is_php_verion']        ) = $this->is_php_version();
		list($this->req['can_exec_ssh_git_file'],$this->msg['can_exec_ssh_git_file']) = $this->can_exec_ssh_git_file();

		return $this->req;
	}

	public function admin_notices() {
		if ( ! current_user_can( GITIUM_MANAGE_OPTIONS_CAPABILITY ) ) {
			return;
		}

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

	private function is_git_version() {
		$git_version = get_transient( 'gitium_git_version' );

		if ( GITIUM_MIN_GIT_VER > substr( $git_version, 0, 3 ) ) {
			global $git;
			$git_version = $git->get_version();
			set_transient( 'gitium_git_version', $git_version );
			if ( empty( $git_version ) ) {
				return array( false, 'There is no git installed on this server.' );
			} else if ( GITIUM_MIN_GIT_VER > substr( $git_version, 0, 3 ) ) {
				return array( false, "The git version is `$git_version` and must be greater than `" . GITIUM_MIN_GIT_VER . "`!" );
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

	private function is_php_version() {
		if ( ! function_exists( 'phpversion' ) ) {
			return array( false, 'The function `phpversion` is disabled!' );
		} else {
			$php_version = phpversion();
			if ( GITIUM_MIN_PHP_VER <= substr( $php_version, 0, 3 ) ) {
				return array( true, "The PHP version is `$php_version`." );
			} else {
				return array( false, "The PHP version is `$php_version` and is not greater or equal to " . GITIUM_MIN_PHP_VER );
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
