<?php
/*  Copyright 2014-2015 Presslabs SRL <ping@presslabs.com>

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

class Gitium_Menu {

	public $gitium_menu_slug   = 'gitium/gitium.php';
	public $commits_menu_slug  = 'gitium/gitium-commits.php';
	public $settings_menu_slug = 'gitium/gitium-settings.php';
	public $git = null;

	public $menu_slug;
	public $submenu_slug;

	public function __construct( $menu_slug, $submenu_slug ) {
		global $git;
		$this->git = $git;

		$this->menu_slug    = $menu_slug;
		$this->submenu_slug = $submenu_slug;
	}

	public function redirect( $message, $success = false, $menu_slug = '' ) {
		$message_id = substr(
			md5( str_shuffle( 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789' ) . time() ), 0, 8
		);
		if ( $message ) {
			set_transient( 'message_' . $message_id, $message, 900 );
		}
		if ( '' === $menu_slug ) { $menu_slug = $this->menu_slug; }
		$url = admin_url( 'admin.php?page=' . $menu_slug );
		$url = esc_url( add_query_arg(
			array(
				'message' => $message_id,
				'success' => $success,
			),
			$url
		) );
		wp_safe_redirect( $url );
		die();
	}

	public function success_redirect( $message = '', $menu_slug = '' ) {
		$this->redirect( $message, true, $menu_slug );
	}

	public function show_message() {
		if ( isset( $_GET['message'] ) && $_GET['message'] ) {
			$type    = ( isset( $_GET['success'] ) && $_GET['success'] == 1 ? 'updated' : 'error' );
			$message = get_transient( 'message_'. $_GET['message'] );
			if ( ! empty( $message ) ) : ?>
				<div class="<?php echo esc_attr( $type ); ?>"><p><?php echo esc_html( $message ); ?></p></div>
			<?php endif;
		}
	}
}
