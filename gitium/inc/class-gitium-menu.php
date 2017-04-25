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
		$url = esc_url_raw( add_query_arg(
			array(
				'message' => $message_id,
				'success' => $success,
			),
			$url
		) );
		wp_safe_redirect( $url );
		wp_die();
	}

	public function success_redirect( $message = '', $menu_slug = '' ) {
		$this->redirect( $message, true, $menu_slug );
	}

	public function disconnect_repository() {
        $gitium_disconnect_repo = filter_input(INPUT_POST, 'GitiumSubmitDisconnectRepository', FILTER_SANITIZE_STRING);

		if ( ! isset( $gitium_disconnect_repo ) ) {
			return;
		}
		check_admin_referer( 'gitium-admin' );
		gitium_uninstall_hook();
		if ( ! $this->git->remove_remote() ) {
			$this->redirect( 'Could not remove remote.' );
		}
		$this->success_redirect();
	}

	public function show_message() {
	    $get_message = filter_input(INPUT_GET, 'message', FILTER_SANITIZE_STRING);
	    $get_success = filter_input(INPUT_GET, 'success', FILTER_SANITIZE_STRING);
		if ( isset( $get_message ) && $get_message ) {
			$type    = ( isset( $get_success ) && $get_success == 1 ? 'updated' : 'error' );
			$message = get_transient( 'message_'. $get_message );
			if ( ! empty( $message ) ) : ?>
				<div class="<?php echo esc_attr( $type ); ?>"><p><?php echo esc_html( $message ); ?></p></div>
			<?php endif;
		}
	}

	protected function show_disconnect_repository_button() {
		?>
		<form name="gitium_form_disconnect" id="gitium_form_disconnect" action="" method="POST">
			<?php
				wp_nonce_field( 'gitium-admin' );
		  ?>
			<input type="submit" name="GitiumSubmitDisconnectRepository" value='<?php _e( 'Disconnect from repo', 'gitium' ); ?>' class="button secondary" onclick="return confirm('<?php _e( 'Are you sure you want to disconnect from the remote repository?', 'gitium' ); ?>')"/>&nbsp;
		</form>
		<?php
	}
}
