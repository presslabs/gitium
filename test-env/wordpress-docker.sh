#!/bin/bash

printf "\n--- bring the official MySQL & WordPress docker images ---\n"
docker pull mysql
docker pull wordpress

printf "\n---  stop previous containers and remove it, if there are any ---\n"
mysqldocker_id=$(docker ps | grep mysqldocker | cut -d' ' -f1)
docker stop $mysqldocker_id
docker rm $mysqldocker_id
mywordpressdocker_id=$(docker ps | grep mywordpressdocker | cut -d' ' -f1)
docker stop $mywordpressdocker_id
docker rm $mywordpressdocker_id

printf "\n---  start the new containers (MySQL and WordPress) ---\n"
docker run --name mysqldocker -e MYSQL_ROOT_PASSWORD=my-secret-pw -d mysql:latest
mywordpressdocker_id=$(docker run -e WORDPRESS_DB_PASSWORD=my-secret-pw -d --name mywordpressdocker --link mysqldocker:mysql  wordpress)

printf "\n---  add the custom changes to the WordPress container ---\n"
docker exec -it $mywordpressdocker_id apt-get update
docker exec -it $mywordpressdocker_id apt-get -y install vim
docker exec -it $mywordpressdocker_id apt-get -y install git
docker exec -it $mywordpressdocker_id sed -i '$ a\php_flag opcache.enable Off' /var/www/html/.htaccess

printf "\n---  find out the IP address of the WordPress container ---\n"
ip_address=$(docker inspect mywordpressdocker | grep '"IPAddress":' | head -1 | cut -d'"' -f4)
echo 'Go to: http://'$ip_address'/ in order to install a new WordPress site.'
