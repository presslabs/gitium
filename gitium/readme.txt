=== Gitium ===

Contributors: PressLabs
Donate link: https://www.presslabs.com/gitium/
Tags: git, version, versioning, deployment, version-control, github, bitbucket, travis, code, revision, testing, development, branch, production, staging, debug, plugin, gitium, presslabs, simple
Requires at least: 3.9
Tested up to: 5.2.2
Requires PHP: 5.6
License: GPLv2
Stable tag: 1.0.3
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatic git version control and deployment for your plugins and themes integrated into wp-admin.


== Description ==

Gitium enables continuous deployment for WordPress, integrating with tools such as Github, Bitbucket or Travis-CI. Theme or plugin updates, installs and removals are all automatically versioned. Ninja code edits from the WordPress editor are also tracked by the version control system.

Gitium is designed with sane development environments in mind, allowing staging and production to follow different branches of the same repository. You can also deploy code by simply using `git push`.

Gitium requires `git` command line tool with a minimum version of 1.7 installed on the server and the `proc_open` PHP function enabled.

You can find more documentation on [Presslabs](https://www.presslabs.com/help/gitium/general).


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

Activate the plugin and follow the on-screen instructions under the `Gitium` menu.

_IMPORTANT_: Gitium does its best not to version your WordPress core, neither your `/wp-content/uploads` folder.

== Frequently Asked Questions ==

= Could not connect to remote repository? =

If you encounter this kind of error you can try to fix it by setting the proper username of the .git directory.

Example: chown -R www-data:www-data .git

= Is this plugin considered stable? =

Yes, we consider the plugin stable after extensive usage in production environments at Presslabs, with hundreds of users and powering sites with hundreds of millions of pageviews per month.

= What will happen in case of conflicts? =

The behavior in case of conflicts is to overwrite the changes on the `origin` repository with the local changes (ie. local modifications take precedence over remote ones).

= How to deploy automatically after a push? =

You can ping the webhook url after a push to automatically deploy the new code. The webhook url can be found under `Gitium` menu, `Settings` section. This url also plays well with Github or Bitbucket webhooks.

= Does it works on multi site setups? =

Gitium does not support multisite setups at the moment.

= How does gitium handle submodules? =

Submodules are currently not supported.

== Upgrade Notice ==
= 1.0.3 =
Fixed wrong redirection for multisite installations during initial setup

== Changelog ==
= 1.0.3 =
* Fixed wrong redirection for multisite installations during initial setup

= 1.0.2 =
* Full PHP 7+ compatibility
* Hotfix - Fixed the blank pages being displayed instead of success of failure messages;
* Hotfix - Fixed the push process when other remote branches had changes;
* Hotfix - Fixed the missing ssh / key handling with fatal errors during activation;
* Added - More success messages in certain cases.

= 1.0.1 =
* Hotfix - Fix race condition on Code Editor Save

= 1.0 =
* Fixed WP 4.9 Compatibility

= 1.0-rc12 =
* Bumped plugin version

= 1.0-rc11 =
* Hotfixed an error that prevented gitium to error_log properly.

= 1.0-rc10 =
* Bumped wordpress tested version

= 1.0-rc9 =
* PHP7 compat and wp-cli

= 1.0-rc8 =
* Fix some indents
* Add some more tests
* Fix the submenu configure logic

= 1.0-rc7 =
* Test remote url from git wrapper
* Remove the phpmd package from test environment
* Set WP_DEBUG to false on tests
* Refactoring
* Abort the cherry-pick - changes are already there
* Fix the race condition
* Add acquire and release logs for gitium lock
* Add explanations to merge with accept mine logic

= 1.0-rc6 =
* Delete all transients and options on uninstall hook
* Add transients to is_versions and get_remote_tracking_branch functions
* Update the composer
* Check requirements before show the admin menu
* Put the logs off by default(on test env)
* Fix redirect issue and display errors
* Create wordpress docker env command
* PHP Warning: unlink #114

= 1.0-rc5 =
* Fix delete plugin/theme bug on 4.6
* Update the readme file

= 1.0-rc4 =
* Fix merge with accept mine behind commits bug

= 1.0-rc3 =
* Add support for multisite
* Fix PHP error on merge & push

= 1.0-rc2 =
* Change the default lockfile location
* Fix a PHP Warning

= 1.0-rc1 =
* Update the logic of merge and push
* Add lock mechanism for fetch and merge
* Fix repo stuck on merge_local branch
* Tested up to 4.5.3

= 0.5.8-beta =
* Add documentation for 'Could not connect to remote repository?'
* Fix the update theme from Dashboard commit message & the install plugin commit message
* Fix install/delete plugin/theme commit message
* Add a test and rewrite the tests
* Tested up to 4.5.2

= 0.5.7-beta =
* Fix bug deleting plugins/themes causes wrong commit message
* Fix bug wrong commit message
* Fix bug updated function to stop maintenance mode hang
* Fix bug undefined variable 'new_versions'
* Add 'Merge changes' button for gitium webhook
* Add gitium documentation for docker
* Add more tests

= 0.5.6-beta =
* Fix compatibility issues with wp-cli

= 0.5.5-beta =
* Fix bug plugin deletion from plugins page did not trigger commit

= 0.5.4-beta =
* Fix bug missing changes on similarly named plugins
* Add requirements notices
* Add requirements help section

= 0.5.3-beta =
* Fix paths with spaces bug
* Add a Disconnect from repo button
* Fix POST var `path` conflicts
* Fix travis tests

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
