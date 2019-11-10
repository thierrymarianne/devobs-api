#!/bin/bash

function start_discovering_network() {
  if [ ! -z "${PROJECT_DIR}" ];
  then
      current_directory="${PROJECT_DIR}/bin"
  else
      current_directory=`dirname "$0"`
  fi

  source "${current_directory}"/functions.sh
  consume_amqp_messages_for_networks
}

start_discovering_network