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

class Gitium_Help {

	public function __construct( $hook, $help = 'gitium' ) {
		add_action( "load-{$hook}", array( $this, $help ), 20 );
	}

	private function general() {
		$screen = get_current_screen();
		$screen->add_help_tab( array( 'id' => 'gitium', 'title' => 'Gitium', 'callback' => array( $this, 'gitium' ) ) );
		$screen->add_help_tab( array( 'id' => 'faq', 'title' => 'F.A.Q.', 'callback' => array( $this, 'faq' ) ) );
		$screen->add_help_tab( array( 'id' => 'requirements', 'title' => 'Requirements', 'callback' => array( $this, 'requirements_callback' ) ) );
		$screen->set_help_sidebar( '<div style="width:auto; height:auto; float:right; padding-right:28px; padding-top:15px"><img src="' . plugins_url( 'img/gitium.svg', dirname( __FILE__ ) ) . '" width="96"></div>' );
	}

	public function gitium() {
		echo '<p>Gitium enables continuous deployment for WordPress integrating with tools such as Github, Bitbucket or Travis-CI. Plugin and theme updates, installs and removals are automatically versioned.</p>';
		echo '<p>Ninja code edits from the WordPress editor are also tracked into version control. Gitium is designed for sane development environments.</p>';
		echo '<p>Staging and production can follow different branches of the same repository. You can deploy code simply trough git push.</p>';
		echo '<p>Gitium requires <code>git</code> command line tool minimum version 1.7 installed on the server and <code>proc_open</code> PHP function enabled.</p>';
	}

	public function faq() {
		echo '<p><strong>Could not connect to remote repository?</strong><br/>If you encounter this kind of error you can try to fix it by setting the proper username of the .git directory.<br />Example: <code>chown -R www-data:www-data .git</code></p>';
		echo '<p><strong>Is this plugin considered stable?</strong><br />Right now this plugin is considered alpha quality and should be used in production environments only by adventurous kinds.</p>';
		echo '<p><strong>What happens in case of conflicts?</strong><br />The behavior in case of conflicts is to overwrite the changes on the origin repository with the local changes (ie. local modifications take precedence over remote ones).</p>';
		echo '<p><strong>How to deploy automatically after a push?</strong><br />You can ping the webhook url after a push to automatically deploy the new code. The webhook url can be found under Code menu. This url plays well with Github or Bitbucket webhooks.</p>';
		echo '<p><strong>Does it works on multi site setups?</strong><br />Gitium is not supporting multisite setups at the moment.</p>';
		echo '<p><strong>How does gitium handle submodules?</strong><br />Currently submodules are not supported.</p>';
	}

	public function requirements_callback() {
		echo '<p>Gitium requires:</p>';
		echo '<p>the function proc_open available</p>';
		echo '<p>can exec the file inc/ssh-git</p>';

		printf( '<p>git version >= %s</p>', esc_html( GITIUM_MIN_GIT_VER ) );
		printf( '<p>PHP version >= %s</p>', esc_html( GITIUM_MIN_PHP_VER ) );
	}

	public function configuration() {
		$screen = get_current_screen();
		$screen->add_help_tab( array( 'id' => 'configuration', 'title' => 'Configuration', 'callback' => array( $this, 'configuration_callback' ) ) );
		$this->general();
	}

	public function configuration_callback() {
		echo '<p><strong>Configuration step 1</strong><br />In this step you must specify the <code>Remote URL</code>. This URL represents the link between the git sistem and your site.</p>';
		echo '<p>You can get this URL from your Git repository and it looks like this:</p>';
		echo '<p>github.com -> git@github.com:user/example.git</p>';
		echo '<p>bitbucket.org -> git@bitbucket.org:user/glowing-happiness.git</p>';
		echo '<p>To go to the next step, fill the <code>Remote URL</code> and then press the <code>Fetch</code> button.</p>';
		echo '<p><strong>Configuration step 2</strong><br />In this step you must select the <code>branch</code> you want to follow.</p>';
		echo '<p>Only this branch will have all of your code modifications.</p>';
		echo '<p>When you push the button <code>Merge & Push</code>, all code(plugins & themes) will be pushed on the git repository.</p>';
	}

	public function status() {
		$screen = get_current_screen();
		$screen->add_help_tab( array( 'id' => 'status', 'title' => 'Status', 'callback' => array( $this, 'status_callback' ) ) );
		$this->general();
	}

	public function status_callback() {
		echo '<p>On status page you can see what files are modified, and you can commit the changes to git.</p>';
	}

	public function commits() {
		$screen = get_current_screen();
		$screen->add_help_tab( array( 'id' => 'commits', 'title' => 'Commits', 'callback' => array( $this, 'commits_callback' ) ) );
		$this->general();
	}

	public function commits_callback() {
		echo '<p>You may be wondering what is the difference between author and committer.</p>';
		echo '<p>The <code>author</code> is the person who originally wrote the patch, whereas the <code>committer</code> is the person who last applied the patch.</p>';
		echo '<p>So, if you send in a patch to a project and one of the core members applies the patch, both of you get credit — you as the author and the core member as the committer.</p>';
	}

	public function settings() {
		$screen = get_current_screen();
		$screen->add_help_tab( array( 'id' => 'settings', 'title' => 'Settings', 'callback' => array( $this, 'settings_callback' ) ) );
		$this->general();
	}

	public function settings_callback() {
		echo '<p>Each line from the gitignore file specifies a pattern.</p>';
		echo '<p>When deciding whether to ignore a path, Git normally checks gitignore patterns from multiple sources, with the following order of precedence, from highest to lowest (within one level of precedence, the last matching pattern decides the outcome)</p>';
		echo '<p>' . sprintf( 'Read more on %s', '<a href="http://git-scm.com/docs/gitignore" target="_blank">git documentation</a>' ) . '</p>';
	}
}
