gitium [![Build Status](https://travis-ci.org/PressLabs/gitium.svg)](https://travis-ci.org/PressLabs/gitium) [![Coverage](https://codeclimate.com/github/PressLabs/gitium/coverage.png)](https://codeclimate.com/github/PressLabs/gitium) [![Code Climate](https://codeclimate.com/github/PressLabs/gitium.png)](https://codeclimate.com/github/PressLabs/gitium)
======

Gitium is a WordPress plugin which provides automatic git version control and deployment for your plugins and themes
integrated into wp-admin.

## Description

Gitium enables continuous deployment for WordPress, integrating with tools such
as Github, Bitbucket or Travis-CI. Theme or plugin updates, installs and
removals are all automatically versioned. Ninja code edits from the WordPress
editor are also tracked by the version control system.

Gitium is designed with sane development environments in mind, allowing staging
and production to follow different branches of the same repository. You can also
deploy code by simply using `git push`.

The plugin requires the `git` command line tool with a minimum version of 1.7 installed on the
server and the `proc_open` PHP function enabled.

Gitium is the latest element discovered at
[PressLabs](http://www.presslabs.com).

## Installation

1. Upload `gitium.zip` to the `/wp-content/plugins/` directory;
2. Extract the `gitium.zip` archive into the `/wp-content/plugins/` directory;
3. Activate the plugin through the 'Plugins' menu in WordPress.

Alternatively, go into your WordPress dashboard and click on Plugins -> Add
Plugin and search for __gitium__. Then, click on Install and, after that, on Activate Now.

## Usage

Activate the plugin and follow the on-screen instructions under the `Code` menu.

_IMPORTANT_: Gitium does its best not to version your WordPress core neither
your `/wp-content/uploads` folder.

## Frequently Asked Questions

##### Is this plugin considered stable?

Right now this plugin is considered alpha quality and should be used in
production environments only by adventurous kinds.

##### What happens in case of conflicts?

The behavior in case of conflicts is to overwrite the changes on the `origin`
repository with the local changes (ie. local modifications take precedence over
remote ones).

##### How to deploy automatically after a push?

You can ping the webhook url after a push to automatically deploy the new code.
The webhook url can be found under `Code` menu. This url plays well with Github
or Bitbucket webhooks.

##### Does it work on multi site setups?

Gitium is not supporting multisite setups at the moment.

##### How does gitium handle submodules?

Submodules are currently not supported.
