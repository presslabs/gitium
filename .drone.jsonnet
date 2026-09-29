// Generate .drone.yml after changing this file:
//   drone jsonnet --stream --format
local Pipeline(php_version, wp_version) = {
  kind: 'pipeline',
  type: 'docker',
  name: 'php' + php_version + '-wp' + wp_version,

  steps: [
    {
      name: 'test',
      image: 'docker.io/presslabs/php-runtime:%s' % php_version,
      pull: 'always',
      // the workspace is cloned as root
      user: 'root',
      environment: {
        WORDPRESS_TEST_DB_HOST: 'database',
        WORDPRESS_TEST_DB_NAME: 'wordpress_test',
        WORDPRESS_TEST_DB_USER: 'wordpress',
        WORDPRESS_TEST_DB_PASSWORD: 'wordpress',
      },
      commands: [
        // the tests create git repositories and commit
        'git config --global user.email gitium@presslabs.com',
        'git config --global user.name Gitium',
        "git config --global --add safe.directory '*'",
        |||
          timeout 120 sh -c 'until php -d display_errors=0 -r "exit(@mysqli_connect(getenv(\"WORDPRESS_TEST_DB_HOST\"), getenv(\"WORDPRESS_TEST_DB_USER\"), getenv(\"WORDPRESS_TEST_DB_PASSWORD\")) ? 0 : 1);" 2>/dev/null; do sleep 2; done'
        |||,
        'make test WP_VERSION=%s' % wp_version,
      ],
    },
  ],

  services: [
    {
      name: 'database',
      image: 'percona/percona-server:8.0',
      pull: 'always',
      environment: {
        MYSQL_DATABASE: 'wordpress_test',
        MYSQL_USER: 'wordpress',
        MYSQL_PASSWORD: 'wordpress',
        MYSQL_ROOT_PASSWORD: 'test',
      },
    },
  ],
};

[
  Pipeline('8.4', '7.1.2'),
  Pipeline('8.3', '7.1.2'),
  Pipeline('8.2', '7.1.2'),
]
