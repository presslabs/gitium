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

### How can I change the configured remote repository?

Gitium stores the configured repository as the `origin` remote. To replace it
with another repository:

1. Open **Code → Gitium → Configuration** in the WordPress dashboard. You can
   also use the **Disconnect from repo** button on the Gitium status screen.
2. Click **Disconnect from repo** and confirm the prompt.
3. Create or open the destination repository and copy its clone URL. Use an SSH
   URL if you want to authenticate with Gitium's generated key pair.
4. Paste the URL into **Remote URL** on the Gitium configuration screen and
   click **Fetch**.
5. If Gitium finds branches in the destination repository, select the branch
   to follow and click **Merge & Push**. For an empty repository, Gitium
   creates and pushes its initial `master` branch during setup.
6. If you use SSH authentication, copy the new public key shown on the
   configuration screen and add it to the destination repository or account
   with write access.

!!! warning
    Disconnecting removes the existing `origin` remote and clears Gitium's
    stored branch, webhook, and key-pair settings. The local files and Git
    history in `wp-content/.git` are preserved, but the old repository is no
    longer updated. Before disconnecting, make sure any local changes you need
    are committed and confirm that the new repository is the one you intend to
    use.
