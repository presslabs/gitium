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

class Gitium_Submenu_Commits extends Gitium_Menu {

	public function __construct() {
		parent::__construct( $this->gitium_menu_slug, $this->commits_menu_slug );
		add_action( GITIUM_ADMIN_MENU_ACTION, array( $this, 'admin_menu' ) );
	}

	public function admin_menu() {
		$submenu_hook = add_submenu_page(
			$this->menu_slug,
			'Git Commits',
			'Commits',
			GITIUM_MANAGE_OPTIONS_CAPABILITY,
			$this->submenu_slug,
			array( $this, 'page' )
		);
		new Gitium_Help( $submenu_hook, 'commits' );
	}

	public function table_head() {
		?>
		<thead>
		<tr>
			<th scope="col"><?php echo 'Commits'; ?></th>
			<th scope="col"></th>
		</tr>
		</thead>
		<?php
	}

	public function table_end_row() {
		echo '</tr>';
	}

	public function table_start_row() {
		static $counter = 0;
		$counter++;
		echo ( 0 != $counter % 2 ) ? '<tr class="active">' : '<tr class="inactive">';
	}

	public function page() {
		?>
		<div class="wrap">
			<h2><?php printf( 'Last %s commits', esc_html( GITIUM_LAST_COMMITS ) ); ?></h2>
			<table class="wp-list-table widefat plugins">
				<?php $this->table_head(); ?>
				<tbody>
					<?php
					foreach ( $this->git->get_last_commits( GITIUM_LAST_COMMITS ) as $commit_id => $data ) {
						unset( $committer_name );
						extract( $data );
	
						// Prepare committer HTML
						if ( isset( $committer_name ) ) {
							$committer = sprintf(
								'<span title="%s"> -> %s %s</span>',
								esc_attr( $committer_email ),
								esc_html( $committer_name ),
								sprintf( esc_html( 'committed %s ago'), human_time_diff( strtotime( $committer_date ) ) )
							);
	
							$committers_avatar = sprintf(
								'<div style="position:absolute; left:30px; top:30px; border:1px solid white; background:white; height:17px; border-radius:2px;">%s</div>',
								get_avatar( $committer_email, 16 )
							);
						} else {
							$committer = '';
							$committers_avatar = '';
						}
	
						$this->table_start_row();
						?>
						<td style="position:relative;">
							<div style="float:left; width:auto; height:auto; padding:2px 5px 0 2px; margin-right:5px; border-radius:2px;">
								<?php echo get_avatar( $author_email, 32 ); ?>
							</div>
							<?php echo wp_kses_post( $committers_avatar ); ?>
							<div style="float:left; width:auto; height:auto;">
								<strong><?php echo esc_html( $subject ); ?></strong><br />
								<span title="<?php echo esc_attr( $author_email ); ?>">
									<?php
									echo esc_html( $author_name ) . ' ';
									printf(
										esc_html( 'authored %s ago'),
										esc_html( human_time_diff( strtotime( $author_date ) ) )
									);
									?>
								</span>
								<?php echo wp_kses_post( $committer ); ?>
							</div>
						</td>
						<td>
							<p style="padding-top:8px;"><?php echo esc_html( $commit_id ); ?></p>
						</td>
						<?php
						$this->table_end_row();
					}
					?>
				</tbody>
			</table>
		</div>
		<?php
	}
	
}
