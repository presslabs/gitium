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

class Gitium_Help {

	public function __construct( $hook, $help = 'gitium' ) {
		add_action( "load-{$hook}", array( $this, $help ), 20 );
	}

	private function general() {
		$screen = get_current_screen();
		$screen->add_help_tab( array( 'id' => 'gitium', 'title' => __( 'Gitium', 'gitium' ), 'callback' => array( $this, 'gitium' ) ) );
		$screen->add_help_tab( array( 'id' => 'faq', 'title' => __( 'F.A.Q.', 'gitium' ), 'callback' => array( $this, 'faq' ) ) );
		$screen->add_help_tab( array( 'id' => 'requirements', 'title' => __( 'Requirements', 'gitium' ), 'callback' => array( $this, 'requirements_callback' ) ) );
		$screen->set_help_sidebar( '<div style="width:auto; height:auto; float:right; padding-right:28px; padding-top:15px"><img src="' . plugins_url( 'img/gitium.svg', dirname( __FILE__ ) ) . '" width="96"></div>' );
	}

	public function gitium() {
		echo '<p>' . __( 'Gitium enables continuous deployment for WordPress integrating with tools such as Github, Bitbucket or Travis-CI. Plugin and theme updates, installs and removals are automatically versioned.', 'gitium' ) . '</p>';
		echo '<p>' . __( 'Ninja code edits from the WordPress editor are also tracked into version control. Gitium is designed for sane development environments.', 'gitium' ) . '</p>';
		echo '<p>' . __( 'Staging and production can follow different branches of the same repository. You can deploy code simply trough git push.', 'gitium' ) . '</p>';
		echo '<p>' . __( 'Gitium requires <code>git</code> command line tool minimum version 1.7 installed on the server and <code>proc_open</code> PHP function enabled.', 'gitium' ) . '</p>';
	}

	public function faq() {
		echo '<p><strong>' . __( 'Could not connect to remote repository?', 'gitium' ) . '</strong><br />'. __( 'If you encounter this kind of error you can try to fix it by setting the proper username of the .git directory.', 'gitium' ) . '<br />' . __( 'Example', 'gitium' ) .': <code>chown -R www-data:www-data .git</code></p>';
		echo '<p><strong>' . __( 'Is this plugin considered stable?', 'gitium' ) . '</strong><br />'. __( 'Right now this plugin is considered alpha quality and should be used in production environments only by adventurous kinds.', 'gitium' ) . '</p>';
		echo '<p><strong>' . __( 'What happens in case of conflicts?', 'gitium' ) . '</strong><br />'. __( 'The behavior in case of conflicts is to overwrite the changes on the origin repository with the local changes (ie. local modifications take precedence over remote ones).', 'gitium' ) . '</p>';
		echo '<p><strong>' . __( 'How to deploy automatically after a push?', 'gitium' ) . '</strong><br />'. __( 'You can ping the webhook url after a push to automatically deploy the new code. The webhook url can be found under Code menu. This url plays well with Github or Bitbucket webhooks.', 'gitium' ) . '</p>';
		echo '<p><strong>' . __( 'Does it works on multi site setups?', 'gitium' ) . '</strong><br />'. __( 'Gitium is not supporting multisite setups at the moment.', 'gitium' ) . '</p>';
		echo '<p><strong>' . __( 'How does gitium handle submodules?', 'gitium' ) . '</strong><br />'. __( 'Currently submodules are not supported.', 'gitium' ) . '</p>';
	}

	public function requirements_callback() {
		echo '<p>' . __( 'Gitium requires:', 'gitium' ) . '</p>';
		echo '<p>' . __( 'the function proc_open available', 'gitium' ) . '</p>';
		echo '<p>' . __( 'can exec the file inc/ssh-git', 'gitium' ) . '</p>';

		printf( '<p>' . __( 'git version >= %s', 'gitium' ) . '</p>', GITIUM_MIN_GIT_VER );
		printf( '<p>' . __( 'PHP version >= %s', 'gitium' ) . '</p>', GITIUM_MIN_PHP_VER );
	}

	public function configuration() {
		$screen = get_current_screen();
		$screen->add_help_tab( array( 'id' => 'configuration', 'title' => __( 'Configuration', 'gitium' ), 'callback' => array( $this, 'configuration_callback' ) ) );
		$this->general();
	}

	public function configuration_callback() {
		echo '<p><strong>' . __( 'Configuration step 1', 'gitium' ) . '</strong><br />' . __( 'In this step you must specify the <code>Remote URL</code>. This URL represents the link between the git sistem and your site.', 'gitium' ) . '</p>';
		echo '<p>' . __( 'You can get this URL from your Git repository and it looks like this:', 'gitium' ) . '</p>';
		echo '<p>' . __( 'github.com -> git@github.com:user/example.git', 'gitium' ) . '</p>';
		echo '<p>' . __( 'bitbucket.org -> git@bitbucket.org:user/glowing-happiness.git', 'gitium' ) . '</p>';
		echo '<p>' . __( 'To go to the next step, fill the <code>Remote URL</code> and then press the <code>Fetch</code> button.', 'gitium' ) . '</p>';
		echo '<p><strong>' . __( 'Configuration step 2', 'gitium' ) . '</strong><br />' . __( 'In this step you must select the <code>branch</code> you want to follow.', 'gitium' ) . '</p>';
		echo '<p>' . __( 'Only this branch will have all of your code modifications.', 'gitium' ) . '</p>';
		echo '<p>' . __( 'When you push the button <code>Merge & Push</code>, all code(plugins & themes) will be pushed on the git repository.', 'gitium' ) . '</p>';
	}

	public function status() {
		$screen = get_current_screen();
		$screen->add_help_tab( array( 'id' => 'status', 'title' => __( 'Status', 'gitium' ), 'callback' => array( $this, 'status_callback' ) ) );
		$this->general();
	}

	public function status_callback() {
		echo '<p>' . __( 'On status page you can see what files are modified, and you can commit the changes to git.', 'gitium' ) . '</p>';
	}

	public function commits() {
		$screen = get_current_screen();
		$screen->add_help_tab( array( 'id' => 'commits', 'title' => __( 'Commits', 'gitium' ), 'callback' => array( $this, 'commits_callback' ) ) );
		$this->general();
	}

	public function commits_callback() {
		echo '<p>' . __( 'You may be wondering what is the difference between author and committer.', 'gitium' ) . '</p>';
		echo '<p>' . __( 'The <code>author</code> is the person who originally wrote the patch, whereas the <code>committer</code> is the person who last applied the patch.', 'gitium' ) . '</p>';
		echo '<p>' . __( 'So, if you send in a patch to a project and one of the core members applies the patch, both of you get credit — you as the author and the core member as the committer.', 'gitium' ) . '</p>';
	}

	public function settings() {
		$screen = get_current_screen();
		$screen->add_help_tab( array( 'id' => 'settings', 'title' => __( 'Settings', 'gitium' ), 'callback' => array( $this, 'settings_callback' ) ) );
		$this->general();
	}

	public function settings_callback() {
		echo '<p>' . __( 'Each line from the gitignore file specifies a pattern.', 'gitium' ) . '</p>';
		echo '<p>' . __( 'When deciding whether to ignore a path, Git normally checks gitignore patterns from multiple sources, with the following order of precedence, from highest to lowest (within one level of precedence, the last matching pattern decides the outcome)', 'gitium' ) . '</p>';
		echo '<p>' . sprintf( __( 'Read more on %s', 'gitium' ), '<a href="http://git-scm.com/docs/gitignore" target="_blank">git documentation</a>' ) . '</p>';
	}
}
