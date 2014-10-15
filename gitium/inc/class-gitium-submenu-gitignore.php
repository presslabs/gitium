<?php
/*  Copyright 2014 PressLabs SRL <ping@presslabs.com>

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

class Gitium_Submenu_Gitignore extends Gitium_Menu {

	public function __construct() {
		parent::__construct( $this->gitium_menu_slug, $this->gitignore_menu_slug );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'save_gitignore' ) );
		add_action( 'admin_init', array( $this, 'regenerate_webhook' ) );
		add_action( 'admin_init', array( $this, 'regenerate_keypair' ) );
	}

	public function admin_menu() {
		$submenu_hook = add_submenu_page(
			$this->menu_slug,
			'.gitignore',
			__( 'Ignore list', 'gitium' ),
			'manage_options',
			$this->submenu_slug,
			array( $this, 'page' )
		);
		new Gitium_Help( $submenu_hook, 'GITIUM_GITIGNORE' );
	}

	public function regenerate_webhook() {
		if ( ! isset( $_POST['SubmitRegenerateWebhook'] ) ) {
			return;
		}
		check_admin_referer( 'gitium-gitignore' );
		gitium_get_webhook_key( true );
		$this->success_redirect( __( 'Webhook URL regenerates. Please make sure you update any external references.', 'gitium' ), $this->gitignore_menu_slug );
	}

	public function regenerate_keypair() {
		if ( ! isset( $_POST['SubmitRegenerateKeypair'] ) ) {
			return;
		}
		check_admin_referer( 'gitium-gitignore' );
		gitium_get_keypair( true );
		$this->success_redirect( __( 'Keypair successfully regenerated.', 'gitium' ), $this->gitignore_menu_slug );
	}

	private function show_webhook_table_webhook_url() {
		?>
		<tr>
			<th><label for="webhook-url"><?php _e( 'Webhook URL', 'gitium' ); ?>:</label></th>
			<td>
			  <p><code id="webhook-url"><?php echo esc_url( gitium_get_webhook() ); ?></code>
			  <?php if ( ! defined( 'GIT_WEBHOOK_URL' ) || GIT_WEBHOOK_URL == '' ) : ?>
			  <input type="submit" name="SubmitRegenerateWebhook" class="button" value="<?php _e( 'Regenerate Webhook', 'gitium' ); ?>" /></p>
			  <?php endif; ?>
			  <p class="description"><?php _e( 'Pinging this URL triggers an update from remote repository.', 'gitium' ); ?></p>
			</td>
		</tr>
		<?php
	}

	private function show_webhook_table_public_key() {
		list( $git_public_key, ) = gitium_get_keypair();
		if ( ! defined( 'GIT_KEY_FILE' ) || GIT_KEY_FILE == '' ) : ?>
			<tr>
				<th><label for="public-key"><?php _e( 'Public Key', 'gitium' ); ?>:</label></th>
				<td>
					<p><input type="text" class="regular-text" name="public_key" id="public-key" value="<?php echo esc_attr( $git_public_key ); ?>" readonly="readonly">
					<input type="submit" name="SubmitRegenerateKeypair" class="button" value="<?php _e( 'Regenerate Key', 'gitium' ); ?>" /></p>
					<p class="description"><?php _e( 'If your use ssh keybased authentication for git you need to allow write access to your repository using this key.', 'gitium' ); ?><br />
					<?php _e( 'Checkout instructions for <a href="https://help.github.com/articles/generating-ssh-keys#step-3-add-your-ssh-key-to-github" target="_blank">github</a> or <a href="https://confluence.atlassian.com/display/BITBUCKET/Add+an+SSH+key+to+an+account#AddanSSHkeytoanaccount-HowtoaddakeyusingSSHforOSXorLinux" target="_blank">bitbucket</a>.', 'gitium' ); ?>
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

	public function save_gitignore() {
		if ( ! isset( $_POST['SubmitSaveGitignore'] ) || ! isset( $_POST['gitignore_content'] ) ) {
			return;
		}
		check_admin_referer( 'gitium-gitignore' );

		if ( $this->git->set_gitignore( $_POST['gitignore_content'] ) ) {
			gitium_commit_gitignore_file();
			$this->success_redirect( __( 'The file `.gitignore` is saved!', 'gitium' ), $this->gitignore_menu_slug );
		} else {
			$this->redirect( __( 'The file `.gitignore` could not be saved!', 'gitium' ), false, $this->gitignore_menu_slug );
		}
	}

	public function page() {
		$this->show_message();
		?>
		<div class="wrap">
		<h2><?php printf( __( 'Git ignore list', 'gitium' ), GITIUM_LAST_COMMITS ); ?></h2>

		<form action="" method="POST">
		<?php wp_nonce_field( 'gitium-gitignore' ) ?>

		<p><?php _e( 'In this file you specify intentionally untracked files to ignore', 'gitium' ); ?> (<a href="http://git-scm.com/docs/gitignore" target="_blank">http://git-scm.com/docs/gitignore</a>)</p>
		<textarea name="gitignore_content" rows="20" cols="140"><?php echo esc_html( $this->git->get_gitignore() ); ?></textarea>

		<?php $this->show_webhook_table(); ?>
		<p class="submit">
		<input type="submit" name="SubmitSaveGitignore" class="button-primary" value="<?php _e( 'Save', 'gitium' ); ?>" />
		</p>

		</form>
		</div>
		<?php
	}

}
