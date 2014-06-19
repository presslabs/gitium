=== Gitium ===

Contributors: PressLabs
Donate link: http://www.presslabs.com/
Tags: git, version, versioning, deployment, version-control, github, bitbucker, travis, code, revision, testing, development, branch, production, staging, debug
Requires at least: 3.9
Tested up to: 3.9.1
Stable tag: 0.3-alpha
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatic git version control and deployment for your plugins and themes integrated into wp-admin.

== Description ==

Gitium enables continuous deployment for WordPress integrating with tools such as Github, Bitbucket or Travis-CI. Plugin and theme updates, installs and removals are automatically versioned. Ninja code edits from the WordPress editor are also tracked into version control. Gitium is designed for sane development environments. Staging and production can follow different branches of the same repository. You can deploy code simply trough git push.

Gitium requires `git` command line tool minimum version 1.7 installed on the server and `proc_open` PHP function enabled.

Gitium is the latest element discovered in the [PressLabs](http://www.presslabs.com).

== Screenshots ==

1. Setup step 1: Add remote repository
2. Setup step 2: Choose following branch
3. Commit local changes

== Installation ==

= Manual Installation =
1. Upload `gitium.zip` to the `/wp-content/plugins/` directory;
2. Extract the `gitium.zip` archive into the `/wp-content/plugins/` directory;
3. Activate the plugin through the 'Plugins' menu in WordPress.

Alternatively go into your WordPress dashboard and click on Plugins -> Add Plugin and search for `Gitium`. Then click on Install, then on Activate Now.

= Usage =

Activate the plugin and follow the on screen instructions under the `Code` menu.

_IMPORTANT_: Gitium does it's best not to version your WordPress core neither your `/wp-content/uploads` folder.

== Frequently Asked Questions ==

= Is this plugin considered stable? =

Right now this plugin is considered alpha quality and should be used in production environments only by adventurous kinds.

= What happens in case of conflicts? =

The behavior in case of conflicts is to overwrite the changes on the `origin` repository with the local changes (ie. local modifications take precedence over remote ones).

= How to deploy automatically after a push? =

You can ping the webhook url after a push to automatically deploy the new code. The webhook url can be found under `Code` menu. This url plays well with Github or Bitbucket webhooks.

= Does it works on multi site setups? =

Gitium is not supporting multisite setups at the moment.

= How does gitium handle submodules? =

Currently submodules are not supported.


== Changelog ==

= 0.3-alpha =
* First alpha release
