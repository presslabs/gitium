## Gitium - Frequently Asked Questions

### Is this plugin considered stable?

Right now this plugin is considered alpha quality and should be used in
production environments only by adventurous kinds.

### What will happen in case of conflicts?

The behavior in case of conflicts is to overwrite the changes on the `origin`
repository with the local changes (ie. local modifications take precedence over
remote ones).

### How to deploy automatically after a push?

You can ping the webhook url after a push to automatically deploy the new code.
The webhook url can be found under `Code` menu. This url also plays well with Github
or Bitbucket webhooks.

### Does it work on multi site setups?

Gitium does not support multisite setups at the moment.

### How does gitium handle submodules?

Submodules are currently not supported.
