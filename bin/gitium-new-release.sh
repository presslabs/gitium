#!/bin/bash

declare -r gitium_tag_release="$1"
declare -r wordpress_username="$2"
declare -r wordpress_password="$3"
declare -r github_release_url="https://github.com/${wordpress_username}/gitium/archive/${gitium_tag_release}.zip"
declare -r wordpress_svn_url="https://plugins.svn.wordpress.org/gitium/"
declare -r wordpress_svn_tag_url="${wordpress_svn_url}/tags/${gitium_tag_release}/"
declare -r wordpress_gitium_page="https://wordpress.org/plugins/gitium/"

printf "\nStart to add the '${gitium_tag_release}' Gitium release to ${wordpress_svn_url} Wordpress SVN repository.\n\n"

if [ "$#" -ne 3 ]; then
  echo "Usage: $0 <gitium_tag_release> <wordpress_username> <wordpress_password>" >&2
  exit 1
fi

if [[ -z `wget -S --spider ${github_release_url} 2>&1 | grep 'HTTP/1.1 200 OK'` ]]; then
  echo "The Gitium release '${gitium_tag_release}' does not exist on github.com! Please create it first." >&2
  exit 1
fi

if [[ `wget -S --spider ${wordpress_svn_tag_url} 2>&1 | grep 'HTTP/1.1 200 OK'` ]]; then
  echo "The Gitium SVN tag '${wordpress_svn_tag_url}' already exists!" >&2
  exit 1
fi

printf "\n$0 - Upload the '${gitium_tag_release}' Gitium release to the WordPress SVN repository '${wordpress_svn_url}'\n\n"

mkdir new_gitium_${gitium_tag_release} ; cd new_gitium_${gitium_tag_release}
wget ${github_release_url}
unzip ${gitium_tag_release}.zip
mkdir wpsvn ; cd wpsvn ; svn co ${wordpress_svn_url}
mkdir gitium/tags/${gitium_tag_release}
cd gitium/trunk ; rm -rf ./*
cp -r ../../../gitium-${gitium_tag_release}/gitium/* ./
cp -r ../../../gitium-${gitium_tag_release}/gitium/* ../tags/${gitium_tag_release}/
cd ../ ; svn add tags/${gitium_tag_release} ; svn status
svn commit --username ${wordpress_username} --password ${wordpress_password} -m "Add the ${gitium_tag_release} version"
cd ../../../ ; rm -rf new_gitium_${gitium_tag_release}

if [[ `wget -S --spider ${wordpress_svn_tag_url} 2>&1 | grep 'HTTP/1.1 200 OK'` ]]; then
  printf "\n$0 - The new Gitium tag '${gitium_tag_release}' is now on WordPress SVN repository."
  printf "\n$0 - Check the Gitium page here: ${wordpress_gitium_page} in order to be sure the new version is visible.\n\n"
  exit 0
else
  echo "Something went wrong, the Gitium SVN tag '${wordpress_svn_url}' is not created on WordPress SVN repository!" >&2
  exit 1
fi

