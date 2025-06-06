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

class Gitium_Submenu_Settings extends Gitium_Menu {

	public function __construct() {
		parent::__construct( $this->gitium_menu_slug, $this->settings_menu_slug );
		add_action( GITIUM_ADMIN_MENU_ACTION, array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'save' ) );
		add_action( 'admin_init', array( $this, 'regenerate_webhook' ) );
		add_action( 'admin_init', array( $this, 'regenerate_public_key' ) );
	}

	public function admin_menu() {
		$submenu_hook = add_submenu_page(
			$this->menu_slug,
			'Settings',
			'Settings',
			GITIUM_MANAGE_OPTIONS_CAPABILITY,
			$this->submenu_slug,
			array( $this, 'page' )
		);
		new Gitium_Help( $submenu_hook, 'settings' );
	}

	public function regenerate_webhook() {
		$gitium_regen_webhook = filter_input(INPUT_POST, 'GitiumSubmitRegenerateWebhook', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		if ( ! isset( $gitium_regen_webhook ) ) {
			return;
		}
		check_admin_referer( 'gitium-settings' );
		gitium_get_webhook_key( true );
		$this->success_redirect( 'Webhook URL regenerates. Please make sure you update any external references.', $this->settings_menu_slug );
	}

	public function regenerate_public_key() {
		$submit_regenerate_pub_key = filter_input(INPUT_POST, 'GitiumSubmitRegeneratePublicKey', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		if ( ! isset( $submit_regenerate_pub_key ) ) {
			return;
		}
		check_admin_referer( 'gitium-settings' );
		gitium_get_keypair( true );
		$this->success_redirect( 'Public key successfully regenerated.', $this->settings_menu_slug );
	}

	private function show_webhook_table_webhook_url() {
		?>
		<tr>
			<th><label for="webhook-url"><?php echo 'Webhook URL'; ?>:</label></th>
			<td>
			  <p><code id="webhook-url"><?php echo esc_url( gitium_get_webhook() ); ?></code>
				<?php if ( ! defined( 'GIT_WEBHOOK_URL' ) || GIT_WEBHOOK_URL == '' ) : ?>
				<input type="submit" name="GitiumSubmitRegenerateWebhook" class="button" value="<?php echo 'Regenerate Webhook'; ?>" />
							<a class="button" href="<?php echo esc_url( gitium_get_webhook() ); ?>" target="_blank">Merge changes</a></p>
				<?php endif; ?>
			  	<p>
					<div>
						<button id="copyButton" class="button" data-copy-text="<?php echo esc_url( gitium_get_webhook() ) ?>">Copy Webhook URL</button>
					</div>
				</p>
				<p class="description"><?php echo 'Pinging this URL triggers an update from remote repository.'; ?></p>
			</td>
		</tr>
		<?php
	}

	private function show_webhook_table_public_key() {
		list( $git_public_key, ) = gitium_get_keypair();
		if ( ! defined( 'GIT_KEY_FILE' ) || GIT_KEY_FILE == '' ) : ?>
			<tr>
				<th><label for="public-key"><?php echo 'Public Key'; ?>:</label></th>
				<td>
					<p><input type="text" class="regular-text" name="public_key" id="public-key" value="<?php echo esc_attr( $git_public_key ); ?>" readonly="readonly">
					<input type="submit" name="GitiumSubmitRegeneratePublicKey" class="button" value="<?php echo 'Regenerate Key'; ?>" /></p>
					<p class="description"><?php echo 'If your code use ssh keybased authentication for git you need to allow write access to your repository using this key.'; ?><br />
					<?php echo 'Checkout instructions for <a href="https://help.github.com/articles/generating-ssh-keys#step-3-add-your-ssh-key-to-github" target="_blank">github</a> or <a href="https://confluence.atlassian.com/display/BITBUCKET/Add+an+SSH+key+to+an+account#AddanSSHkeytoanaccount-HowtoaddakeyusingSSHforOSXorLinux" target="_blank">bitbucket</a>.'; ?>
					</p>
				</td>
			</tr>
		<?php endif;
	}

	public function show_webhook_table() {
		?>
		<table class="form-table">
			<?php $this->show_webhook_table_webhook_url() ?>
			<?php $this->show_webhook_table_public_key(); ?>
		</table>
		<?php
	}

	public function save() {
	    $submit_save = filter_input(INPUT_POST, 'GitiumSubmitSave', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
	    $gitignore_content = filter_input(INPUT_POST, 'gitignore_content', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		if ( ! isset( $submit_save ) || ! isset( $gitignore_content ) ) {
			return;
		}
		check_admin_referer( 'gitium-settings' );

		if ( $this->git->set_gitignore( $gitignore_content ) ) {
			gitium_commit_and_push_gitignore_file();
			$this->success_redirect( 'The file `.gitignore` is saved!', $this->settings_menu_slug );
		} else {
			$this->redirect(  'The file `.gitignore` could not be saved!', false, $this->settings_menu_slug );
		}
	}

	public function page() {
		$this->show_message();
		?>
		<div class="wrap">
		<h2><?php echo 'Gitium Settings'; ?></h2>

		<form action="" method="POST">
		<?php wp_nonce_field( 'gitium-settings' ) ?>

		<p><span style="color:red;"><?php echo 'Be careful when you modify this list!'; ?></span></p>
		<textarea name="gitignore_content" rows="20" cols="140"><?php echo esc_html( $this->git->get_gitignore() ); ?></textarea>

		<?php $this->show_webhook_table(); ?>
		<p class="submit">
		<input type="submit" name="GitiumSubmitSave" class="button-primary" value="<?php echo 'Save'; ?>" />
		</p>

		</form>
		</div>
		<?php
	}

}
