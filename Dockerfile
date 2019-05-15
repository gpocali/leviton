FROM debian:stretch
MAINTAINER Gregory Pocali <1571781+gpocali@users.noreply.github.com>

# This docker is used to control MyLeviton switches and turn lights on at sunset and off at sunrise at a user defined brightness
# 3 Environmental Variables must be defined
# - leviton_username
# - leviton_password
# - leviton_percent
# For security purposes, pass these variables through a file using env_file in the run command

COPY levitonAPI.php /usr/bin/leviton.php

RUN apt-get update; apt-get -y install wget g++ make git php-cli php-curl coreutils procps; \
mkdir /tmp/sunwait; cd /tmp/sunwait; git clone https://github.com/risacher/sunwait.git; cd sunwait; make; cp sunwait /usr/local/bin; cd /tmp/; rm -Rf sunwait; \
apt-get -y remove wget g++ make git; apt-get -y autoremove; apt-get clean; rm -rf /var/lib/apt/lists/*; chmod +x /usr/bin/leviton.php;

ENTRYPOINT ["php", "/usr/bin/leviton.php", "start"]

