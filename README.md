Gitium [![Build Status](https://travis-ci.org/PressLabs/gitium.svg)](https://travis-ci.org/PressLabs/gitium) [![Coverage](https://codeclimate.com/github/PressLabs/gitium/coverage.png)](https://codeclimate.com/github/PressLabs/gitium) [![Code Climate](https://codeclimate.com/github/PressLabs/gitium.png)](https://codeclimate.com/github/PressLabs/gitium)
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
[Presslabs](https://www.presslabs.com).


## Installation

1. Upload `gitium.zip` to the `/wp-content/plugins/` directory;
2. Extract the `gitium.zip` archive into the `/wp-content/plugins/` directory;
3. Activate the plugin through the 'Plugins' menu in WordPress.

Alternatively, go into your WordPress dashboard and click on Plugins -> Add
Plugin and search for __gitium__. Then, click on Install and, after that, on Activate Now.


## Usage

Activate the plugin and follow the on-screen instructions under the `Code` menu.

_IMPORTANT_: Gitium does its best not to version your WordPress core, neither
your `/wp-content/uploads` folder.


## Test

##### Build the docker image
cd test-env
docker build -t gitiumtest .

##### Run the docker image and associate the code with /code dir
cd gitium
docker run -it -v `pwd`:/code gitiumtest

##### Start the env for tests
make clean ; make env_latest

##### Run the tests
reset ; ./vendor/bin/phpunit --tap

##### Run only one suite (clean run)
make clean ; make env_latest ; reset ; ./vendor/bin/phpunit --tap tests/test-git-wrapper.php


## Useful Docker commands

##### Test if Docker is installed
sudo docker ps

##### List the Docker images
sudo docker images

##### Biuld a Docker image
cd gitium/test-env
sudo docker build -t gitium-test-tag .

##### Start a Docker image*
cd gitium
sudo docker run -it -v `pwd`:/code gitium-dev-tag

##### View all containers
docker ps -a

##### Kill containers and remove them
docker rm $(docker kill $(docker ps -aq))

##### Remove all existing containers
docker rm $(docker ps -aq)

##### Remove an image/tag
docker rmi <node>
docker rmi docker-phpunit:1.0.0

##### Remove all images
docker rmi $(docker images -qf "dangling=true")

##### Remove all images except my-image and ubuntu
docker rmi $(docker images | grep -v 'ubuntu\|my-image' | awk {'print $3'})

##### Rename/retag an image
docker tag server:latest myname/server:latest
docker tag 1cf76 myUserName/imageName:0.1.0


## Inside Docker commands

##### Start the env for tests
make clean ; make env_latest

##### Ctrl+D
exit to root console

##### Go to developer console
su - developer

##### Run only the methods with test_is_dirty
reset ; ./vendor/bin/phpunit --tap tests/test-git-wrapper.php --filter '/test_is_dirty/'

##### Run only one suite
./vendor/bin/phpunit --tap tests/test-git-wrapper.php


## Frequently Asked Questions

##### Is this plugin considered stable?

Right now this plugin is considered alpha quality and should be used in
production environments only by adventurous kinds.

##### What will happen in case of conflicts?

The behavior in case of conflicts is to overwrite the changes on the `origin`
repository with the local changes (ie. local modifications take precedence over
remote ones).

##### How to deploy automatically after a push?

You can ping the webhook url after a push to automatically deploy the new code.
The webhook url can be found under `Code` menu. This url also plays well with Github
or Bitbucket webhooks.

##### Does it work on multi site setups?

Gitium does not support multisite setups at the moment.

##### How does gitium handle submodules?

Submodules are currently not supported.
