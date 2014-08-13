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

class Gitium_Admin {
	private $options;
	private $menu_slug = 'gitium/gitium.php';
	private $git = null;

	public function __construct() {
		global $git;
		$this->git = $git;

		list( $git_public_key, $git_private_key ) = gitium_get_keypair();
		$git->set_key( $git_private_key );

		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_menu', array( $this, 'add_menu_bubble' ) );

		// admin actions
		if ( current_user_can( 'manage_options' ) ) {
			add_action( 'admin_init', array( $this, 'init_repo' ) );
			add_action( 'admin_init', array( $this, 'choose_branch' ) );
			add_action( 'admin_init', array( $this, 'save_changes' ) );
			add_action( 'admin_init', array( $this, 'regenerate_webhook' ) );
			add_action( 'admin_init', array( $this, 'regenerate_keypair' ) );
		}
	}

	public function add_menu_page() {
		$page = add_menu_page(
			'Git Status',
			'Code',
			'manage_options',
			$this->menu_slug,
			array( $this, 'admin_page' )
		);
	}

	public function humanized_change( $change ) {
		$meaning = array(
			'??' => 'untracked',
			'rM' => 'modified to remote',
			'rA' => 'added to remote',
			'rD' => 'deleted from remote',
			'D'  => 'deleted from work tree',
			'M'  => 'updated in work tree',
			'A'  => 'added to work tree',
			'AM' => 'added to work tree',
			'R'  => 'deleted from work tree',
		);

		if ( isset( $meaning[ $change ] ) )
			return $meaning[ $change ];

		if ( 0 === strpos( $change, 'R ' ) ) {
			$old_filename = substr( $change, 2 );
			$change = "renamed from `$old_filename`";
		}
		return $change;
	}

