<?php
/*  Copyright 2014-2016 Presslabs SRL <ping@presslabs.com>

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

class Gitium_Menu_Bubble extends Gitium_Menu {

	public function __construct() {
		parent::__construct( $this->gitium_menu_slug, $this->gitium_menu_slug );
		add_action( GITIUM_ADMIN_MENU_ACTION, array( $this, 'add_menu_bubble' ) );
	}

	public function add_menu_bubble() {
		global $menu;

		if ( ! _gitium_is_status_working()  ) {
			foreach ( $menu as $key => $value  ) {
				if ( $this->menu_slug == $menu[ $key ][2] ) {
					$menu_bubble = get_transient( 'gitium_menu_bubble' );
					if ( false === $menu_bubble ) { $menu_bubble = ''; }
					$menu[ $key ][0] = str_replace( $menu_bubble, '', $menu[ $key ][0] );
					delete_transient( 'gitium_menu_bubble' );
					return;
				}
			}
		}

		list( , $changes ) = _gitium_status();

		if ( ! empty( $changes ) ) :
			$bubble_count = count( $changes );
			foreach ( $menu as $key => $value  ) {
				if ( $this->menu_slug == $menu[ $key ][2] ) {
					$menu_bubble = " <span class='update-plugins count-$bubble_count'><span class='plugin-count'>"
						. $bubble_count . '</span></span>';
					$menu[ $key ][0] .= $menu_bubble;
					set_transient( 'gitium_menu_bubble', $menu_bubble );
					return;
				}
			}
		endif;
	}
}
