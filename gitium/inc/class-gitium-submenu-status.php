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

class Gitium_Submenu_Status extends Gitium_Menu {

	public function __construct() {
		parent::__construct( $this->gitium_menu_slug, $this->gitium_menu_slug );

		if ( current_user_can( GITIUM_MANAGE_OPTIONS_CAPABILITY ) ) {
			add_action( GITIUM_ADMIN_MENU_ACTION, array( $this, 'admin_menu' ) );
			add_action( 'admin_init', array( $this, 'save_changes' ) );
			add_action( 'admin_init', array( $this, 'save_ignorelist' ) );
			add_action( 'admin_init', array( $this, 'disconnect_repository' ) );
		}
	}

	public function admin_menu() {
		add_menu_page(
			'Git Status',
			'Gitium',
			GITIUM_MANAGE_OPTIONS_CAPABILITY,
			$this->menu_slug,
			array( $this, 'page' ),
			plugins_url( 'img/gitium.png', dirname( __FILE__ ) )
		);

		$submenu_hook = add_submenu_page(
			$this->menu_slug,
			'Git Status',
			'Status',
			GITIUM_MANAGE_OPTIONS_CAPABILITY,
			$this->menu_slug,
			array( $this, 'page' )
		);
		new Gitium_Help( $submenu_hook, 'status' );
	}

	private function get_change_meanings() {
		return array(
			'??' => 'untracked',
			'rM' => 'modified on remote',
			'rA' => 'added to remote',
			'rD' => 'deleted from remote',
			'D'  => 'deleted from work tree',
			'M'  => 'updated in work tree',
			'A'  => 'added to work tree',
			'AM' => 'added to work tree',
			'R'  => 'deleted from work tree',
		);
	}

	public function humanized_change( $change ) {
		$meaning = $this->get_change_meanings();

		if ( isset( $meaning[ $change ] ) ) {
			return $meaning[ $change ];
		}
		if ( 0 === strpos( $change, 'R ' ) ) {
			$old_filename = substr( $change, 2 );
			$change = sprintf( 'renamed from `%s`', $old_filename );
		}
		return $change;
	}

