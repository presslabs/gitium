FROM ubuntu:latest
MAINTAINER Presslabs Engineering engineering@presslabs.com

RUN apt-get -qq update
RUN DEBIAN_FRONTEND=noninteractive apt-get -yq install php7.0-cli php7.0-dev php7.0-curl php7.0-mysql git subversion wget mysql-client-5.7 mysql-server-5.7 sudo
ADD create_user.py /create_user.py
CMD ["/usr/bin/python3", "/create_user.py"]
