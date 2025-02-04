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
class Gitium_Admin {

	public function __construct() {
		global $git;

		list( , $git_private_key ) = gitium_get_keypair();
		$git->set_key( $git_private_key );

		if ( current_user_can( GITIUM_MANAGE_OPTIONS_CAPABILITY ) ) {
			$req = new Gitium_Requirements();
			if ( ! $req->get_status() ) {
				return false;
			}

			if ( $this->has_configuration() ) {
				new Gitium_Submenu_Status();
				new Gitium_Submenu_Commits();
				new Gitium_Submenu_Settings();
				new Gitium_Menu_Bubble();
			} else {
				new Gitium_Submenu_Configure();
			}
		}
	}

	public function has_configuration() {
		return _gitium_is_status_working() && _gitium_get_remote_tracking_branch();
	}
}

if ( ( is_admin() && ! is_multisite() ) || ( is_network_admin() && is_multisite() ) ) {
	add_action( 'init', 'gitium_admin_page' );
	function gitium_admin_page() {
		new Gitium_Admin();
	}
}
