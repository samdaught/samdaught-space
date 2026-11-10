#!/usr/bin/env bash

function info {
  echo " "
  echo "--> $1"
  echo " "
}

info "Provision-script user: `whoami`"

info "Restart web-stack"
service php7.2-fpm restart
service nginx restart
service mysql restart