	public function redirect( $message, $success = false ) {
		$message_id = substr(
			md5( str_shuffle( 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789' ) . time() ), 0, 8
		);
		if ( $message )
			set_transient( 'message_' . $message_id, $message, 900 );
		$url = admin_url( 'admin.php?page=' . $this->menu_slug );
		$url = add_query_arg(
			array(
				'message' => $message_id,
				'success' => $success,
			),
			$url
		);
		wp_safe_redirect( $url );
		die();
	}

	public function success_redirect( $message = '' ) {
		$this->redirect( $message, true );
	}

	public function init_process( $remote_url ) {
		$git = $this->git;
		$git->init();
		$git->add_remote_url( $remote_url );
		$git->fetch_ref();
		if ( count( $git->get_remote_branches() ) == 0 ) {
			$git->add( 'wp-content', '.gitignore' );
			$current_user = wp_get_current_user();
			$git->commit( 'Initial commit', $current_user->display_name, $current_user->user_email );
			if ( ! $git->push( 'master' ) ) {
				$git->cleanup();
				return FALSE;
			}
		}
		return TRUE;
	}

	public function init_repo() {
		if ( ! isset( $_POST['SubmitFetch'] ) || ! isset( $_POST['remote_url'] ) ) return;
		check_admin_referer( 'gitium-admin' );
		if ( empty( $_POST['remote_url'] ) ) $this->redirect( 'Please secify a valid repo.' );

		if ( $this->init_process( $_POST['remote_url'] ) )
			$this->success_redirect();
		else
			$this->redirect( 'Could not push to remote ' . $_POST['remote_url']  );
	}

	public function choose_branch() {
		if ( ! isset( $_POST['SubmitMergeAndPush'] ) || ! isset( $_POST['tracking_branch'] ) ) return;
		check_admin_referer( 'gitium-admin' );
		
		$git = $this->git;
		$git->add();
		$branch       = $_POST['tracking_branch'];
		$current_user = wp_get_current_user();
		$commit = $git->commit( 'Merged existing code from ' . get_home_url(), $current_user->display_name, $current_user->user_email );
		if ( ! $commit ) {
			$git->cleanup();
			$this->redirect( 'Could not create initial commit -> ' . $git->get_last_error() );
		}
		if ( ! $git->merge_initial_commit( $commit, $branch ) ) {
			$git->cleanup();
			$this->redirect( 'Could not merge the initial commit -> ' . $git->get_last_error() );
		}
		$git->push( $branch );
		$this->success_redirect();
	}

	public function save_changes() {
		if ( ! isset( $_POST['SubmitSave'] ) ) return;
		check_admin_referer( 'gitium-admin' );

		$git = $this->git;
		enable_maintenance_mode() or wp_die( 'Could not enable the maintenance mode!' );
		$git->add();
		$commitmsg = 'Merged changes from ' . get_site_url() . ' on ' . date( 'm.d.Y' );
		if ( isset( $_POST['commitmsg'] ) && ! empty( $_POST['commitmsg'] ) ) {
			$commitmsg = $_POST['commitmsg'];
		}

		$current_user = wp_get_current_user();
		$commit = $git->commit( $commitmsg, $current_user->display_name, $current_user->user_email );
		if ( ! $commit ) {
			$this->redirect( 'Could not commit!' );
		}

		$merge_success = gitium_merge_and_push( $commit );
		disable_maintenance_mode();
		if ( ! $merge_success )
			$this->redirect( 'Merge failed: ' . $git->get_last_error() );
		$this->success_redirect( "Pushed commit: `$commitmsg`" );
	}

	public function regenerate_webhook() {
		if ( ! isset( $_POST['SubmitRegenerateWebhook'] ) ) return;
		check_admin_referer( 'gitium-admin' );

		gitium_get_webhook_key( TRUE );
		$this->success_redirect( 'Webhook URL regenrates. Please make sure you update any external references.' );
	}

	public function regenerate_keypair() {
		if ( ! isset( $_POST['SubmitRegenerateKeypair'] ) ) return;
		check_admin_referer( 'gitium-admin' );

		gitium_get_keypair( TRUE );
		$this->success_redirect( 'Keypair successfully regenerated.' );
	}

	public function admin_page() {
		if ( isset( $_GET['message'] ) && $_GET['message'] ) {
			$type    = ( isset( $_GET['success'] ) && $_GET['success'] == 1 ? 'updated' : 'error' );
			$message = get_transient( 'message_'. $_GET['message'], '' );
			if ( ! empty( $message ) ) : ?>
				<div class="<?php echo esc_attr( $type ); ?>"><p><?php echo esc_html( $message ); ?></p></div>
			<?php endif;
		}

		$git = $this->git;

		if ( ! $git->is_versioned() )
			return $this->setup_step_1();

		if ( ! $git->get_remote_tracking_branch() )
			return $this->setup_step_2();

		_gitium_status( true );
		if ( gitium_has_the_minimum_version() )
			$this->changes_page();

	}

	public function add_menu_bubble() {
		global $menu;
		$git = $this->git;
		list( $branch_status, $changes ) = _gitium_status();
		if ( ! empty( $changes ) ) :
			$bubble_count = count( $changes );
			foreach ( $menu as $key => $value  ) {
				if ( $this->menu_slug == $menu[ $key ][2] ) {
					$menu[ $key ][0] .= " <span class='update-plugins count-$bubble_count'><span class='plugin-count'>"
						. $bubble_count . '</span></span>';
					return;
				}
			}
		endif;
	}

	private function setup_step_1() {
		$git = $this->git;
		list( $git_public_key, $git_private_key ) = gitium_get_keypair(); ?>
		<div class="wrap">
		<h2>Status <code>unconfigured</code></h2>
		
		<form action="" method="POST">
		<?php wp_nonce_field( 'gitium-admin' ) ?>

		<table class="form-table">
		<tr>
			<th scope="row"><label for="remote_url">Remote URL</label></th>
			<td>
				<input type="text" class="regular-text" name="remote_url" id="remote_url" placeholder="git@github.com:user/example.git" value="">
				<p class="description">This URL provide access to a Git repository via SSH, HTTPS, or Subversion.<br />
				If you need to authenticate over "https://" instead of SSH use: <code>https://user:pass@github.com/user/example.git</code></p>
			</td>
		</tr>

		<?php if ( ! defined( 'GIT_KEY_FILE' ) || GIT_KEY_FILE == '' ) : ?>
		<tr>
			<th scope="row"><label for="key_pair">Key pair</label></th>
			<td>
				<p>
				<input type="text" class="regular-text" name="key_pair" id="key_pair" value="<?php echo esc_attr( $git_public_key ); ?>" readonly="readonly">
				<input type="submit" name="SubmitRegenerateKeypair" class="button" value="Regenerate Key" />
				</p>
				<p class="description">If your use ssh keybased authentication for git you need to allow write access to your repository using this key.<br>
				Checkout instructions for <a href="https://help.github.com/articles/generating-ssh-keys#step-3-add-your-ssh-key-to-github" target="_blank">github</a> or <a href="https://confluence.atlassian.com/display/BITBUCKET/Add+an+SSH+key+to+an+account#AddanSSHkeytoanaccount-HowtoaddakeyusingSSHforOSXorLinux" target="_blank">bitbucket</a>.
				</p>
			</td>
		</tr>
		<?php endif; ?>

		</table>

		<p class="submit">
			<input type="submit" name="SubmitFetch" class="button-primary" value="Fetch" />
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
		<?php wp_nonce_field( 'gitium-admin' ) ?>

		<table class="form-table">
		<tr>
			<th scope="row"><label for="tracking_branch">Choose tracking branch</label></th>
			<td>
				<select name="tracking_branch" id="tracking_branch">
				<?php foreach ( $git->get_remote_branches() as $branch ) : ?>
					<option value="<?php echo esc_attr( $branch ); ?>"><?php echo esc_html( $branch ); ?></option>
				<?php endforeach; ?>
				</select>
				<p class="description">Your code origin is set to <code><?php echo esc_html( $git->get_remote_url() ); ?></code></p>
			</td>
		</tr>
		</table>

		<p class="submit">
			<input type="submit" name="SubmitMergeAndPush" class="button-primary" value="Merge & Push" />
		</p>
		</form>
		</div>
		<?php
	}

	private function show_ahead_and_behind_info( $changes = '' ) {
		$branch = $this->git->get_remote_tracking_branch();
		$ahead  = count( $this->git->get_ahead_commits() );
		$behind = count( $this->git->get_behind_commits() );
		?>
		<p>
		  Following remote branch <code><?php echo esc_html( $branch ); ?></code>.
		  <?php
			if ( ! $ahead && ! $behind && empty( $changes ) ) echo 'Everything is up to date';
			if ( $ahead && $behind ) echo esc_html( "You are $ahead commits ahead and $behind behind remote." );
			elseif ( $ahead ) echo esc_html( "You are $ahead commits ahead remote." );
			elseif ( $behind ) echo esc_html( "You are $behind commits behind remote." );
			?>
		</p>
		<?php
	}

	private function show_git_changes_table( $changes = '' ) {
		?>
		<table class="widefat" id="git-changes-table">
		<thead><tr><th scope="col" class="manage-column">Path</th><th scope="col" class="manage-column">Change</th></tr></thead>
		<tfoot><tr><th scope="col" class="manage-column">Path</th><th scope="col" class="manage-column">Change</th></tr></tfoot>
		<tbody>
			<?php if ( empty( $changes ) ) : ?>
				<tr><td><p>Nothing to commit, working directory clean.</p></td></tr>
			<?php else : ?>
				<?php foreach ( $changes as $path => $type ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $path ); ?></strong>
						</td>
						<td>
							<?php if ( is_dir( ABSPATH . '/' . $path ) && is_dir( ABSPATH . '/' . trailingslashit( $path ) . '.git' ) ) { // test if is submodule ?>
								Submodules are not supported in this version.
							<?php } else { ?>
								<span title="<?php echo esc_html( $type ); ?>"><?php echo esc_html( $this->humanized_change( $type ) ); ?></span>
							<?php } ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
		</table>
		<?php
	}

	private function changes_page() {
		list( $branch_status, $changes ) = _gitium_status();
		list( $git_public_key, $git_private_key ) = gitium_get_keypair();
		?>
		<div class="wrap">
		<div id="icon-options-general" class="icon32">&nbsp;</div>
		<h2>Status <code class="small">connected to <strong><?php echo esc_html( $this->git->get_remote_url() ); ?></strong></code></h2>

		<?php
			$this->show_ahead_and_behind_info( $changes );
			$this->show_git_changes_table( $changes );
		?>

		<form action="" method="POST">
		<?php wp_nonce_field( 'gitium-admin' ) ?>

		<?php if ( ! empty( $changes ) ) : ?>
			<p>
			<label for="save-changes">Commit message:</label>
			<input type="text" name="commitmsg" id="save-changes" class="widefat" value="" placeholder="Merged changes from <?php echo esc_url( get_site_url() ); ?> on <?php echo esc_html( date( 'm.d.Y' ) ); ?>" />
			</p>
			<p>
			<input type="submit" name="SubmitSave" class="button-primary button" value="Save changes" <?php if ( get_transient( 'gitium_remote_disconnected', TRUE ) ) echo 'disabled="disabled" '; ?>/>
			</p>
		<?php endif; ?>

		<table class="form-table">
		  <tr>
			<th><label for="webhook-url">Webhook URL:</label></th>
			<td>
			  <p><code id="webhook-url"><?php echo esc_url( gitium_get_webhook() ); ?></code>
			  <?php if ( ! defined( 'GIT_WEBHOOK_URL' ) || GIT_WEBHOOK_URL == '' ) : ?>
			  <input type="submit" name="SubmitRegenerateWebhook" class="button" value="Regenerate Webhook" /></p>
			  <?php endif; ?>
			  <p class="description">Pinging this URL triggers an update from remote repository.</p>
			</td>
		  </tr>

		  <?php if ( ! defined( 'GIT_KEY_FILE' ) || GIT_KEY_FILE == '' ) : ?>
		  <tr>
			<th><label for="public-key">Public Key:</label></th>
			<td>
			  <p><input type="text" class="regular-text" name="public_key" id="public-key" value="<?php echo esc_attr( $git_public_key ); ?>" readonly="readonly">
			  <input type="submit" name="SubmitRegenerateKeypair" class="button" value="Regenerate Key" /></p>
			  <p class="description">If your use ssh keybased authentication for git you need to allow write access to your repository using this key.<br>
			  Checkout instructions for <a href="https://help.github.com/articles/generating-ssh-keys#step-3-add-your-ssh-key-to-github" target="_blank">github</a> or <a href="https://confluence.atlassian.com/display/BITBUCKET/Add+an+SSH+key+to+an+account#AddanSSHkeytoanaccount-HowtoaddakeyusingSSHforOSXorLinux" target="_blank">bitbucket</a>.
			  </p>
			</td>
		  </tr>
		  <?php endif; ?>
		</table>

		</form>
		</div>
		<?php
	}
}

if ( is_admin() ) add_action( 'init', 'gitium_admin_page' );
function gitium_admin_page() {
	$gitium_options = new Gitium_Admin();
}
