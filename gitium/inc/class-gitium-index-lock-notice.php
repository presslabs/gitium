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
 * A manual fallback for a .git/index.lock left behind by a git process that was killed with its request.
 * Gitium removes such a lock by itself before every git command once it's 10 minutes old, the notice
 * lets the user remove it sooner, and tells them when it can't be removed (e.g. file permissions).
 */
class Gitium_Index_Lock_Notice extends Gitium_Menu {

	// git commands run by Gitium take a few seconds, a lock older than this one is most likely stale
	const MIN_AGE = 60;

	// the age at which Git_Wrapper removes the lock by itself
	const AUTO_REMOVE_AGE = 600;

	public function __construct() {
		parent::__construct( $this->gitium_menu_slug, $this->gitium_menu_slug );

		if ( current_user_can( GITIUM_MANAGE_OPTIONS_CAPABILITY ) ) {
			add_action( GITIUM_ADMIN_NOTICES_ACTION, array( $this, 'admin_notices' ) );
			add_action( 'admin_init', array( $this, 'remove_index_lock' ) );
		}
	}

	public function remove_index_lock() {
		$submit = filter_input( INPUT_POST, 'GitiumSubmitRemoveIndexLock', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! isset( $submit ) ) {
			return;
		}
		check_admin_referer( 'gitium-admin' );

		if ( false === $this->git->get_index_lock_age() ) {
			$this->success_redirect( 'The `.git/index.lock` file was already removed.' );
		}
		if ( ! $this->git->remove_stale_index_lock( self::MIN_AGE ) ) {
			$this->redirect( 'Could not remove the `.git/index.lock` file.' );
		}
		delete_transient( 'gitium_is_status_working' );
		delete_transient( 'gitium_uncommited_changes' );
		delete_transient( 'gitium_menu_bubble' );
		$this->success_redirect( 'The `.git/index.lock` file was removed.' );
	}

	public function admin_notices() {
		$age = $this->git->get_index_lock_age();
		if ( false === $age || $age < self::MIN_AGE ) {
			return;
		}
		// no git command ran since the lock got old enough to be removed, remove it now
		if ( $age >= self::AUTO_REMOVE_AGE && $this->git->remove_stale_index_lock( self::AUTO_REMOVE_AGE ) ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p><strong>Gitium: git has been locked for <?php echo esc_html( human_time_diff( time() - $age ) ); ?>.</strong></p>
			<p>
				A git command was stopped while it was running (usually by a timeout of PHP-FPM or of the web server)
				and the <code>.git/index.lock</code> file was left behind. Until it is removed git can't commit any change.
			</p>
			<?php if ( $age < self::AUTO_REMOVE_AGE ) : ?>
				<p>Gitium removes it by itself once it's <?php echo esc_html( human_time_diff( time() - self::AUTO_REMOVE_AGE ) ); ?> old, you can remove it now.</p>
			<?php else : ?>
				<p>Gitium could not remove it by itself: check that the web server user can delete <code><?php echo esc_html( $this->git->get_index_lock_path() ); ?></code>.</p>
			<?php endif; ?>
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
