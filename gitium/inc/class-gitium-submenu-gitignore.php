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

	private $gitignore_filename = '.gitignore';

	public function __construct() {
		parent::__construct( $this->gitium_menu_slug, $this->gitignore_menu_slug );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'save_gitignore' ) );
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
		<textarea name="gitignore_content" rows="40" cols="120"><?php echo esc_html( $this->git->get_gitignore() ); ?></textarea>

		<p class="submit">
		<input type="submit" name="SubmitSaveGitignore" class="button-primary" value="<?php _e( 'Save', 'gitium' ); ?>" />
		</p>

		</form>
		</div>
		<?php
	}

}
