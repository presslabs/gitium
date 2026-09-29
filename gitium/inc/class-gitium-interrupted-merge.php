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

/**
 * Tells the user when the PHP process was killed in the middle of a git operation
 * (e.g. by the PHP-FPM request_terminate_timeout) and lets them recover from it.
 */
class Gitium_Interrupted_Merge extends Gitium_Menu {

	public function __construct() {
		parent::__construct( $this->gitium_menu_slug, $this->gitium_menu_slug );

		if ( current_user_can( GITIUM_MANAGE_OPTIONS_CAPABILITY ) ) {
			add_action( GITIUM_ADMIN_NOTICES_ACTION, array( $this, 'admin_notices' ) );
			add_action( 'admin_init', array( $this, 'recover_merge' ) );
			add_action( 'admin_init', array( $this, 'remove_index_lock' ) );
		}
	}

	private function is_gitium_page() {
		$page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		return ( is_string( $page ) && 0 === strpos( $page, 'gitium/' ) );
	}

	// the git state changed, don't show the cached one
	private function clear_cached_status() {
		delete_transient( 'gitium_remote_tracking_branch' );
		delete_transient( 'gitium_is_status_working' );
		delete_transient( 'gitium_uncommited_changes' );
		delete_transient( 'gitium_menu_bubble' );
	}

	public function recover_merge() {
		$submit = filter_input( INPUT_POST, 'GitiumSubmitRecoverMerge', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! isset( $submit ) ) {
			return;
		}
		check_admin_referer( 'gitium-admin' );
		gitium_prevent_interruption();

		$merge = $this->git->get_interrupted_merge();
		if ( ! $merge ) {
			$this->clear_cached_status();
			$this->success_redirect( 'There is no interrupted merge to recover.' );
		}

		$lock = gitium_acquire_merge_lock();
		if ( ! $lock ) {
			$this->redirect( 'Another merge is in progress, please try again.' );
		}
		$saved = $this->git->recover_interrupted_merge();
		gitium_release_merge_lock( $lock );
		$this->clear_cached_status();

		if ( false === $saved ) {
			$this->redirect( 'Could not recover the interrupted merge: ' . $this->git->get_last_error() );
		}
		$message = sprintf( 'The files of the interrupted merge were recovered and the local branch `%s` was restored.', $merge['branch'] );
		if ( $saved ) {
			$message .= sprintf( ' The commits of the unfinished merge are kept in the branch `%s`.', $saved );
		}
		$message .= ' Check the status page, then push the changes again.';
		$this->success_redirect( $message );
	}

	public function remove_index_lock() {
		$submit = filter_input( INPUT_POST, 'GitiumSubmitRemoveIndexLock', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! isset( $submit ) ) {
			return;
		}
		check_admin_referer( 'gitium-admin' );

		if ( ! $this->git->remove_stale_index_lock() ) {
			$this->redirect( 'Could not remove the `.git/index.lock` file.' );
		}
		$this->clear_cached_status();
		$this->success_redirect( 'The `.git/index.lock` file was removed.' );
	}

	public function admin_notices() {
		// checking the branches runs git, do it only on the Gitium pages
		$merge = $this->git->get_interrupted_merge( $this->is_gitium_page() );
		if ( $merge ) {
			$this->show_interrupted_merge_notice( $merge );
		}

		$lock_age = $this->git->get_stale_index_lock();
		if ( false !== $lock_age ) {
			$this->show_index_lock_notice( $lock_age );
		}
	}

	private function show_interrupted_merge_notice( $merge ) {
		$started = empty( $merge['started'] ) ? '' : ' on ' . wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $merge['started'] );
		?>
		<div class="notice notice-error">
			<p><strong>Gitium: a merge with the remote repository was interrupted<?php echo esc_html( $started ); ?>.</strong></p>
			<p>
				The PHP process was stopped before the merge could finish, usually by a timeout of PHP-FPM
				(<code>request_terminate_timeout</code>) or of the web server / proxy, or by the memory limit.
				Some plugin and theme files may be missing from the disk or have the version from the remote repository.
				Your files are safe in the <code><?php echo esc_html( $merge['backup'] ); ?></code> branch.
			</p>
			<p>Gitium doesn't commit or merge anything until the files are recovered.</p>
			<form action="" method="POST">
				<?php wp_nonce_field( 'gitium-admin' ); ?>
				<p>
					<input type="submit" name="GitiumSubmitRecoverMerge" class="button-primary" value="Recover the files" />
				</p>
			</form>
			<p class="description">
				Recovering puts back the local files and the <code><?php echo esc_html( $merge['branch'] ); ?></code> branch, nothing is deleted.
				To avoid this in the future, raise the time limits of the server, merging a large site can take a few minutes.
			</p>
		</div>
		<?php
	}

	private function show_index_lock_notice( $lock_age ) {
		?>
		<div class="notice notice-error">
			<p><strong>Gitium: a git command was interrupted <?php echo esc_html( human_time_diff( time() - $lock_age ) ); ?> ago.</strong></p>
			<p>
				The PHP process was stopped while git was running (usually by a timeout of PHP-FPM or of the web server)
				and the <code>.git/index.lock</code> file was left behind. Until it is removed git can't commit any change.
			</p>
			<form action="" method="POST">
				<?php wp_nonce_field( 'gitium-admin' ); ?>
				<p>
					<input type="submit" name="GitiumSubmitRemoveIndexLock" class="button" value="Remove the lock file" />
				</p>
			</form>
		</div>
		<?php
	}
}
