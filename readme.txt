=== Gitium ===

Contributors: PressLabs
Donate link: http://www.presslabs.com/
Tags: git, version, versioning, presslabs
Requires at least: 3.9
Tested up to: 3.9
Stable tag: 0.3-alpha
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Gitium will keeps your code on git improving interactions between site admins and developers.

== Description ==

Gitium will keeps your code on git improving interactions between site admins and developers. It automatically commits plugins and themes updates/deletes and post activation code changes. It allows continous deployment for wordpress by providing a wehook leveraging integrations with github, travis and other CI tools.

== Installation ==

= Installation =
1. Upload `gitium.zip` to the `/wp-content/plugins/` directory;
2. Extract the `gitium.zip` archive into the `/wp-content/plugins/` directory;
3. Activate the plugin through the 'Plugins' menu in WordPress.

Alternatively go into your WordPress dashboard and click on Plugins -> Add Plugin and search for Gitium. Then click on Install, then on Activate Now.

= Usage =

Install and activate the plugin according with the Installation instructions.

== Frequently Asked Questions ==

= Is this plugin considered stable? =

Right now this plugin is considered alpha quality and should be used in production environments only by adventurous kinds.

= What happens in case of conflicts? =

The behavior in case of conflicts is to overwrite the changes on `origin` repository with the local changes. Eg. local modifications take precedence over remote ones.

= How to deploy automatically after a push? =

You can ping the webhook url after a push to automatically deploy the new code. This url plays well with github webhooks.


== Changelog ==

= 0.3-alpha =
* First alpha release
