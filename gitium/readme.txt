=== Gitium ===

Contributors: PressLabs
Donate link: https://www.presslabs.com/gitium/
Tags: git, version control, revision, gitium, presslabs
Requires at least: 4.7
Tested up to: 6.8
Requires PHP: 7.4
License: GPLv3
Stable tag: 1.2.1
License URI: https://www.gnu.org/licenses/gpl-3.0.en.html

Automatic git version control and deployment for your plugins and themes integrated into wp-admin.

== About the makers ==
This plugin was developed by the crafty people at Presslabs—the Smart Managed WordPress Hosting Platform. Here we bring high-performance hosting and business intelligence for WordPress sites. In our spare time, we contribute to the global open-source community with our code.

We’ve built Gitium back in 2013 to provide our clients a more simple and error-free method to integrate a new git version control into their code management flow.

== What is Gitium? ==

This plugin enables continuous deployment for WordPress, integrating with tools such as Github, Bitbucket or Travis-CI. Theme or plugin updates, installs and removals are all automatically versioned. Ninja code edits from the WordPress editor are also tracked by the version control system.

== Why is Gitium? ==

Gitium is designed with responsible development environments in mind, allowing staging and production to follow different branches of the same repository. You can also deploy code by simply using git push.

Gitium requires git command line tool with a minimum version of 1.7 installed on the server and the proc_open PHP function enabled.

== Gitium features: ==
-preserves the WordPress behavior
-accountability for code changes
-safe code storage—gets all code edits in Git

== Development ==
For more details about Gitium, head here: http://docs.presslabs.com/gitium/usage/

== Receiving is nicer when giving ==
We’ve built this to make our lives easier and we’re happy to do that for other developers, too. We’d really appreciate it if you could contribute with code, tests, documentation or just share your experience with Gitium.

Development of Gitium happens at http://github.com/PressLabs/gitium 
Issues are tracked at http://github.com/PressLabs/gitium/issues 
This WordPress plugin can be found at https://wordpress.org/plugins/gitium/

== Screenshots ==

1. Setup step 1: Get SSH Key
2. Setup step 2: Set SSH Key (Github)
3. Setup step 3: Add remote repository
4. Setup step 4: Choose following branch
5. Commit local changes


== Installation ==

= Manual Installation =

1. Go to your WordPress admin dashboard.
2. Navigate to 'Plugins' → 'Add New'.
3. Search for "Gitium".
4. Install and activate the Gitium plugin.

= Usage =

- Connect Your Repository
After activation, go to the Gitium settings in your WordPress admin area.
Copy the Public Key that Gitium has generated for you from the Key Pair field.
In your repository manager of choice (GitHub, GitLab, or Bitbucket), go to the settings page and find the “Deploy keys” (or similar) section. There you will need to add the Public Key you’ve copied from Gitium. This will grant Gitium access to your repository. Make sure to allow write access as well. Also make sure that you copy the entire key from gitium.
Now go back to your main repository page and copy the SSH URL to your repo. Paste this URL in Gitium and press the “Fetch” button.
A “Repository initialized successfully” message will show up. This means that your repository has been populated with the current code of your website and it is ready to start working with Gitium.

- Initial Commit
Once connected, Gitium will automatically commit your existing WordPress theme and plugins to the connected repository.
This initial commit serves as the baseline for your site’s code.

- Making Changes
Make changes to your WordPress site’s code (themes, plugins) as needed.
Gitium will automatically commit these changes to your Git repository.
Using the webhook provided by Gitium, it will also automatically deploy the changes from the repository to your WordPress site.

- Webook Configuration
Gitium uses the webhook to automatically deploy remote changes to your server. To configure it follow these steps:
    1. Go to your WordPress website and go to your Gitium Settings page;
    2. Copy the full Webhook URL that Gitium provides;
    3. In your Git Manager settings, go to Webhook section, add a new webhook and paste the webhook URL you have copied from Gitium.
    4. Press Add, no settings changes needed. The webook simply needs a ping, nothing more. The security key is already embedded in the final URL Gitium has generated for you.

Now when you push to your repo, this webhook will automatically pull the changes to your remote server and deploy them.

You can see more details about the plugin also in our documentation here: https://www.presslabs.com/docs/code/gitium/install-gitium/

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

= Where do I report security bugs found in this plugin? =

Please report security bugs found in the source code of the Gitium plugin through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/gitium). The Patchstack team will assist you with verification, CVE assignment, and notify the developers of this plugin.

== Upgrade Notice ==
= 1.2.1 =
Tested up to WP 6.8

== Changelog ==

= 1.2.1 =
* Tested the compatibility of the plugin with WP 6.8

= 1.2.0 =
* Changed the license for all files to GPLv3
* Fix: In some cases, the WP is configured in another folder. We've made some changes on how we check for the wp-load.php file

= 1.1.0 =
* Fix: In some cases, the website was stuck in maintenance when it was pulling the changes from remote
* Added: A copy-to-clipboard button was introduced for copying ssh key-pair and webhook url

= 1.0.7 =
* Fix: HOME env definition;
* Fix: deprecation warnings in PHP 8.1;
* Compat: added composer.json package;
* Compat: add the possibility to use a custom `.gitignore` by defining the `GITIGNORE` constant.

= 1.0.6 =
* Fixed deprecation warnings for dynamic property in git-wrapper

= 1.0.5 =
* Various bug fixes

= 1.0.4 =
* PHP 8 compat. fixes

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