	public function save_ignorelist() {
	    $gitium_ignore_path = filter_input(INPUT_POST, 'GitiumIgnorePath', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		if ( ! isset( $gitium_ignore_path ) ) {
			return;
		} else {
			$path = $gitium_ignore_path;
		}
		check_admin_referer( 'gitium-admin' );

		if ( $this->git->set_gitignore( join( "\n", array_unique( array_merge( explode( "\n", $this->git->get_gitignore() ), array( $path ) ) ) ) ) ) {
			gitium_commit_and_push_gitignore_file( $path );
			$this->success_redirect( 'The file `.gitignore` is saved!', $this->gitium_menu_slug );
		} else {
			$this->redirect( 'The file `.gitignore` could not be saved!', false, $this->gitium_menu_slug );
		}
	}

	public function save_changes() {
		$gitium_save_changes = filter_input(INPUT_POST, 'GitiumSubmitSaveChanges', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$gitium_commit_msg = filter_input(INPUT_POST, 'commitmsg', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

		if ( ! isset( $gitium_save_changes ) ) {
			return;
		}
	
		check_admin_referer( 'gitium-admin' );
		
		gitium_enable_maintenance_mode() or wp_die('Could not enable the maintenance mode!');
		
		$commitmsg = sprintf(
			'Merged changes from %s on %s',
			esc_url( get_site_url() ),
			esc_html( date( 'm.d.Y' ) )
		);
		
		if ( isset( $gitium_commit_msg ) && ! empty( $gitium_commit_msg ) ) {
			$commitmsg = $gitium_commit_msg;
		}

		$commits = array();

		$current_user = wp_get_current_user();
        
		// Get local status and behind commits
		$local_status = $this->git->local_status();
		$behind_commits = count( $this->git->get_behind_commits() );
        
		if ( $this->git->is_dirty() && $this->git->add() > 0 ) {
			$commit = $this->git->commit( $commitmsg, $current_user->display_name, $current_user->user_email );
			if ( ! $commit ) {
				gitium_disable_maintenance_mode();
				$this->redirect( 'Could not commit!');
			}
			$commits[] = $commit;
		}

		$merge_success = gitium_merge_and_push( $commits );
		
		gitium_disable_maintenance_mode();
		
		if ( ! $merge_success ) {
			$this->redirect( 'Merge failed: ' . $this->git->get_last_error() );
		}
		
		// Determine message based on previous conditions
		if ( $behind_commits > 0 && empty( $local_status[1] ) ) {
			$this->success_redirect( sprintf( 'Pull done!' ) );
		} else{
			$this->success_redirect( sprintf( 'Pushed commit: `%s`', $commitmsg ) );
		}
	}

	private function show_ahead_and_behind_info( $changes = '' ) {
		$branch = $this->git->get_remote_tracking_branch();
		$ahead  = count( $this->git->get_ahead_commits() );
		$behind = count( $this->git->get_behind_commits() );
		?>
		<p>
			<?php 
			printf(
				'%s',
				wp_kses_post( 'Following remote branch <code>' . esc_html( $branch ) . '</code>.' )
			);
			?>&nbsp;
			<?php
			if ( ! $ahead && ! $behind && empty( $changes ) ) {
				echo esc_html( 'Everything is up to date' );
			}
			if ( $ahead && $behind ) {
				printf(
					'%s',
					esc_html( sprintf( 'You are %s commits ahead and %s behind remote.', $ahead, $behind ) )
				);
			} elseif ( $ahead ) {
				printf(
					'%s',
					esc_html( sprintf( 'You are %s commits ahead remote.', $ahead ) )
				);
			} elseif ( $behind ) {
				printf(
					'%s',
					esc_html( sprintf( 'You are %s commits behind remote.', $behind ) )
				);
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
			input.name    = 'GitiumIgnorePath';
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
			echo '<div class="row-actions"><span class="edit"><a href="#" onclick="add_path_and_submit(\'' . esc_html($path) . '\');">' . 'Add this file to the `.gitignore` list.' . '</a></span></div></td>';
			echo '<td>';
			if ( is_dir( ABSPATH . '/' . $path ) && is_dir( ABSPATH . '/' . trailingslashit( $path ) . '.git' ) ) { // test if is submodule
				echo 'Submodules are not supported in this version.';
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
		<thead><tr><th scope="col" class="manage-column"><?php echo 'Path'; ?></th><th scope="col" class="manage-column"><?php echo 'Change'; ?></th></tr></thead>
		<tfoot><tr><th scope="col" class="manage-column"><?php echo 'Path'; ?></th><th scope="col" class="manage-column"><?php echo 'Change'; ?></th></tr></tfoot>
		<tbody>
		<?php
		if ( empty( $changes ) ) :
			echo '<tr><td><p>Nothing to commit, working directory clean.</p></td></tr>';
		else :
			$this->show_git_changes_table_rows( $changes );
		endif;
		?>
		</tbody>
		</table>
		<?php
	}
    
   private function show_git_changes_table_submit_buttons( $changes ) {
        // Get local status and behind commits
        $local_status = $this->git->local_status();
        $behind_commits = count( $this->git->get_behind_commits() );

        // Determine button value based on conditions
        if ( $behind_commits > 0 && !empty( $local_status[1] ) ) {
            $button_value = 'Pull & Push changes';
        } else if ( $behind_commits > 0 ) {
            $button_value = 'Pull changes';
        } else if ( !empty( $local_status[1] ) ) {
            $button_value = 'Push changes';
        }

        // Check if there are any changes to display the form
        if ( !empty( $changes ) ) : ?>
            <p>
                <label for="save-changes"><?php echo 'Commit message'; ?>:</label>
                <input type="text" name="commitmsg" id="save-changes" class="widefat" value="" placeholder="<?php printf( 'Merged changes from %s on %s', esc_url(get_site_url()), esc_html(date( 'm.d.Y' ) )); ?>" />
            </p>
            <p>
                <input type="submit" name="GitiumSubmitSaveChanges" class="button-primary button" value="<?php echo esc_html( $button_value ); ?>" <?php if ( get_transient( 'gitium_remote_disconnected' ) ) { echo 'disabled="disabled" '; } ?>/>&nbsp;
            </p>
        <?php endif;
    }

	private function changes_page() {
		list( , $changes ) = _gitium_status();
		?>
		<div class="wrap">
		<div id="icon-options-general" class="icon32">&nbsp;</div>
		<h2><?php echo 'Status'; ?> <code class="small" style="background-color:forestgreen; color:whitesmoke;"><?php echo 'connected to'; ?> <strong><?php echo esc_html( $this->git->get_remote_url() ); ?></strong></code></h2>

		<form name="form_status" id="form_status" action="" method="POST">
		<?php
			wp_nonce_field( 'gitium-admin' );
			$this->show_ahead_and_behind_info( $changes );
			$this->show_git_changes_table( $changes );
			$this->show_git_changes_table_submit_buttons( $changes );
		?>
		</form>
		<?php
			$this->show_disconnect_repository_button();
		?>
		</div>
		<?php
	}

	public function page() {
		$this->show_message();
		_gitium_status( true );
		$this->changes_page();
	}
}