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

class Gitium_Submenu_Configure extends Gitium_Menu {

	public function __construct() {
		parent::__construct( $this->gitium_menu_slug, $this->gitium_menu_slug );

		if ( current_user_can( GITIUM_MANAGE_OPTIONS_CAPABILITY ) ) {
			add_action( GITIUM_ADMIN_MENU_ACTION, array( $this, 'admin_menu' ) );
			add_action( 'admin_init', array( $this, 'regenerate_keypair' ) );
			add_action( 'admin_init', array( $this, 'gitium_warning' ) );
			add_action( 'admin_init', array( $this, 'init_repo' ) );
			add_action( 'admin_init', array( $this, 'choose_branch' ) );
			add_action( 'admin_init', array( $this, 'disconnect_repository' ) );
		}
	}

	public function admin_menu() {
		add_menu_page(
			'Git Configuration',
			'Gitium',
			GITIUM_MANAGE_OPTIONS_CAPABILITY,
			$this->menu_slug,
			array( $this, 'page' ),
			plugins_url( 'img/gitium.png', dirname( __FILE__ ) )
		);

		$submenu_hook = add_submenu_page(
			$this->menu_slug,
			'Git Configuration',
			'Configuration',
			GITIUM_MANAGE_OPTIONS_CAPABILITY,
			$this->menu_slug,
			array( $this, 'page' )
		);
		new Gitium_Help( $submenu_hook, 'configuration' );
	}

