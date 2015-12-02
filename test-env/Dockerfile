FROM ubuntu:latest
MAINTAINER Radu Ciorba radu@devrandom.ro

RUN apt-get -qq update
RUN DEBIAN_FRONTEND=noninteractive apt-get -yq install php5-cli php5-dev php5-curl php5-mysql git subversion wget mysql-client-5.5 mysql-server-5.5
ADD create_user.py /create_user.py
CMD ["/usr/bin/python3", "/create_user.py"]