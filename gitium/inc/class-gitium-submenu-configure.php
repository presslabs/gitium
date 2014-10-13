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

class Gitium_Submenu_Configure extends Gitium_Menu {

	public function __construct() {
		parent::__construct( $this->gitium_menu_slug, $this->gitium_menu_slug );

		if ( current_user_can( 'manage_options' ) ) {
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'admin_init', array( $this, 'init_repo' ) );
			add_action( 'admin_init', array( $this, 'choose_branch' ) );
		}
	}

	public function admin_menu() {
		add_menu_page(
			__( 'Git Configuration', 'gitium' ),
			'Gitium',
			'manage_options',
			$this->menu_slug,
			array( $this, 'page' ),
			'http://marius.trypl.com/wp-content/uploads/2014/09/gitium.png'
		);

		$submenu_hook = add_submenu_page(
			$this->menu_slug,
			__( 'Git Configuration', 'gitium' ),
			__( 'Configuration', 'gitium' ),
			'manage_options',
			$this->menu_slug,
			array( $this, 'page' )
		);
		new Gitium_Help( $submenu_hook, 'GITIUM_STATUS' );
	}

	public function init_process( $remote_url ) {
		$git = $this->git;
		$git->init();
		$git->add_remote_url( $remote_url );
		$git->fetch_ref();
		if ( count( $git->get_remote_branches() ) == 0 ) {
			$git->add( 'wp-content', '.gitignore' );
			$current_user = wp_get_current_user();
			$git->commit( __( 'Initial commit', 'gitium' ), $current_user->display_name, $current_user->user_email );
			if ( ! $git->push( 'master' ) ) {
				$git->cleanup();
				return false;
			}
		}
		return true;
	}

	public function init_repo() {
		if ( ! isset( $_POST['SubmitFetch'] ) || ! isset( $_POST['remote_url'] ) ) {
			return;
		}

		check_admin_referer( 'gitium-admin' );

		if ( empty( $_POST['remote_url'] ) ) {
			$this->redirect( __( 'Please specify a valid repo.', 'gitium' ) );
		}

		if ( $this->init_process( $_POST['remote_url'] ) ) {
			$this->success_redirect();
		} else {
			$this->redirect( __( 'Could not push to remote', 'gitium' ) . ' ' . $_POST['remote_url'] );
		}
	}

	public function choose_branch() {
		if ( ! isset( $_POST['SubmitMergeAndPush'] ) || ! isset( $_POST['tracking_branch'] ) ) {
			return;
		}
		check_admin_referer( 'gitium-admin' );
		$this->git->add();

		$branch       = $_POST['tracking_branch'];
		$current_user = wp_get_current_user();

		$commit = $this->git->commit( __( 'Merged existing code from ', 'gitium' ) . get_home_url(), $current_user->display_name, $current_user->user_email );
		if ( ! $commit ) {
			$this->git->cleanup();
			$this->redirect( __( 'Could not create initial commit -> ', 'gitium' ) . $this->git->get_last_error() );
		}
		if ( ! $this->git->merge_initial_commit( $commit, $branch ) ) {
			$this->git->cleanup();
			$this->redirect( __( 'Could not merge the initial commit -> ', 'gitium' ) . $this->git->get_last_error() );
		}
		$this->git->push( $branch );
		$this->success_redirect();
	}

	private function setup_step_1() {
		list( $git_public_key, ) = gitium_get_keypair(); ?>
		<div class="wrap">
			<h2><?php _e( 'Status', 'gitium' ); ?> <code><?php _e( 'unconfigured', 'gitium' ); ?></code></h2>
		
		<form action="" method="POST">
		<?php wp_nonce_field( 'gitium-admin' ); ?>

		<table class="form-table">
		<tr>
		<th scope="row"><label for="remote_url"><?php _e( 'Remote URL', 'gitium' ); ?></label></th>
			<td>
				<input type="text" class="regular-text" name="remote_url" id="remote_url" placeholder="git@github.com:user/example.git" value="">
				<p class="description"><?php _e( 'This URL provide access to a Git repository via SSH, HTTPS, or Subversion.', 'gitium' ); ?><br />
		<?php _e( 'If you need to authenticate over "https://" instead of SSH use: <code>https://user:pass@github.com/user/example.git</code>', 'gitium' ); ?></p>
			</td>
		</tr>

		<?php if ( ! defined( 'GIT_KEY_FILE' ) || GIT_KEY_FILE == '' ) : ?>
		<tr>
		<th scope="row"><label for="key_pair"><?php _e( 'Key pair', 'gitium' ); ?></label></th>
			<td>
				<p>
				<input type="text" class="regular-text" name="key_pair" id="key_pair" value="<?php echo esc_attr( $git_public_key ); ?>" readonly="readonly">
				<input type="submit" name="SubmitRegenerateKeypair" class="button" value="<?php _e( 'Regenerate Key', 'gitium' ); ?>" />
				</p>
				<p class="description"><?php _e( 'If your use ssh keybased authentication for git you need to allow write access to your repository using this key.', 'gitium' ); ?><br />
<?php _e( 'Checkout instructions for <a href="https://help.github.com/articles/generating-ssh-keys#step-3-add-your-ssh-key-to-github" target="_blank">github</a> or <a href="https://confluence.atlassian.com/display/BITBUCKET/Add+an+SSH+key+to+an+account#AddanSSHkeytoanaccount-HowtoaddakeyusingSSHforOSXorLinux" target="_blank">bitbucket</a>.', 'gitium' ); ?>
				</p>
			</td>
		</tr>
		<?php endif; ?>

		</table>

		<p class="submit">
		<input type="submit" name="SubmitFetch" class="button-primary" value="<?php _e( 'Fetch', 'gitium' ); ?>" />
		</p>

		</form>
		</div>
		<?php
	}

	private function setup_step_2() {
		$git = $this->git; ?>
		<div class="wrap">
		<h2>Status</h2>

		<form action="" method="POST">
		<?php wp_nonce_field( 'gitium-admin' ); ?>

		<table class="form-table">
		<tr>
		<th scope="row"><label for="tracking_branch"><?php _e( 'Choose tracking branch', 'gitium' ); ?></label></th>
			<td>
				<select name="tracking_branch" id="tracking_branch">
				<?php foreach ( $git->get_remote_branches() as $branch ) : ?>
					<option value="<?php echo esc_attr( $branch ); ?>"><?php echo esc_html( $branch ); ?></option>
				<?php endforeach; ?>
				</select>
				<p class="description"><?php_e( 'Your code origin is set to', 'gitium' ); ?> <code><?php echo esc_html( $git->get_remote_url() ); ?></code></p>
			</td>
		</tr>
		</table>

		<p class="submit">
		<input type="submit" name="SubmitMergeAndPush" class="button-primary" value="<?php _e( 'Merge & Push', 'gitium' ); ?>" />
		</p>
		</form>
		</div>
		<?php
	}

	public function page() {
		$this->show_message();

		if ( ! $this->git->is_versioned() ) {
			return $this->setup_step_1();
		}

		if ( ! $this->git->get_remote_tracking_branch() ) {
			return $this->setup_step_2();
		}

		_gitium_status( true );
	}
}
