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

class Gitium_Submenu_Requirements extends Gitium_Menu {

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
		parent::__construct( $this->gitium_menu_slug, $this->requirements_menu_slug );

		list( $this->req['is_git_version_gt_1_7'], $this->msg['is_git_version_gt_1_7'] ) = $this->is_git_version_gt_1_7();
		list( $this->req['is_proc_open'],          $this->msg['is_proc_open']          ) = $this->is_proc_open();
		list( $this->req['is_php_verion_gt_5_3'],  $this->msg['is_php_verion_gt_5_3']  ) = $this->is_php_version_gt_5_3();
		list( $this->req['can_exec_ssh_git_file'], $this->msg['can_exec_ssh_git_file'] ) = $this->can_exec_ssh_git_file();

		$this->status = $this->get_status();
		if ( true ) {
			$this->show_requirements_page();
		}
	}

	function show_requirements_page() {
		if ( current_user_can( 'manage_options' ) ) { #&& $this->status ) {
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		}
	}

	function get_req() {
		return $this->req;
	}

	function get_msg() {
		return $this->msg;
	}

	function get_requirements() {
		return array( $this->req, $this->msg );
	}

	function get_status() {
		$requirement = $this->get_req();

		foreach ( $requirement as $req ) :
			if ( false === $req ) :
				return false;
			endif;
		endforeach;

		return true;
	}

	private function is_git_version_gt_1_7() {
		global $git;

		$git_version = $git->get_version();

		if ( '1.7' <= substr( $git_version, 0, 3 ) ) {
			return array( true, "The git version is `$git_version`." );
		} else {
			return array( false, "The git version is `$git_version` and must be greater than `1.7`!" );
		}
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

	public function admin_menu() {
		$submenu_hook = add_submenu_page(
			$this->menu_slug,
			__( 'Gitium Requirements', 'gitium' ),
			__( 'Requirements', 'gitium' ),
			'manage_options',
			$this->submenu_slug,
			array( $this, 'page' )
		);
		new Gitium_Help( $submenu_hook, 'requirements' );
	}

	public function show_table_row( $status, $message ) {
		$color = ( true === $status ) ? 'forestgreen' : 'firebrick' ;
		echo "<tr><td><span style='color:$color'>$message</span></td></tr>";
	}

	public function page() {
		list( $req, $msg ) = $this->get_requirements();
		?>
		<div class="wrap">
		<div id="icon-options-general" class="icon32">&nbsp;</div>
		<h2><?php _e( 'Requirements', 'gitium' ); ?> <code class="small" style="background-color:<?php if ( true === $this->status ) { echo 'forestgreen'; } else { echo 'firebrick'; } ?>; color:whitesmoke;"><strong>status</strong></code></h2>
		<table class="widefat" id="gitium-requiremes-table">
		<thead><tr><th scope="col" class="manage-column"><?php _e( 'Requirements', 'gitium' ); ?></th></tr></thead>
		<tfoot><tr><th scope="col" class="manage-column"><?php _e( 'Requirements', 'gitium' ); ?></th></tr></tfoot>
		<tbody>
		<?php
			foreach ( $req as $key => $status ) :
				$message = $msg[ $key ];
				$this->show_table_row( $status, $message );
			endforeach;
		?>
		</tbody>
		</table>
		</div>
		<?php
	}
}
