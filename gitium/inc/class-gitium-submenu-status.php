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

class Gitium_Submenu_Status extends Gitium_Menu {

	public function __construct() {
		parent::__construct( $this->gitium_menu_slug, $this->gitium_menu_slug );

		if ( current_user_can( 'manage_options' ) ) {
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'admin_init', array( $this, 'save_changes' ) );
			add_action( 'admin_init', array( $this, 'save_ignorelist' ) );
		}
	}

	public function admin_menu() {
		add_menu_page(
			__( 'Git Status', 'gitium' ),
			'Gitium',
			'manage_options',
			$this->menu_slug,
			array( $this, 'page' ),
			plugins_url( 'img/gitium.png', dirname( __FILE__ ) )
		);

		$submenu_hook = add_submenu_page(
			$this->menu_slug,
			__( 'Git Status', 'gitium' ),
			__( 'Status', 'gitium' ),
			'manage_options',
			$this->menu_slug,
			array( $this, 'page' )
		);
		new Gitium_Help( $submenu_hook, 'status' );
	}

	private function get_change_meanings() {
		return array(
			'??' => __( 'untracked', 'gitium' ),
			'rM' => __( 'modified on remote', 'gitium' ),
			'rA' => __( 'added to remote', 'gitium' ),
			'rD' => __( 'deleted from remote', 'gitium' ),
			'D'  => __( 'deleted from work tree', 'gitium' ),
			'M'  => __( 'updated in work tree', 'gitium' ),
			'A'  => __( 'added to work tree', 'gitium' ),
			'AM' => __( 'added to work tree', 'gitium' ),
			'R'  => __( 'deleted from work tree', 'gitium' ),
		);
	}

	public function humanized_change( $change ) {
		$meaning = $this->get_change_meanings();

		if ( isset( $meaning[ $change ] ) ) {
			return $meaning[ $change ];
		}
		if ( 0 === strpos( $change, 'R ' ) ) {
			$old_filename = substr( $change, 2 );
			$change = sprintf( __( 'renamed from `%s`', 'gitium' ), $old_filename );
		}
		return $change;
	}

	public function save_ignorelist() {
		if ( ! isset( $_POST['path'] ) ) {
			return;
		} else {
			$path = $_POST['path'];
		}
		check_admin_referer( 'gitium-admin' );

		if ( $this->git->set_gitignore( join( "\n", array_unique( array_merge( explode( "\n", $this->git->get_gitignore() ), array( $path ) ) ) ) ) ) {
			gitium_commit_and_push_gitignore_file( $path );
			$this->success_redirect( __( 'The file `.gitignore` is saved!', 'gitium' ), $this->gitium_menu_slug );
		} else {
			$this->redirect( __( 'The file `.gitignore` could not be saved!', 'gitium' ), false, $this->gitium_menu_slug );
		}
	}

	public function save_changes() {
		if ( ! isset( $_POST['GitiumSubmitSaveChanges'] ) ) {
			return;
		}
		check_admin_referer( 'gitium-admin' );

		gitium_enable_maintenance_mode() or wp_die( __( 'Could not enable the maintenance mode!', 'gitium' ) );
		$this->git->add();
		$commitmsg = sprintf( __( 'Merged changes from %s on %s', 'gitium' ), get_site_url(), date( 'm.d.Y' ) );
		if ( isset( $_POST['commitmsg'] ) && ! empty( $_POST['commitmsg'] ) ) {
			$commitmsg = $_POST['commitmsg'];
		}
		$current_user = wp_get_current_user();
		$commit = $this->git->commit( $commitmsg, $current_user->display_name, $current_user->user_email );
		if ( ! $commit ) {
			$this->redirect( __( 'Could not commit!', 'gitium' ) );
		}
		$merge_success = gitium_merge_and_push( $commit );
		gitium_disable_maintenance_mode();
		if ( ! $merge_success ) {
			$this->redirect( __( 'Merge failed: ', 'gitium' ) . $this->git->get_last_error() );
		}
		$this->success_redirect( sprintf( __( 'Pushed commit: `%s`', 'gitium' ), $commitmsg ) );
	}

	private function show_ahead_and_behind_info( $changes = '' ) {
		$branch = $this->git->get_remote_tracking_branch();
		$ahead  = count( $this->git->get_ahead_commits() );
		$behind = count( $this->git->get_behind_commits() );
		?>
		<p>
			<?php printf( __( 'Following remote branch <code>%s</code>.', 'gitium' ), $branch );
		?>&nbsp;<?php
		if ( ! $ahead && ! $behind && empty( $changes ) ) {
			_e( 'Everything is up to date', 'gitium' );
		}
		if ( $ahead && $behind ) {
			printf( __( 'You are %s commits ahead and %s behind remote.', 'gitium' ), $ahead, $behind );
		} elseif ( $ahead ) {
			printf( __( 'You are %s commits ahead remote.', 'gitium' ), $ahead );
		} elseif ( $behind ) {
			printf( __( 'You are %s commits behind remote.', 'gitium' ), $behind );
		}
			?>
		</p>
		<?php
	}

	private function show_git_changes_table_rows( $changes = '' ) {
		?>
		<script type="application/javascript">
		function add_path_and_submit( elem ) {
			var container = document.getElementById( 'form_status' );
			var input     = document.createElement( 'input' );
			input.type    = 'hidden';
			input.name    = 'path';
			input.value   = elem;
			container.appendChild( input );
			container.submit();
		}
		</script>
		<?php
		$counter = 0;
		foreach ( $changes as $path => $type ) :
			$counter++;
			echo ( 0 != $counter % 2 ) ? '<tr class="alternate">' : '<tr>';
			echo '<td><strong>' . esc_html( $path ) . '</strong>';
			echo '<div class="row-actions"><span class="edit"><a href="#" onclick="add_path_and_submit(\'' . $path . '\');">' . __( 'Add this file to the `.gitignore` list.', 'gitium' ) . '</a></span></div></td>';
			echo '<td>';
			if ( is_dir( ABSPATH . '/' . $path ) && is_dir( ABSPATH . '/' . trailingslashit( $path ) . '.git' ) ) { // test if is submodule
				_e( 'Submodules are not supported in this version.', 'gitium' );
			} else {
				echo '<span title="' . esc_html( $type ) .'">' . esc_html( $this->humanized_change( $type ) ) . '</span>';
			}
			echo '</td>';
			echo '</tr>';
		endforeach;
	}

	private function show_git_changes_table( $changes = '' ) {
		?>
		<table class="widefat" id="git-changes-table">
		<thead><tr><th scope="col" class="manage-column"><?php _e( 'Path', 'gitium' ); ?></th><th scope="col" class="manage-column"><?php _e( 'Change', 'gitium' ); ?></th></tr></thead>
		<tfoot><tr><th scope="col" class="manage-column"><?php _e( 'Path', 'gitium' ); ?></th><th scope="col" class="manage-column"><?php _e( 'Change', 'gitium' ); ?></th></tr></tfoot>
		<tbody>
		<?php
		if ( empty( $changes ) ) :
			echo '<tr><td><p>';
			_e( 'Nothing to commit, working directory clean.', 'gitium' );
			echo '</p></td></tr>';
		else :
			$this->show_git_changes_table_rows( $changes );
		endif;
		?>
		</tbody>
		</table>
		<?php
	}

	private function show_git_changes_table_submit_buttons( $changes ) {
		if ( ! empty( $changes ) ) : ?>
			<p>
			<label for="save-changes"><?php _e( 'Commit message', 'gitium' ); ?>:</label>
			<input type="text" name="commitmsg" id="save-changes" class="widefat" value="" placeholder="<?php printf( __( 'Merged changes from %s on %s', 'gitium' ), get_site_url(), date( 'm.d.Y' ) ); ?>" />
			</p>
			<p>
			<input type="submit" name="GitiumSubmitSaveChanges" class="button-primary button" value="<?php _e( 'Save changes', 'gitium' ); ?>" <?php if ( get_transient( 'gitium_remote_disconnected' ) ) { echo 'disabled="disabled" '; } ?>/>&nbsp;
			</p>
		<?php endif;
	}

	private function changes_page() {
		list( , $changes ) = _gitium_status();
		?>
		<div class="wrap">
		<div id="icon-options-general" class="icon32">&nbsp;</div>
		<h2><?php _e( 'Status', 'gitium' ); ?> <code class="small" style="background-color:forestgreen; color:whitesmoke;"><?php _e( 'connected to', 'gitium' ); ?> <strong><?php echo esc_html( $this->git->get_remote_url() ); ?></strong></code></h2>

		<form name="form_status" id="form_status" action="" method="POST">
		<?php
			wp_nonce_field( 'gitium-admin' );
			$this->show_ahead_and_behind_info( $changes );
			$this->show_git_changes_table( $changes );
			$this->show_git_changes_table_submit_buttons( $changes );
		?>
		</form>
		</div>
		<?php
	}

	public function page() {
		$this->show_message();
		_gitium_status( true );
		if ( gitium_has_the_minimum_version() ) {
			$this->changes_page();
		}
	}
}
