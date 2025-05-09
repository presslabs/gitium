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

	public function redirect( $message = '', $success = false, $menu_slug = '' ) {
		$message_id = substr(
			md5( str_shuffle( 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789' ) . time() ), 0, 8
		);
		if ( $message ) {
			set_transient( 'message_' . $message_id, $message, 900 );
		}
		if ( '' === $menu_slug ) { $menu_slug = $this->menu_slug; }
		$url = network_admin_url( 'admin.php?page=' . $menu_slug );
		$url = esc_url_raw( add_query_arg(
			array(
				'message' => $message_id,
				'success' => $success,
			),
			$url
		) );
		wp_safe_redirect( $url );
		exit;
	}

	public function success_redirect( $message = '', $menu_slug = '' ) {
		$this->redirect( $message, true, $menu_slug );
	}

	public function disconnect_repository() {
        $gitium_disconnect_repo = filter_input(INPUT_POST, 'GitiumSubmitDisconnectRepository', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

		if ( ! isset( $gitium_disconnect_repo ) ) {
			return;
		}
		check_admin_referer( 'gitium-admin' );
		gitium_uninstall_hook();
		if ( ! $this->git->remove_remote() ) {
			$this->redirect( 'Could not remove remote.');
		}
		$this->success_redirect( 'You are now disconnected from the repository. New key pair generated.' );
	}

	public function show_message() {
	    $get_message = filter_input(INPUT_GET, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
	    $get_success = filter_input(INPUT_GET, 'success', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		if ( isset( $get_message ) && $get_message ) {
			$type    = ( isset( $get_success ) && $get_success == 1 ) ? 'updated' : 'error';
			$message = get_transient( 'message_'. $get_message );
			if ( $message  ) : ?>
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
			<input type="submit" name="GitiumSubmitDisconnectRepository" value="<?php echo 'Disconnect from repo'; ?>" class="button secondary" onclick="return confirm('<?php echo 'Are you sure you want to disconnect from the remote repository?'; ?>')"/>&nbsp;
		</form>
		<?php
	}
}