	public function regenerate_keypair() {
	    $submit_keypair = filter_input(INPUT_POST, 'GitiumSubmitRegenerateKeypair', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		if ( ! isset( $submit_keypair ) ) {
			return;
		}
		check_admin_referer( 'gitium-admin' );
		gitium_get_keypair( true );
		$this->success_redirect( 'Keypair successfully regenerated.' );
	}

	public function gitium_warning() {
		$submit_warning = filter_input(INPUT_POST, 'GitiumSubmitWarning', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		if ( ! isset( $submit_warning ) ) {
			return;
		}
		check_admin_referer( 'gitium-admin' );
		$this->git->remove_wp_content_from_version_control();
	}

	public function init_process( $remote_url ) {
		$git = $this->git;
		$git->init();
		$git->add_remote_url( $remote_url );
		$git->fetch_ref();
		if ( count( $git->get_remote_branches() ) == 0 ) {
			$git->add( 'wp-content', '.gitignore' );
			$current_user = wp_get_current_user();
			$git->commit(  'Initial commit', $current_user->display_name, $current_user->user_email );
			if ( ! $git->push( 'master' ) ) {
				$git->cleanup();
				return false;
			}
		}
		return true;
	}

	public function init_repo() {
		$remote_url = filter_input(INPUT_POST, 'remote_url', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
	    $gitium_submit_fetch = filter_input(INPUT_POST, 'GitiumSubmitFetch', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		if ( ! isset( $gitium_submit_fetch ) || ! isset( $remote_url ) ) {
			return;
		}
		check_admin_referer( 'gitium-admin' );

		if ( empty( $remote_url ) ) {
			$this->redirect( 'Please specify a valid repo.' );
		}
		if ( $this->init_process( $remote_url ) ) {
			$this->success_redirect( 'Repository initialized successfully.' );
		} else {
			global $git;
			$this->redirect( 'Could not push to remote: ' . $remote_url . ' ERROR: ' . serialize( $git->get_last_error() ) );
		}
	}

	public function choose_branch() {
	    $gitium_submit_merge_push = filter_input(INPUT_POST, 'GitiumSubmitMergeAndPush', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $tracking_branch = filter_input(INPUT_POST, 'tracking_branch', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		if ( ! isset( $gitium_submit_merge_push ) || ! isset( $tracking_branch ) ) {
			return;
		}
		check_admin_referer( 'gitium-admin' );
		$this->git->add();

		$branch = $tracking_branch;
		set_transient( 'gitium_remote_tracking_branch', $branch );
		$current_user = wp_get_current_user();

		$commit = $this->git->commit( 'Merged existing code from ' . get_home_url(), $current_user->display_name, $current_user->user_email );
		if ( ! $commit ) {
			$this->git->cleanup();
			$this->redirect( 'Could not create initial commit -> ' . $this->git->get_last_error() );
		}
		if ( ! $this->git->merge_initial_commit( $commit, $branch ) ) {
			$this->git->cleanup();
			$this->redirect( 'Could not merge the initial commit -> ' . $this->git->get_last_error() );
		}
		$this->git->push( $branch );
		$this->success_redirect( 'Branch selected successfully.' );
	}

	private function setup_step_1_remote_url() {
		?>
		<tr>
		<th scope="row"><label for="remote_url"><?php echo 'Remote URL'; ?></label></th>
			<td>
				<input type="text" class="regular-text" name="remote_url" id="remote_url" placeholder="git@github.com:user/example.git" value="">
				<p class="description"><?php echo 'This URL provide access to a Git repository via SSH, HTTPS, or Subversion.'; ?><br />
		<?php echo 'If you need to authenticate over "https://" instead of SSH use: <code>https://user:pass@github.com/user/example.git</code>'; ?></p>
			</td>
		</tr>
		<?php
	}

	private function setup_step_1_key_pair() {
		if ( ! defined( 'GIT_KEY_FILE' ) || GIT_KEY_FILE == '' ) :
			list( $git_public_key, ) = gitium_get_keypair(); ?>
			<tr>
			<th scope="row"><label for="key_pair"><?php echo 'Key pair'; ?></label></th>
				<td>
					<p>
						<input type="text" class="regular-text" name="key_pair" id="key_pair" value="<?php echo esc_attr( $git_public_key ); ?>" readonly="readonly">
						<input type="submit" name="GitiumSubmitRegenerateKeypair" class="button" value="<?php echo 'Regenerate Key'; ?>" />
					</p>
					<p>
						<div>
							<button id="copyButton" class="button" data-copy-text="<?php echo esc_attr($git_public_key); ?>">Copy Key Pair</button>
						</div>
					</p>
					<p class="description"><?php echo 'If your code use ssh keybased authentication for git you need to allow write access to your repository using this key.'; ?><br />
			<?php echo 'Checkout instructions for <a href="https://help.github.com/articles/generating-ssh-keys#step-3-add-your-ssh-key-to-github" target="_blank">github</a> or <a href="https://confluence.atlassian.com/display/BITBUCKET/Add+an+SSH+key+to+an+account#AddanSSHkeytoanaccount-HowtoaddakeyusingSSHforOSXorLinux" target="_blank">bitbucket</a>.'; ?>
					</p>
				</td>
			</tr>
		<?php endif;
	}

	private function setup_warning() {
		?>
		<div class="wrap">
			<h2><?php echo 'Warning!'; ?></h2>
			<form name="gitium_form_warning" id="gitium_form_warning" action="" method="POST">
				<?php wp_nonce_field( 'gitium-admin' ); ?>
				<p><code>wp-content</code> is already under version control. You <a onclick="document.getElementById('gitium_form_warning').submit();" style="color:red;" href="#">must remove it from version control</a> in order to continue.</p>
				<p><strong>NOTE</strong> by doing this you WILL LOSE commit history, but NOT the actual files.</p>
				<input type="hidden" name="GitiumSubmitWarning" class="button-primary" value="1" />
			</form>
		</div>
		<?php
	}

	private function setup_step_1() {
		?>
		<div class="wrap">
			<h2>Configuration step 1</h2>
			<p>If you need help to set this up, please click on the "Help" button from the top right corner of this screen.</p>
			<form action="" method="POST">
				<?php wp_nonce_field( 'gitium-admin' ); ?>
				<table class="form-table">
					<?php $this->setup_step_1_remote_url(); ?>
					<?php $this->setup_step_1_key_pair(); ?>
				</table>
				<p class="submit">
				<input type="submit" name="GitiumSubmitFetch" class="button-primary" value="<?php echo 'Fetch'; ?>" />
				</p>
			</form>
		</div>
		<?php
	}

	private function setup_step_2() {
		$git = $this->git; ?>
		<div class="wrap">
		<h2>Configuration step 2</h2>
		<p>If you need help to set this up, please click on the "Help" button from the top right corner of this screen.</p>


		<form action="" method="POST">
		<?php wp_nonce_field( 'gitium-admin' ); ?>

		<table class="form-table">
		<tr>
		<th scope="row"><label for="tracking_branch">Choose tracking branch</label></th>
			<td>
				<select name="tracking_branch" id="tracking_branch">
				<?php foreach ( $git->get_remote_branches() as $branch ) : ?>
					<option value="<?php echo esc_attr( $branch ); ?>"><?php echo esc_html( $branch ); ?></option>
				<?php endforeach; ?>
				</select>
				<p class="description"><?php echo 'Your code origin is set to'; ?> <code><?php echo esc_html( $git->get_remote_url() ); ?></code></p>
			</td>
		</tr>
		</table>

		<p class="submit">
		<input type="submit" name="GitiumSubmitMergeAndPush" class="button-primary" value="<?php echo 'Merge & Push'; ?>" />
		</p>
		</form>
		<?php
			$this->show_disconnect_repository_button();
		?>
		</div>
		<?php
	}

	public function page() {
		$this->show_message();

		if ( wp_content_is_versioned() ) {
			return $this->setup_warning();
		}

		if ( ! $this->git->is_status_working() || ! $this->git->get_remote_url() ) {
			return $this->setup_step_1();
		}

		if ( ! $this->git->get_remote_tracking_branch() ) {
			return $this->setup_step_2();
		}

		_gitium_status( true );
		gitium_update_is_status_working();
		gitium_update_remote_tracking_branch();
	}
}
