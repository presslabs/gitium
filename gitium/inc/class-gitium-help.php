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

class Gitium_Help {

	public $tabs = array(
		'GITIUM_OVERVIEW' => array(
			'general' => true,
			'title'   => 'Gitium',
			'help'    => array(
				'Gitium enables continuous deployment for WordPress integrating with tools such as Github, Bitbucket or Travis-CI. Plugin and theme updates, installs and removals are automatically versioned.',
				'Ninja code edits from the WordPress editor are also tracked into version control. Gitium is designed for sane development environments.',
				'Staging and production can follow different branches of the same repository. You can deploy code simply trough git push.',
				'Gitium requires <code>git</code> command line tool minimum version 1.7 installed on the server and <code>proc_open</code> PHP function enabled.',
			),
		),
		'GITIUM_FAQ' => array(
			'general' => true,
			'title'   => 'F.A.Q.',
			'help'    => array(
				'<strong>Is this plugin considered stable?</strong><br />Right now this plugin is considered alpha quality and should be used in production environments only by adventurous kinds.',
				'<strong>What happens in case of conflicts?</strong><br />The behavior in case of conflicts is to overwrite the changes on the origin repository with the local changes (ie. local modifications take precedence over remote ones).',
				'<strong>How to deploy automatically after a push?</strong><br />You can ping the webhook url after a push to automatically deploy the new code. The webhook url can be found under Code menu. This url plays well with Github or Bitbucket webhooks.',
				'<strong>Does it works on multi site setups?</strong><br />Gitium is not supporting multisite setups at the moment.',
				'<strong>How does gitium handle submodules?</strong><br />Currently submodules are not supported.',
			)
		),
		'GITIUM_STATUS' => array(
			'general' => false,
			'title'   => 'Status',
			'help'    => array(
				'On status page you can see what files are modified, and you can commit those changes to git.'
			),
		),
		'GITIUM_COMMITS' => array(
			'general' => false,
			'title'   => 'Commits',
			'help'    => array(
				'You may be wondering what the difference is between author and committer.',
				'The <code>author</code> is the person who originally wrote the patch, whereas the <code>committer</code> is the person who last applied the patch.',
				'So, if you send in a patch to a project and one of the core members applies the patch, both of you get credit — you as the author and the core member as the committer.',
			),
		),
		'GITIUM_GITIGNORE' => array(
			'general' => false,
			'title'   => 'Gitignore',
			'help'    => array(
				'<span style="color:red;">Be careful when you modify this list!</span>',
				'Each line in a gitignore file specifies a pattern.',
				'When deciding whether to ignore a path, Git normally checks gitignore patterns from multiple sources, with the following order of precedence, from highest to lowest (within one level of precedence, the last matching pattern decides the outcome)',
				'Read more on <a href="http://git-scm.com/docs/gitignore" target="_blank">git documentation</a>',
			),
		),
	);

	private function filter_tabs( $selected_tab ) {
		return array_merge( array( $selected_tab => $this->tabs[ $selected_tab ] ), array_filter( $this->tabs, function( $data ) { return true === $data['general']; } ) );
	}

	public function __construct( $hook, $selected_tab = 'GITIUM_OVERVIEW' ) {
		add_action(
			"load-{$hook}",
			function () use ( $selected_tab ) {
				$screen = get_current_screen();
				$tabs   = $this->filter_tabs( $selected_tab );
				foreach ( $tabs as $key => $data ) {
					$screen->add_help_tab( array( 'id' => $key, 'title' => __( $data['title'], 'gitium' ), 'callback' => array( $this, 'prepare' ) ) );
				}
				$screen->set_help_sidebar( '<div style="width:auto; height:auto; float:right; padding-right:28px; padding-top:15px"><img src="http://cdn.presslabs.com/wp-content/themes/presslabs/img/gitium/gitium.svg" width="96"></div>' );
			},
			20
		);
	}

	public function prepare( $screen, $tab ) {
		unset( $screen );
		foreach ( $tab['callback'][0]->tabs[ $tab['id'] ]['help'] as $help ) {
			printf( '<p>%s</p>', __( $help, 'gitium' ) );
		}
	}
}
