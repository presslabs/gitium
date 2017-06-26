#!/bin/bash

declare -r plugin_name_slug="$1"
declare -r plugin_tag_release="$2"
declare -r wordpress_username="$3"
declare -r wordpress_password="$4"

declare -r plugin_release_url="https://github.com/${wordpress_username}/${plugin_name_slug}/archive/${plugin_tag_release}.zip"
declare -r wordpress_svn_url="https://plugins.svn.wordpress.org/${plugin_name_slug}/"
declare -r wordpress_svn_tag_url="${wordpress_svn_url}/tags/${plugin_tag_release}/"
declare -r wordpress_plugin_page="https://wordpress.org/plugins/${plugin_name_slug}/"

printf "\nStart to add the '${plugin_tag_release}' ${plugin_name_slug} release to ${wordpress_svn_url} Wordpress SVN repository.\n\n"

if [ "$#" -ne 4 ]; then
  echo "Usage: $0 <plugin_name_slug> <plugin_tag_release> <wordpress_username> <wordpress_password>" >&2
  exit 1
fi

if [[ -z `wget -S --spider ${plugin_release_url} 2>&1 | grep 'HTTP/1.1 200 OK'` ]]; then
  echo "The ${plugin_name_slug} release '${plugin_tag_release}' does not exist on github.com! Please create it first." >&2
  exit 1
fi

if [[ `wget -S --spider ${wordpress_svn_tag_url} 2>&1 | grep 'HTTP/1.1 200 OK'` ]]; then
  echo "The ${plugin_name_slug} SVN tag '${wordpress_svn_tag_url}' already exists!" >&2
  exit 1
fi

printf "\n$0 - Upload the '${plugin_tag_release}' ${plugin_name_slug} release to the WordPress SVN repository '${wordpress_svn_url}'\n\n"

mkdir new_${plugin_name_slug}_${plugin_tag_release} ; cd new_${plugin_name_slug}_${plugin_tag_release}
wget ${plugin_release_url}
unzip ${plugin_tag_release}.zip
mkdir wpsvn ; cd wpsvn ; svn co ${wordpress_svn_url}
mkdir ${plugin_name_slug}/tags/${plugin_tag_release}
cd ${plugin_name_slug}/trunk ; rm -rf ./*
cp -r ../../../${plugin_name_slug}-${plugin_tag_release}/${plugin_name_slug}/* ./
cp -r ../../../${plugin_name_slug}-${plugin_tag_release}/${plugin_name_slug}/* ../tags/${plugin_tag_release}/
cd ../ ; svn add tags/${plugin_tag_release} ; svn status
svn commit --username ${wordpress_username} --password "${wordpress_password}" -m "Add the ${plugin_tag_release} version"
cd ../../../ ; rm -rf new_${plugin_name_slug}_${plugin_tag_release}

if [[ `wget -S --spider ${wordpress_svn_tag_url} 2>&1 | grep 'HTTP/1.1 200 OK'` ]]; then
  printf "\n$0 - The new ${plugin_name_slug} tag '${plugin_tag_release}' is now on WordPress SVN repository."
  printf "\n$0 - Check the ${plugin_name_slug} page here: ${wordpress_plugin_page} in order to be sure the new version is visible.\n\n"
  exit 0
else
  echo "Something went wrong, the ${plugin_name_slug} SVN tag '${wordpress_svn_url}' is not created on WordPress SVN repository!" >&2
  exit 1
fi
