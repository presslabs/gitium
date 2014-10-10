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

	public function __construct() {
		global $git;

		list( , $git_private_key ) = gitium_get_keypair();
		$git->set_key( $git_private_key );

		if ( current_user_can( 'manage_options' ) ) { // admin actions
			new Gitium_Submenu_Status();

			// those menus are visibile only when it is all set up
			if ( $git->is_versioned() && $git->get_remote_tracking_branch() ) {
				new Gitium_Menu_Bubble();
				new Gitium_Submenu_Commits();
				new Gitium_Submenu_Gitignore();
			}
		}
	}
}

if ( is_admin() ) {
	add_action( 'init', 'gitium_admin_page' );
	function gitium_admin_page() {
		new Gitium_Admin();
	}
}
