## Gitium Usage

Activate the plugin and follow the on-screen instructions under the Code menu.

!!! important
    Gitium does its best not to version your WordPress core, neither your /wp-content/uploads folder.

### Requirements
Gitium requires git command line tool with a minimum version of 1.7 installed on the server and the proc_open PHP function enabled.


### Installing

1. Upload `gitium.zip` to the `/wp-content/plugins/` directory;
2. Extract the `gitium.zip` archive into the `/wp-content/plugins/` directory;
3. Activate the plugin through the 'Plugins' menu in WordPress.

Alternatively, go into your WordPress dashboard and click on Plugins -> Add
Plugin and search for __gitium__. Then, click on Install and, after that, on Activate Now.


### Activating with bitbucket private repo

1. Go to your bitbucket acount and create a new (empty) repo.
2. Clone the repo. Make sure you choose "ssh" and not "https" under the cloning options. copy the clone link
3. Go to the __gitium__ page on the admin menu
4. Paste the link under the __Remote URI__ text imput erae
5. Copy the __key Pair__ from the gitium admin menu
6. Head back to the Bitbucket acount. Go into yout acount (not the repo, the whole account) -> settings -> SSH Keys -> add Key -> paste the key undet the __key__ section. You can name you key. Hit __add Key__
7. Back in the admin menu of gitium plugin, press __fetch__

All done! your repo should be filled with your theme files now.



