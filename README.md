Gitium [![Build Status](https://travis-ci.org/PressLabs/gitium.svg)](https://travis-ci.org/PressLabs/gitium) [![Coverage](https://codeclimate.com/github/PressLabs/gitium/coverage.png)](https://codeclimate.com/github/PressLabs/gitium) [![Code Climate](https://codeclimate.com/github/PressLabs/gitium.png)](https://codeclimate.com/github/PressLabs/gitium)
======

# Welcome to Gitium

Gitium was built in 2013 to provide our clients a more simple and error-free method to integrate a new git version control into their code management flow.

Gitium was developed by the awesome engineering team at [Presslabs](https://www.presslabs.com/), a Managed WordPress Hosting provider.

For more open-source projects, check [Presslabs Code](https://www.presslabs.org/). 

### What is Gitium?

This plugin enables continuous deployment for WordPress, integrating with tools such as Github, Bitbucket or Travis-CI. Theme or plugin updates, installs and removals are all automatically versioned. Ninja code edits from the WordPress editor are also tracked by the version control system.

### Why Gitium?

Gitium is designed with responsible development environments in mind, allowing staging and production to follow different branches of the same repository. You can also deploy code by simply using git push.

Gitium requires git command line tool with a minimum version of 1.7 installed on the server and the proc_open PHP function enabled.

### Gitium features:

- preserves the WordPress behavior
- accountability for code changes
- safe code storage—gets all code edits in Git

### Development

For more details about Gitium, head here: https://www.presslabs.org/gitium/docs/usage/

#### Running the tests

The tests run with PHPUnit against the WordPress test suite and need `git`, `composer` and a MySQL database whose tables they drop:

```
WORDPRESS_TEST_DB_HOST=127.0.0.1 WORDPRESS_TEST_DB_USER=wordpress WORDPRESS_TEST_DB_PASSWORD=wordpress \
    make test WP_VERSION=7.1.2
```

`make test` installs the dependencies and downloads WordPress and its test suite to `/tmp`. On Drone the tests run for every
push with PHP 8.2, 8.3 and 8.4 (see `.drone.jsonnet`; run `drone jsonnet --stream --format` to regenerate `.drone.yml` after
changing it). To run a pipeline locally use `drone exec --pipeline php8.4-wp7.1.2`.

### Contributing

We’ve built this to make our lives easier and we’re happy to do that for other developers, too. We’d really appreciate it if you could contribute with code, tests, documentation or just share your experience with Gitium.

Development of Gitium happens at http://github.com/PressLabs/gitium 
Issues are tracked at http://github.com/PressLabs/gitium/issues 
This WordPress plugin can be found at https://wordpress.org/plugins/gitium/

### License

This project is licensed under the [GNU GENERAL PUBLIC LICENSE Version 3](https://www.gnu.org/licenses/gpl.html).

