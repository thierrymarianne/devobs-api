#!/bin/bash

if [ ! -z "${PROJECT_DIR}" ];
then
    current_directory="${PROJECT_DIR}/bin"
else
    current_directory=`dirname "$0"`
fi

source "${current_directory}"/console.sh

consume_amqp_lively_status_messages
