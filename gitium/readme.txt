=== Gitium ===

Contributors: PressLabs
Donate link: http://www.presslabs.com/gitium/
Tags: git, version, versioning, deployment, version-control, github, bitbucket, travis, code, revision, testing, development, branch, production, staging, debug, plugin, gitium, presslabs, simple
Requires at least: 3.9
Tested up to: 4.1.2
Stable tag: 0.5.2-beta
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatic git version control and deployment for your plugins and themes integrated into wp-admin.

== Description ==

Gitium enables continuous deployment for WordPress, integrating with tools such as Github, Bitbucket or Travis-CI. Theme or plugin updates, installs and removals are all automatically versioned. Ninja code edits from the WordPress editor are also tracked by the version control system.

Gitium is designed with sane development environments in mind, allowing staging and production to follow different branches of the same repository. You can also deploy code by simply using `git push`.

Gitium requires `git` command line tool with a minimum version of 1.7 installed on the server and the `proc_open` PHP function enabled.

Gitium is the latest element discovered at [PressLabs](http://www.presslabs.com/gitium/).

== Screenshots ==

1. Setup step 1: Get SSH Key
2. Setup step 2: Set SSH Key (Github)
3. Setup step 3: Add remote repository
4. Setup step 4: Choose following branch
5. Commit local changes

== Installation ==

= Manual Installation =
1. Upload `gitium.zip` to the `/wp-content/plugins/` directory;
2. Extract the `gitium.zip` archive into the `/wp-content/plugins/` directory;
3. Activate the plugin through the 'Plugins' menu in WordPress.

Alternatively, go into your WordPress dashboard and click on Plugins -> Add Plugin and search for `Gitium`. Then, click on Install and, after that, on Activate Now.

= Usage =

Activate the plugin and follow the on-screen instructions under the `Code` menu.

_IMPORTANT_: Gitium does its best not to version your WordPress core, neither your `/wp-content/uploads` folder.

== Frequently Asked Questions ==

= Is this plugin considered stable? =

Right now this plugin is considered alpha quality and should be used in production environments only by adventurous kinds.

= What will happen in case of conflicts? =

The behavior in case of conflicts is to overwrite the changes on the `origin` repository with the local changes (ie. local modifications take precedence over remote ones).

= How to deploy automatically after a push? =

You can ping the webhook url after a push to automatically deploy the new code. The webhook url can be found under `Code` menu. This url also plays well with Github or Bitbucket webhooks.

= Does it works on multi site setups? =

Gitium does not support multisite setups at the moment.

= How does gitium handle submodules? =

Submodules are currently not supported.


== Changelog ==

= 0.5.2-beta =
* Add Contextual Help to Configuration page
* Make the icon path relative
* The key file is deleted properly
* Update serbian translation
* Make the resource type more specific
* Fix Menu Bubble
* Remove useless param for get_transient
* Add Spanish Translation
* Rename `gitium_version` transient
* Fix git version notice
* Delete .vimrc
* Update .gitignore
* Fix syntax error
* Add better git version check
* Fix add_query_arg vulnerability

= 0.5.1-beta =
* Update Serbian Translation (by [Ogi Djuraskovic](http://firstsiteguide.com/))
* Fix Menu Bubble

= 0.5-beta =
* Add `Last 20 Commits` menu page
* Add WordPress Contextual Help menu
* Add `Settings` menu page
* Move `Webhook URL` and `Public Key` fields to `Settings` page
* Add menu icon
* The `.gitignore` file can be edited
* Fix commit message on theme/plugin update event
* Refactoring

= 0.4-beta =
* Add `Bitbucket` documentation link
* Add the action `gitium_before_merge_with_accept_mine`
* Moved to `travis-ci.org`
* Add new tests
* Added code climate coverage reporting
* Refactoring

= 0.3.2-alpha =
* Fix plugin activation issues

= 0.3.1-alpha =
* Fix issues with ssh repositories
* Fix maintemance mode when webhook fails

= 0.3-alpha =
* First alpha release
