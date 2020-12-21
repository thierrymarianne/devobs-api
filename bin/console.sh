#!/usr/bin/env bash

# 2019-11-10 - Notes about dependencies addition or removal
#
# ```
# export VENDOR_NAME='symfony/symfony:^3.4.x' && make add-php-dependency
# export VENDOR_NAME='symfony/symfony' && make remove-php-dependency
# ```
#
# 2019-10-19 - Notes about clearing application cache
#
# ```
# make clear-backend-application-cache
# ```
#
# 2019-10-06 - Notes about running Apache container
#
# ```
# make build-php-container
# make build-apache-container
#
# # RabbitMQ startup has to be complete
# # when generating cached application configuration
# make run-rabbitmq
#
# # Install PHP vendors
# make install-php-dependencies
#
# make run-apache-container
#
# # Set ACL
# make set-acl
# ```
#
# 2019-06-27 - Notes
#
# - Ensure environment variable PROJECT_DIR has been declared
# - Ensure the appropriate docker network has been created before considering to initialize a MySQL volume
# - Building a PHP docker image is a requirement of the initialization of the MySQL volume
# ```
# # First shortcut command to be executed before running a MySQL container
# make initialize-mysql-volume
# ```
# - Building RabbitMQ docker image would prevent having warning provided the project configuration
# when initializing MySQL volume
# - Before running a RabbitMQ container, the following commands should have been executed
# ```
# make run-rabbitmq-container
# make configure-rabbitmq-user-privileges
# make setup-amqp-fabric
# ```

function get_application_prefix() {
    echo 'devobs-api'
}

function get_docker_network() {
    echo 'devobs-api'
}

function get_container_name_for() {
    local target
    target="${1}"

    local application_prefix
    application_prefix="$(get_application_prefix)-"

    echo "${application_prefix}${target}"
}

function get_image_name_for() {
    local target
    target="${1}"

    local application_prefix
    application_prefix="$(get_application_prefix)-"

    echo "${application_prefix}${target}"
}

function get_project_name() {
    local project_name
    project_name=''
    if [ -n "${PROJECT_NAME}" ]; then
        project_name='-p '"$PROJECT_NAME "
    fi

    echo "${project_name}"
}

function create_network() {
    local network
    network=`get_docker_network`

    local command
    command="$(echo -n 'docker network create '"${network}"' \
    --subnet=192.168.176.0/20 \
    --ip-range=192.168.176.0/10 \
    --gateway=192.168.176.1')"

    /bin/bash -c "${command}"
}

function get_network_option() {
    network='--network '`get_docker_network`' '
    if [ -n "${NO_DOCKER_NETWORK}" ];
    then
        network=''
    fi

    echo "${network}";
}

function kill_existing_consumers {
    local pids
    pids=(`ps ux | grep "rabbitmq:consumer" | grep -v '/bash' | grep -v grep | cut -d ' ' -f 2-3`)

    local totalProcesses
    totalProcesses=`ps ux | grep "rabbitmq:consumer" | grep -v grep | grep -c ''`

    if [ -n "${DOCKER_MODE}" ];
    then
        remove_exited_containers
    fi

    if [ "${totalProcesses}" == "0" ] ||  [ -z "${totalProcesses}" ];
    then
        echo 'No consumption processes left to kill'
        return;
    fi

    echo 'The total consumption processes counted is '"${totalProcesses}"

    if [ -z "${MAX_PROCESSES}" ];
    then
        MAX_PROCESSES=2
    fi

    echo 'The maximum processes to be kept alive is '"${MAX_PROCESSES}"

    if [ -n ${DOCKER_MODE} ];
    then
        totalProcesses="$(docker ps -a | grep "${application_prefix}"php | grep -c '')"
    fi

    local total_processes
    total_processes=$(expr 0 + "${totalProcesses}")

    local max_processes
    max_processes=$(expr 0 + "${MAX_PROCESSES}")

    if [ "${total_processes}" -le "${max_processes}" ];
    then
        return
    fi

    if [ -z "${pids}" ];
    then
        return
    fi

    if [ -n "${DOCKER_MODE}" ];
    then
        make remove-php-container

        return
    fi

    export IFS=$'\n'
    for pid in ${pids[@]};
    do echo 'About to kill process with pid '"${pid}" && \
        _pid=$(echo 0 + `echo "${pid}" | sed -e "s/[[:space:]]+//g"` | bc) && \
        kill -9 ${_pid} && \
        echo 'Just killed process of pid "'${_pid}'" consuming messages'
    done
}

function stop_workers() {
    local project_name
    project_name=$(get_project_name)

    local symfony_environment
    symfony_environment="$(get_symfony_environment)"

    local script
    script='bin/console messenger:stop-workers -e prod'
    command="docker-compose ${project_name} exec -T -e ${symfony_environment} worker ${script}"

    echo '=> About to stop consumers'
    /bin/bash -c "${command}"
}

function handle_messages {
    local command_suffix
    command_suffix="${1}"

    local namespace
    namespace="${2}"

    if [ -z "${namespace}" ];
    then
        namespace='twitter'
    fi

    export NAMESPACE="handle_amqp_messages_${command_suffix}_${namespace}"

    export XDEBUG_CONFIG="idekey='phpstorm-xdebug'"

    if [ -z "${MESSAGES}" ]
    then
        MESSAGES=10;
        echo '[default count of messages] '$MESSAGES
    fi

    local default_memory_limit
    default_memory_limit='128M'
    if [ -z "${MEMORY_LIMIT}" ]
    then
        MEMORY_LIMIT="${default_memory_limit}"
        echo '[default memory limit] '$MEMORY_LIMIT
    fi

    if [ -z "${TIME_LIMIT}" ]
    then
        TIME_LIMIT="300";
    fi

    if [ -z "${PROJECT_DIR}" ];
    then
        export PROJECT_DIR='/var/www/devobs'
    fi

    local minimum_execution_time=10
    if [ ! -z "${MINIMUM_EXECUTION_TIME}" ];
    then
        minimum_execution_time="${MINIMUM_EXECUTION_TIME}"
    fi

    remove_exited_containers

    local rabbitmq_output_log="./var/logs/rabbitmq."${NAMESPACE}".out.log"
    local rabbitmq_error_log="./var/logs/rabbitmq."${NAMESPACE}".error.log"
    ensure_log_files_exist "${rabbitmq_output_log}" "${rabbitmq_error_log}"
    rabbitmq_output_log="${PROJECT_DIR}/${rabbitmq_output_log}"
    rabbitmq_error_log="${PROJECT_DIR}/${rabbitmq_error_log}"

    local php_directives
    php_directives=''
    if [ "${MEMORY_LIMIT}" != '128M' ];
    then
        php_directives='php -dmemory_limit='"${MEMORY_LIMIT}"' '
    fi

    trap stop_workers SIGINT SIGTERM

    export SCRIPT="${php_directives}bin/console messenger:consume --time-limit=${TIME_LIMIT} -m ${MEMORY_LIMIT} -l ${MESSAGES} "${command_suffix}

    cd "${PROJECT_DIR}/provisioning/containers" || exit

    local symfony_environment
    symfony_environment="$(get_symfony_environment)"

    local project_name
    project_name=$(get_project_name)
    command="docker-compose ${project_name} run --rm --name ${SUPERVISOR_PROCESS_NAME} -T -e ${symfony_environment} worker ${SCRIPT}"
    echo 'Executing command: "'$command'"'
    echo 'Logging standard output of RabbitMQ messages consumption in '"${rabbitmq_output_log}"
    echo 'Logging standard error of RabbitMQ messages consumption in '"${rabbitmq_error_log}"
    /bin/bash -c "$command >> ${rabbitmq_output_log} 2>> ${rabbitmq_error_log}"
    cd "../../"

    /bin/bash -c "sleep ${minimum_execution_time}"
}

function consume_amqp_lively_status_messages {
    handle_messages 'timely_status' 'consumer'
}

function consume_amqp_messages_for_aggregates_likes {
    handle_messages 'aggregates_likes' 'consumer'
}

function consume_amqp_messages_for_networks {
    handle_messages 'network' 'consumer'
}

function consume_amqp_messages_for_aggregates_status {
    handle_messages 'aggregates_status'
}

function consume_amqp_messages_for_member_status {
    handle_messages 'user_status'
}

function consume_amqp_messages_for_news_status {
    handle_messages 'news_status'
}

function purge_queues() {
    local rabbitmq_vhost
    rabbitmq_vhost="$(cat <(cat .env.local | grep STATUS=amqp | sed -E 's#.+(/.+)/[^/]*$#\1#' | sed -E 's/\/%2f/\//g'))"
    cd provisioning/containers || exit

    local project_name
    project_name="$(get_project_name)"

    /bin/bash -c "docker-compose ${project_name} exec -d messenger rabbitmqctl purge_queue get-news-status -p ${rabbitmq_vhost}"
    /bin/bash -c "docker-compose ${project_name} exec -d messenger rabbitmqctl purge_queue get-news-likes -p ${rabbitmq_vhost}"
    /bin/bash -c "docker-compose ${project_name} exec -d messenger rabbitmqctl purge_queue failures -p ${rabbitmq_vhost}"
}

function stop_workers() {
    cd provisioning/containers || exit

    docker-compose run --rm worker bin/console messenger:stop-workers
}

function execute_command () {
    local output_log="${1}"
    local error_log="${2}"

    make run-php-script >> "${output_log}" 2>> "${error_log}"

    if [ -n "${VERBOSE}" ];
    then
        cat "${output_log}" | tail -n1000
        cat "${error_log}" | tail -n1000
    fi
}

function get_mysql_gateway() {
    local gateway=`ip -f inet addr  | grep docker0 -A1 | cut -d '/' -f 1 | grep inet | sed -e 's/inet//' -e 's/\s*//g'`
    echo "${gateway}"
}

function grant_privileges {
    local database_user_test="$(get_param_value_from_config "database_user_test")"
    local database_name_test="$(get_param_value_from_config "database_name_test")"
    local database_password_test="$(get_param_value_from_config "database_password_test")"
    local container_name=`get_mysql_container_name`

    local gateway="`get_mysql_gateway`"

    cat provisioning/containers/mysql/templates/grant-privileges-to-testing-user.sql.dist | \
        sed -e 's/{database_name_test}/'"${database_name_test}"'/g' \
        -e 's/{database_user_test}/'"${database_user_test}"'/g' \
        -e 's/{database_password_test}/'"${database_password_test}"'/g' \
        -e 's/{gateway}/'"${gateway}"'/g' \
        >  provisioning/containers/mysql/templates/grant-privileges-to-testing-user.sql

    docker exec -ti "${container_name}" mysql \
        -e "$(cat provisioning/containers/mysql/templates/grant-privileges-to-testing-user.sql)"

    local database_user="$(get_param_value_from_config "database_user")"
    local database_name="$(get_param_value_from_config "database_name")"
    local database_password="$(get_param_value_from_config "database_password")"

    cat provisioning/containers/mysql/templates/grant-privileges-to-user.sql.dist | \
        sed -e 's/{database_name}/'"${database_name}"'/g' \
        -e 's/{database_user}/'"${database_user}"'/g' \
        -e 's/{database_password}/'"${database_password}"'/g' \
        -e 's/{gateway}/'"${gateway}"'/g' \
        >  provisioning/containers/mysql/templates/grant-privileges-to-user.sql

    docker exec -ti "${container_name}" mysql \
        -e "$(cat provisioning/containers/mysql/templates/grant-privileges-to-user.sql)"
}

function get_project_dir {
    local project_dir='/var/www/devobs'

    if [ -n "${PROJECT_DIR}" ];
    then
        project_dir="${PROJECT_DIR}"
    fi

    echo "${project_dir}"
}

function create_database_schema {
    local env="${1}"

    if [ -z "${env}" ];
    then
        echo 'Please pass a valid environment ("test", "dev" or "prod")'
    fi

    local project_dir="$(get_project_dir)"
    echo 'php /var/www/devobs/bin/console doctrine:schema:create -e '"${env}" | make run-php
}

function create_database_test_schema {
    create_database_schema "test"
}

function create_database_prod_like_schema {
    create_database_schema "prod"
}

function get_param_value_from_config() {
    local name="${1}"

    if [ -z "${name}" ];
    then
        echo 'Please provide the non-empty name of a parameter available in the configuration, which has not been commented out.'
    fi

    local param_value=`cat app/config/parameters.yml | grep "${name}"':' | grep -v '#' | \
        cut -f 2 -d ':' | sed -e 's/[[:space:]]//g' -e 's/^"//' -e 's/"$//'`

    echo "${param_value}"
}

# @deprecated
# In the past, all existing migrations were removed before computing new ones
function diff_schema {
    local question="Would you like to remove the previous queries generated? Not doing so might have some unpredictable consequences."

    if [ $(ls app/DoctrineMigrations/Version* | grep -c '') -gt 0 ];
    then
        if whiptail --defaultno --yesno "${question}" 20 60;
        then
            echo 'OK, let us remove those previous queries.'
            # Ensuring the migrations files belong to rightful owner
            sudo chown `whoami` ./app/DoctrineMigrations/Version*
            local migration_directory="`pwd`/app/DoctrineMigrations/"
            cd ./app/DoctrineMigrations/
            ls ./Version* | xargs -I{} /bin/bash -c 'version="'${migration_directory}{}'" && echo "About to remove ${version}" && \
                rm "${version}"'
            cd ./../../
        else
            echo 'Ok, let us learn from our mistakes.'
        fi
    fi

    /bin/bash -c "export PROJECT_DIR=`pwd`; echo 'php /var/www/devobs/bin/console doc:mig:diff -vvvv' | make run-php"
}

# @deprecated
# In production, export the *appropriate* environment variable (contains "_accepted_") to migrate a schema
# No export of variable environment is provided here or in the Makefile documentation to prevent bad mistakes
# from happening
# In development, "app/config/parameters.yml" should contain a parameter %port_local%
# holding the port of a development database
function migrate_schema {
    local pattern=$"s/\(\$this\->addSql('\)//g"
    local first_query=$(cat "$(ls app/DoctrineMigrations/Version*.php | tail -n1)" | \
        grep addSql \
        | sed -e "${pattern}" )

    local queries=$(printf %s "$(echo ${first_query} | head -n1 | head -c500)")

    local port_accepted_once=''
    if [ -n "${accepted_database_port}" ];
    then
        port_accepted_once="${accepted_database_port}"
        unset accepted_database_port
    fi;

    local port_admin="$(get_param_value_from_config "database_port_admin")"

    local with_risks=0
    if [ "${port_accepted_once}" == "${port_admin}" ];
    then
        with_risks=1
    fi

    if [ ${with_risks} -eq 1 ];
    then
        local confirmation_request="Are you fully aware of what you're doing at this time: "
        local now="$(date '+%Y-%m-%d %H:%M:%S')"
        local question="$(printf "%s %s?" "${confirmation_request}" "${now}" )"
        if whiptail --defaultno --yesno "${question}...${queries}" 20 60;
        then
            echo 'OK, let us migrate this schema, dear being capable of running commands.'
        else
            echo 'OK, good bye.'
            return
        fi
    else
        if [ ${port_admin} != '%port_local%' ];
        then
            echo "Sorry won't do for your own sake (please see README.md)."
            return
        fi
    fi

    local question="Are you sure you'd like to migrate the schema for database running on port ${port_admin}?"
    # @see https://stackoverflow.com/a/27875395/282073
    # The second most voted proposition was adopted for its ease of use and readability
    #
    # About the box width and height to be rendered
    # $ man whiptail | grep yesno -A4
    if whiptail --defaultno --yesno "${question}...${queries}" 20 60;
    then
        echo 'OK, let us migrate this schema.'
    else
        echo 'OK, good bye.'
        return
    fi

    local project_dir="$(get_project_dir)"
    echo 'php '"${project_dir}"'/bin/console doc:mig:mig --em=admin' | make run-php
}

function compute_schema_differences_for_read_database() {
    run_php_script "php /var/www/devobs/bin/console doc:mig:diff -vvvv --em=default -n" interactive_mode
}

function compute_schema_differences_for_write_database() {
    run_php_script "php /var/www/devobs/bin/console doc:mig:diff -vvvv --em=write -n" interactive_mode
}

function migrate_schema_of_read_database() {
    run_php_script "php /var/www/devobs/bin/console doc:mig:mig --em=default" interactive_mode
}

function migrate_schema_of_write_database() {
    run_php_script "php /var/www/devobs/bin/console doc:mig:mig --em=write" interactive_mode
}

function is_rabbitmq_not_ready {
  local container_expected_to_be_ready
  container_expected_to_be_ready="$(get_container_name_for "rabbitmq")"

  if [ "$(docker logs "${container_expected_to_be_ready}" | grep -c 'startup complete')" -eq 0 ];
  then
    echo 0
    return 1
  fi

  echo 1
}

function get_php_docker_image_name() {
    local docker_image_name
    docker_image_name="$(get_image_name_for "php")${memory}"
    echo '=> About to run container from docker image "'"${docker_image_name}"'"' 1>&2

    echo "${docker_image_name}"
}

function check_security_of_php_dependencies() {
  local project_dir
  project_dir='/var/www/devobs'

  local command
  command="/bin/sh -c 'cd "${project_dir}" && "
  command="${command}"'php ./bin/security-checker security:check'"'"
  echo "${command}" | make run-php
}

function clear_backend_application_cache() {
  local project_dir
  project_dir='/var/www/devobs'

  echo 'php '"${project_dir}"'/app/console cache:clear -e dev --no-warmup' | make run-php
  echo 'php '"${project_dir}"'/app/console cache:clear -e prod --no-warmup' | make run-php
}

function install_php_dependencies {
    local project_dir
    project_dir="$(get_project_dir)"

    local production_option
    production_option=''
    if [ -n "${APP_ENV}" ] && [ "${APP_ENV}" = 'prod' ];
    then
        production_option='--apcu-autoloader '
    fi

    local command
    command=$(echo -n '/bin/bash -c "cd '"${project_dir}"' &&
    source '"${project_dir}"'/bin/install-composer.sh &&
    php '"${project_dir}"'/composer.phar install '"${production_option}"'--prefer-dist -n"')
    echo "${command}" | make run-php
}

function run_composer {
    local command=''
    if [ -z "${COMMAND}" ];
    then
        command="${COMMAND}"
        echo 'Please pass a non-empty command as environment variable'
        echo 'e.g.'
        echo 'export COMMAND="install --prefer-dist"'
        return
    fi

    command="${COMMAND}"

    local project_dir="$(get_project_dir)"
    local command=$(echo -n 'php /bin/bash -c "cd '"${project_dir}"' &&
    php -dmemory_limit="-1" '"${project_dir}"'/composer.phar "'"${command}")
    echo ${command} | make run-php
}

function run_composer {
    local command=''
    if [ -z "${COMMAND}" ];
    then
        command="${COMMAND}"
        echo 'Please pass a non-empty command as environment variable'
        echo 'e.g.'
        echo 'export COMMAND="install --prefer-dist"'
        return
    fi

    command="${COMMAND}"

    local project_dir="$(get_project_dir)"
    local command=$(echo -n 'php /bin/bash -c "cd '"${project_dir}"' &&
    php -dmemory_limit="-1" '"${project_dir}"'/composer.phar "'"${command}")
    echo ${command} | make run-php
}

function add_php_dependency {
    local dependency
    dependency="${1}"

    if [ -z "${GITHUB_OAUTH_TOKEN}" ];
    then
        echo 'Please export a GitHub OAuth token as an environment variable e.g.'
        echo 'export GITHUB_OAUTH_TOKEN="tok"'

        return 1
    fi

    if [ -z "${VENDOR_NAME}" ] && [ -z "${dependency}" ];
    then
        echo 'Please export a vendor name as an environment variable e.g.'
        echo 'export VENDOR_NAME="symfony/symfony:^3.x"'

        return 1
    fi

    if [ -z "${dependency}" ];
    then
      echo 'Please pass a non-empty vendor name as first argument e.g.'
      echo '"symfony/symfony:^3.x"'

      return 1
    fi

    # Ensure dependency is available
    # to prevent failure when caching configuration parameters
    run_rabbitmq_container

    # Wait for RabbitMQ startup to be complete
    while [ `is_rabbitmq_not_ready` -eq 0 ];
    do
        sleep 1
        echo 'Waiting for RabbitMQ to be ready...'
    done

    local project_dir="$(get_project_dir)"
    local command=$(echo -n '/bin/bash -c "cd '"${project_dir}"' &&
    source '"${project_dir}"'/bin/install-composer.sh &&
    php -v &&
    php '"${project_dir}"'/composer.phar config -g github-oauth.github.com '"${GITHUB_OAUTH_TOKEN}"' &&
    COMPOSER_MEMORY_LIMIT=8G php '"${project_dir}"'/composer.phar -vvv req '"'"''"${dependency}""'"'"')

    echo "${command}" | make run-php
}

function remove_php_dependency {
    local dependency
    dependency="${1}"

    if [ -z "${GITHUB_OAUTH_TOKEN}" ];
    then
        echo 'Please export a GitHub OAuth token as an environment variable e.g.'
        echo 'export GITHUB_OAUTH_TOKEN="tok"'

        return 1
    fi

    if [ -z "${VENDOR_NAME}" ] && [ -z "${dependency}" ];
    then
        echo 'Please export a vendor name as environment variable e.g.'
        echo 'export VENDOR_NAME="symfony/symfony:^3.x"'

        return 1
    fi

    if [ -z "${dependency}" ];
    then
      echo 'Please pass a non-empty vendor name as first argument e.g.'
      echo '"symfony/symfony:^3.x"'

      return 1
    fi

    # Ensure dependency is available
    # to prevent failure when caching configuration parameters
    run_rabbitmq_container

    # Wait for RabbitMQ startup to be complete
    while [ `is_rabbitmq_not_ready` -eq 0 ];
    do
        sleep 1
        echo 'Waiting for RabbitMQ to be ready...'
    done

    local project_dir="$(get_project_dir)"
    local command=$(echo -n '/bin/bash -c "cd '"${project_dir}"' &&
    source '"${project_dir}"'/bin/install-composer.sh &&
    php '"${project_dir}"'/composer.phar config -g github-oauth.github.com '"${GITHUB_OAUTH_TOKEN}"' &&
    php '"${project_dir}"'/composer.phar remove --no-update '"'"''"${dependency}""'"'"')

    echo "${command}" | make run-php
}

function get_gateway() {
    local gateway=`ip -f inet addr  | grep docker0 -A1 | cut -d '/' -f 1 | grep inet | sed -e 's/inet//' -e 's/\s*//g'`
    if [ ! -z "${GATEWAY}" ];
    then
        gateway="${GATEWAY}"
    fi

    echo "${gateway}"
}

function run_mysql_client {
    local mysql_volume_path="${1}"

    local gateway="$(get_gateway)"
    local last_container_id="$(get_mysql_container_id)"

    if `is_mysql_volume_initialized "${mysql_volume_path}"`;
    then
        docker exec -ti "${last_container_id}" mysql -uroot -A -h"${gateway}"
        return
    fi

    docker exec -ti "${last_container_id}" mysql -uroot -A
}

function remove_mysql_container {
    local container_name=`get_mysql_container_name`

    if [ `docker ps -a | grep "${container_name}" | grep -c ''` -gt 0 ];
    then
        docker rm -f `docker ps -a | grep "${container_name}" | awk '{print $1}'`
    fi
}

function is_mysql_volume_initialized() {
    local mysql_volume_path="${1}"

    if [ -e "${mysql_volume_path}"/initialized ];
    then
        return 0;
    fi

    return 1;
}

function get_mysql_container_name() {
    local from="${1}"
    if [ -z "${from}" ];
    then
        from=`pwd`
    fi

    local container_name=$(echo "${from}" | sha1sum | awk '{print $1}')

    echo "maria-${container_name}"
}

function get_mysql_container_id() {
    local container_name=`get_mysql_container_name`

    echo "$(docker ps -a  | grep "${container_name}" | awk '{print $1}')"
}

function mark_mysql_volume_as_initialized() {
    local last_container_id="$(get_mysql_container_id)"
    docker exec -ti "${last_container_id}" touch "/var/lib/mysql/initialized"
}

function wait_until_mysql_container_is_ready() {
    local last_container_id="$(get_mysql_container_id)"
    local last_container_logs="$(docker logs "${last_container_id}" 2>&1)"

    while [ $(echo "${last_container_logs}" | grep -c "\.sock") -eq 0 ];
    do
        sleep 1
        last_container_logs="$(docker logs "${last_container_id}" 2>&1)"

        test $(echo "${last_container_logs}" | grep -c "\.sock") -eq 0 && echo -n '.'
    done
}

function run_mysql_container {
    local from="${1}"

    if [ -n "${from}" ];
    then
        echo 'About to move to "'"${from}"'"'
        cd "${from}"
    fi

    local database_password="$(get_param_value_from_config "database_password_admin")"
    local database_name="$(get_param_value_from_config "database_name_admin")"
    local database_user="$(get_param_value_from_config "database_user_admin")"
    if [ ! -z "${REPLICATION_SERVER}" ] && [ -z "${MASTER_SERVER}" ];
    then
        database_password="$(get_param_value_from_config "database_password_read")"
        database_name="$(get_param_value_from_config "database_name_read")"
        database_user="$(get_param_value_from_config "database_user_read")"
    fi

    echo 'Database name is "'"${database_name}"'"'
    echo 'User name is '"${database_user}*****"
    local obfuscated_password=$(/bin/bash -c 'echo "'"${database_password}"'" | head -c5')
    echo 'User password would be like '"${obfuscated_password}*****"

    cd ./provisioning/containers/mysql

    local configuration_file='my-master.cnf.dist'
    if [ ! -z "${REPLICATION_SERVER}" ] && [ -z "${MASTER_SERVER}" ];
    then
        configuration_file='my-slave.cnf.dist'
    fi

    local replacement_pattern='s/{password\}/'"${database_password}"'/'
    cat ./templates/"${configuration_file}" | sed -e "${replacement_pattern}" > ./templates/my.cnf

    remove_mysql_container

    local initializing=1
    local default_configuration_volume='-v '"`pwd`"'/templates/my.cnf:/etc/mysql/conf.d/config-file.cnf '
    local configuration_volume="${default_configuration_volume}"

    local mysql_volume_path=`pwd`"/../../volumes/mysql"
    if [ ! -z "${MYSQL_VOLUME}" ];
    then
        mysql_volume_path="${MYSQL_VOLUME}"
        configuration_volume='-v '"`pwd`"'/templates/my.cnf:/etc/mysql/conf.d/config-file.cnf '
        echo 'About to mount "'"${MYSQL_VOLUME}"'" as MySQL volume'
    fi

    if [ -z "${INIT}" ];
    then
        # Credentials yet to be granted can not be configured at initialization
        configuration_volume=''
        initializing=0

        if `is_mysql_volume_initialized "${mysql_volume_path}"`;
        then
            local configuration_volume="${default_configuration_volume}"
        fi
    fi

    local gateway="`get_mysql_gateway`"

    local mysql_volume_path=`pwd`"/../../volumes/mysql"
    if [ -n "${MYSQL_VOLUME}" ];
    then
        is_replication_server='--server-id=2 '

        if [ ! -z "${MASTER_SERVER}" ];
        then
            is_replication_server='--server-id=1 '
        fi
    fi

    # @see https://hub.docker.com/_/mysql/
    command="docker run --restart=always -d -p${gateway}:3306:3306 --name "${container_name}" \
        -e MYSQL_DATABASE=${database_name} \
        -e MYSQL_USER=${database_user} \
        -e MYSQL_PASSWORD=${database_password} \
        -e MYSQL_ROOT_PASSWORD=${database_password} \
        -v "${log_path}":/var/mysql/log \
        ${configuration_volume} -v ${mysql_volume_path}:/var/lib/mysql \
        db-maria ${is_replication_server}--character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci"

    # Restore current directory to project root dir
    cd ./../../../

    /bin/bash -c "echo 'About to execute command: ${command}'"
    /bin/bash -c "${command}"

    if [ "${initializing}" -eq 0 ];
    then
        wait_until_mysql_container_is_ready

        local last_container_id="$(get_mysql_container_id)"
        local matching_databases=$(docker exec -ti "${last_container_id}" mysql -uroot -h"${gateway}" \
            -e 'show databases' | \
            grep weaving_dev | grep -c '')

        if [ ${matching_databases} -eq 0 ];
        then
            echo 'About to grant privileges and to create a database.'

            grant_privileges && \
            create_database_prod_like_schema && \
            create_database_test_schema

            mark_mysql_volume_as_initialized
        fi
    fi

    # Log the last created container on initialization
    if [ "${initializing}" -eq 1 ];
    then
        echo 'About to initialize MySQL container.'

        wait_until_mysql_container_is_ready
        remove_mysql_container

        unset INIT
        run_mysql_container `pwd`
    else
        run_mysql_client "${mysql_volume_path}"
    fi
}

function initialize_mysql_volume {
    remove_mysql_container
    sudo rm -rf ./provisioning/volumes/mysql/*

    export INIT=1
    run_mysql_container # Will clean up INIT global var
}

function does_network_exist() {
    local network_name
    network_name="$(get_docker_network)"

    if [ "$(docker network ls | grep "${network_name}" -c)" -gt 0 ];
    then
        echo 1
        return
    fi

    echo 0;
}

function run_rabbitmq_container {
    local rabbitmq_vhost="$(cat <(cat ../backup/app/config/parameters.yml | grep -v '#' | grep 'rabbitmq_vhost:' | cut -f 2 -d ':' | sed -e 's/[[:space:]]//g'))"
    local rabbitmq_password="$(cat ../backup/app/config/parameters.yml | grep -v '#' | grep 'rabbitmq_password:' | cut -f 2 -d ':' | sed -e 's/[[:space:]]//g')"
    local rabbitmq_user=$(cat <(cat ../backup/app/config/parameters.yml | \
        grep 'rabbitmq_user:' | grep -v '#' | \
        cut -f 2 -d ':' | sed -e 's/[[:space:]]//g'))

    echo 'RabbitMQ user is "'"${rabbitmq_user}"'"'
    echo 'RabbitMQ password is "'"${rabbitmq_password}"'"'
    echo 'RabbitMQ vhost is "'"${rabbitmq_vhost}"'"'

    cd ./provisioning/containers/rabbitmq

    remove_rabbitmq_container

    local gateway=`ifconfig | grep docker0 -A1 | tail -n1 | awk '{print $2}' | sed -e 's/addr://'`

    if [ "$(docker images -a | grep "${name}" -c)" -gt 0 ];
    then
        echo 1
        return
    fi

    echo 0;
}

function build_php_container() {
    cd provisioning/containers/php
    docker build -t "$(get_image_name_for "php")" .
}

function remove_exited_containers() {
    /bin/bash -c "docker ps -a | grep Exited | awk ""'"'{print $1}'"'"" | xargs docker rm -f >> /dev/null 2>&1"
}

function remove_php_container() {
    local namespace=''
    if [ -n  "${NAMESPACE}" ];
    then
        namespace=' | grep '"'""${NAMESPACE}""'"
    fi

    remove_exited_containers

    local running_containers_matching_namespace='docker ps -a | grep hours | grep '"$(get_container_name_for "php")"'-'"${namespace}"

    local running_containers
    running_containers=`/bin/bash -c "${running_containers_matching_namespace} | grep -c ''"`
    if [ "${running_containers}" -eq 0 ];
    then
        echo 'No more PHP container to be removed'

        return
    fi

    command="${running_containers_matching_namespace} | awk '{print "'$1'"}' | xargs docker rm -f >> /dev/null 2>&1"
    echo '=> About to execute command "'"${command}"'"'

    /bin/bash -c "${command}" || echo 'No more container to be removed'
}

function list_amqp_queues() {
    local rabbitmq_vhost
    rabbitmq_vhost="$(cat <(cat .env.local | grep STATUS=amqp | sed -E 's#.+(/.+)/[^/]*$#\1#' | sed -E 's/\/%2f/\//g'))"
    cd provisioning/containers || exit

    local project_name
    project_name="$(get_project_name)"
    /bin/bash -c "docker-compose ${project_name} exec messenger watch -n1 'rabbitmqctl list_queues -p ${rabbitmq_vhost}'"
}

function set_permissions_in_apache_container() {
    sudo rm -rf ./var/cache
    sudo mkdir ./var/cache
    sudo chown -R `whoami` ./var/logs ./var

    cd ./provisioning/containers || exit
    docker-compose exec worker bin/console cache:clear -e prod --no-warmup
    docker-compose exec worker bin/console cache:clear -e dev --no-warmup
    cd "../../"
}

function build_apache_container() {
    cd provisioning/containers/apache || exit
    docker build -t apache .
}

function remove_apache_container {
    if [ `docker ps -a | grep "$(get_image_name_for "apache")" -c` -eq 0 ]
    then
        return;
    fi

    docker ps -a | grep "$(get_image_name_for "apache")" | awk '{print $1}' | xargs docker rm -f
}

function get_apache_container_interactive_shell() {
    docker exec -ti "$(get_image_name_for "apache")" bash
}

function ensure_blackfire_configuration_files_are_present() {
    if [ ! -e `pwd`'/provisioning/containers/apache/templates/blackfire/zz-blackfire.ini' ];
    then
      cp `pwd`'/provisioning/containers/apache/templates/blackfire/zz-blackfire.ini.dist' \
        `pwd`'/provisioning/containers/apache/templates/blackfire/zz-blackfire.ini'
    fi

    if [ ! -e `pwd`'/provisioning/containers/apache/templates/blackfire/agent' ];
    then
      cp `pwd`'/provisioning/containers/apache/templates/blackfire/agent.dist' \
        `pwd`'/provisioning/containers/apache/templates/blackfire/agent'
    fi

    if [ ! -e `pwd`'/provisioning/containers/apache/templates/blackfire/.blackfire.ini' ];
    then
      cp `pwd`'/provisioning/containers/apache/templates/blackfire/.blackfire.ini.dist' \
        `pwd`'/provisioning/containers/apache/templates/blackfire/.blackfire.ini'
    fi
}

function build_mysql_container() {
    cd provisioning/containers/mysql
    docker build -t db-maria .
}

function build_php_fpm_container() {
    cd provisioning/containers/php-fpm
    docker build -t "$(get_image_name_for "php-fpm")" .
}

function run_php_fpm() {
    local suffix=''
    if [ ! -z "${DEVOBS_API_PHP_FPM_SUFFIX}" ];
    then
        suffix="-${DEVOBS_API_PHP_FPM_SUFFIX}"
    fi

    remove_php_fpm_container "${suffix}"

    local port=80
    if [ -n "${PRESS_REVIEW_PHP_FPM_PORT}" ];
    then
        port="${DEVOBS_API_PHP_FPM_PORT}"
    fi

    host host=''
    if [ -n "${PRESS_REVIEW_PHP_FPM_HOST}" ];
    then
        host="${DEVOBS_API_PHP_FPM_HOST}"':'
    fi

    host mount=''
    if [ -n "${PRESS_REVIEW_PHP_FPM_MOUNT}" ];
    then
        mount="${DEVOBS_API_PHP_FPM_MOUNT}"
    fi

    local symfony_environment
    symfony_environment="$(get_symfony_environment)"

    if [ ! -e "`pwd`/provisioning/containers/php-fpm/templates/.blackfire.ini" ]
    then
        /bin/bash -c "cp `pwd`/provisioning/containers/php-fpm/templates/.blackfire.ini{.dist,}";
    fi

    if [ ! -e "`pwd`/provisioning/containers/php-fpm/templates/zz-blackfire.ini" ];
    then
        /bin/bash -c "cp `pwd`/provisioning/containers/php-fpm/templates/zz-blackfire.ini{.dist,}";
    fi

    local extensions
    extensions=`pwd`"/provisioning/containers/php-fpm/templates/extensions.ini.dist";

    local extensions_volume
    extensions_volume="-v ${extensions}:/usr/local/etc/php/conf.d/extensions.ini"

    local network
    network=`get_network_option`

    local command
    command=$(echo -n 'docker run '"${network}"' \
--restart=always \
-d -p '${host}''${port}':9000 \
-e '"${symfony_environment}"' '"${extensions_volume}"' \
-v '`pwd`'/provisioning/containers/php-fpm/templates/20-no-xdebug.ini.dist:/usr/local/etc/php/conf.d/20-xdebug.ini \
-v '`pwd`'/provisioning/containers/php-fpm/templates/devobs.conf:/usr/local/etc/php-fpm.d/www.conf \
-v '`pwd`'/provisioning/containers/php-fpm/templates/docker.conf:/usr/local/etc/php-fpm.d/docker.conf \
-v '`pwd`'/provisioning/containers/php-fpm/templates/zz-docker.conf:/usr/local/etc/php-fpm.d/zz-docker.conf \
-v '`pwd`'/provisioning/containers/php-fpm/templates/zz-blackfire.ini:/usr/local/etc/php/conf.d/zz-blackfire.ini \
-v '`pwd`'/provisioning/containers/php-fpm/templates/.blackfire.ini:/root/.blackfire.ini \
-v '`pwd`'/provisioning/containers/apache/templates/blackfire/agent:/etc/blackfire/agent \
'"${mount}"' \
-v '`pwd`':/var/www/devobs \
--name='"$(get_container_name_for "php-fpm")${suffix}"' php-fpm php-fpm'
)

    echo 'About to execute "'"${command}"'"'

    /bin/bash -c "${command}"
}

function remove_php_fpm_container {
    local suffix="${1}"

    if [ `docker ps -a | grep "$(get_container_name_for "php-fpm")${suffix}" -c` -eq 0 ]
    then
        return;
    fi

    docker ps -a | grep "$(get_container_name_for "php-fpm")${suffix}" | awk '{print $1}' | xargs docker rm -f
}

function run_php_script() {
    local script
    script="${1}"

    local interactive_mode
    interactive_mode="${2}"

    if [ -z "${interactive_mode}" ];
    then
      interactive_mode="${INTERACTIVE_MODE}";
    fi

    if [ -z "${script}" ];
    then
      if [ -z "${SCRIPT}" ];
      then
        echo 'Please pass a valid path to a script by export an environment variable'
        echo 'e.g.'
        echo 'export SCRIPT="bin/console cache:clear"'
        return
      fi

      script="${SCRIPT}"
    fi

    local memory=''
    if [ -n "${PHP_MEMORY_LIMIT}" ];
    then
        memory="${PHP_MEMORY_LIMIT}"
    fi

    local namespace=
    namespace=''
    if [ -n "${NAMESPACE}" ];
    then
        namespace="${NAMESPACE}-"

        echo 'About to run container in namespace '"${NAMESPACE}"
    fi

    local suffix
    suffix='-'"${namespace}""$(cat /dev/urandom | tr -cd 'a-f0-9' | head -c 32 2>> /dev/null)"

    export SUFFIX="${suffix}"
    local symfony_environment
    symfony_environment="$(get_symfony_environment)"

    local option_detached
    option_detached=''
    if [ -z "${interactive_mode}" ];
    then
        option_detached='-d '
    fi

    local network
    network="$(get_network_option)"

    local project_name=''
    project_name="$(get_project_name)"

    local container_name
    container_name="$(echo "${script}" | sha256sum | awk '{print $1}')"

    local command

    if [ -z "${interactive_mode}" ];
    then
        command="$(echo -n 'cd provisioning/containers && \
        docker-compose '"${project_name}"'run -T --rm --name='"${container_name}"' '"${option_detached}"'worker '"${script}")"
    else
        command="$(echo -n 'cd provisioning/containers && \
        docker-compose '"${project_name}"'exec '"${option_detached}"'worker '"${script}")"
    fi

    echo 'About to execute "'"${command}"'"'
    /bin/bash -c "${command}"
}

function run_php() {
    local arguments
    arguments="$(cat -)"

    if [ -z "${arguments}" ];
    then
        arguments="${ARGUMENT}"
    fi

    cd ./provisioning/containers || exit

    local command
    command=$(echo -n 'docker-compose -f docker-compose.yml exec -T worker '"${arguments}")

    echo 'About to execute '"${command}"
    /bin/bash -c "${command}"
}

function run_stack() {
    cd provisioning/containers || exit
    docker-compose up
    cd ../..
}

function run_worker() {
    cd provisioning/containers || exit
    docker-compose up worker
    cd ../..
}

function keep_php_container_running() {
    echo 'php -r "while (true) { sleep(1); } "' | make run-php
}

function ensure_log_files_exist() {
    local standard_output_file="${1}"
    local standard_error_file="${2}"

    if [ ! -e ./composer.lock ];
    then
      echo 'Inconsistent file system location prevents executing the next commands'
      return 1
    fi

    if [ ! -e "${standard_output_file}" ];
    then
        sudo touch "${standard_output_file}";
    fi

    if [ ! -e "${standard_error_file}" ];
    then
        sudo touch "${standard_error_file}";
    fi

    if [ ! `whoami` == 'www-data' ];
    then
        sudo chown www-data "${standard_output_file}" "${standard_error_file}"
        sudo chmod a+rwx "${standard_output_file}" "${standard_error_file}"
    fi
}

function get_symfony_environment() {
    local symfony_env='dev'
    if [ -n "${SYMFONY_ENV}" ];
    then
        symfony_env="${SYMFONY_ENV}"
    fi

    echo 'APP_ENV='"${symfony_env}"
}

function get_environment_option() {
    local symfony_env='dev'
    if [ -n "${SYMFONY_ENV}" ];
    then
        symfony_env="${SYMFONY_ENV}"
    fi

    echo ' APP_ENV='"${symfony_env}"
}

# @deprecated
function dispatch_messages_from_members_lists {
    export NAMESPACE="produce_messages_from_members_lists"
    before_running_command

    if [ -z "${username}" ];
    then
        echo 'Please export a valid username: export username="bob"'

        return
    fi

    run_command 'bin/console weaving_the_web:amqp:produce:lists_members --screen_name='"${username}"
}

# @deprecated
function dispatch_messages_for_networks {
    export NAMESPACE="produce_messages_for_networks"
    before_running_command

    if [ -z "${MEMBER_LIST}" ];
    then
        echo 'Please export a valid member list: export MEMBER_LIST="bob,alice"'

        return
    fi

    run_command 'bin/console import-network --member-list="'${MEMBER_LIST}'"'
}

# @deprecated
function dispatch_messages_for_timely_statuses {
    export NAMESPACE="produce_messages_for_timely_statuses"
    before_running_command

    run_command 'bin/console weaving_the_web:amqp:produce:timely_statuses'
}

# @deprecated
function dispatch_messages_from_member_timeline {
    export NAMESPACE="produce_messages_from_member_timeline"
    before_running_command

    if [ -z "${username}" ];
    then
        echo 'Please export a valid username:'
        echo 'export username="rasmus"'

        return
    fi

    run_command 'bin/console weaving_the_web:amqp:produce:user_timeline --screen_name="'"${username}"'" -vvv'
}

function before_running_command() {
    make remove-php-container

    export XDEBUG_CONFIG="idekey='phpstorm-xdebug'"

    if [ -z "${PROJECT_DIR}" ];
    then
        export PROJECT_DIR='/var/www/devobs'
    fi
}

function run_command {
    local php_command
    php_command=${1}

    local rabbitmq_output_log="var/logs/rabbitmq."${NAMESPACE}".out.log"
    local rabbitmq_error_log="var/logs/rabbitmq."${NAMESPACE}".error.log"

    local PROJECT_DIR
    PROJECT_DIR='.'

    ensure_log_files_exist "${rabbitmq_output_log}" "${rabbitmq_error_log}"

    rabbitmq_output_log="${PROJECT_DIR}/${rabbitmq_output_log}"
    rabbitmq_error_log="${PROJECT_DIR}/${rabbitmq_error_log}"

    export SCRIPT="${php_command}"

    if [ -n "${memory_limit}" ];
    then
        export PHP_MEMORY_LIMIT=' -d memory_limit='"${memory_limit}"
    fi

    if [ ! -z "${NAMESPACE}" ];
    then
      echo 'Logging standard output of RabbitMQ messages consumption in '"${output_log}"
      echo 'Logging standard error of RabbitMQ messages consumption in '"${error_log}"
      execute_command "${output_log}" "${error_log}"

      return
    fi

    echo 'Logging standard output of worker in '"${output_log}"
    echo 'Logging standard error of worker in '"${error_log}"
    execute_command "${output_log}" "${error_log}"
}

# @deprecated
function dispatch_messages_for_aggregates_list {
    export in_priority=1
    export NAMESPACE="produce_aggregates_messages"
    dispatch_messages_for_news_list
}

# @deprecated
function dispatch_messages_for_search_query {
    export NAMESPACE="produce_search_query"
    dispatch_messages_for_news_list
}

function dispatch_messages_for_news_list {
    if [ -z ${NAMESPACE} ];
    then
        export NAMESPACE="produce_news_messages"
    fi

    before_running_command

    if [ -z "${username}" ];
    then
        echo 'Please export a valid username: export username="bob"'

        return
    fi

    local priority_option=''
    if [ -n "${in_priority}" ];
    then
        priority_option='--priority_to_aggregates '
    fi

    local query_restriction=''
    if [ -n "${QUERY_RESTRICTION}" ];
    then
        query_restriction='--query_restriction='"${QUERY_RESTRICTION}"
    fi

    local list_option
    list_option=''
    if [ -n "${list_name}" ];
    then
        local list_option='--list='"'${list_name}'"
        if [ -n "${multiple_lists}" ];
        then
            list_option='--lists='"'${multiple_lists}'"
        fi
    fi

    local cursor_argument
    cursor_argument=''
    if [ -n "${CURSOR}" ];
    then
        cursor_argument=' --cursor='"${CURSOR}"
    fi

    local arguments
    arguments="${priority_option}"'--screen_name='"${username}"' '"${list_option}"' '"${query_restriction}"
    arguments="${arguments}${cursor_argument}"
    run_command 'bin/console press-review:dispatch-messages-to-fetch-member-statuses '"${arguments}"
}

function refresh_statuses() {
    export NAMESPACE="refresh_statuses"
    make remove-php-container

    export XDEBUG_CONFIG="idekey='phpstorm-xdebug'"

    if [ -z "${PROJECT_DIR}" ];
    then
        export PROJECT_DIR='/var/www/devobs'
    fi

    local rabbitmq_output_log="var/logs/rabbitmq."${NAMESPACE}".out.log"
    local rabbitmq_error_log="var/logs/rabbitmq."${NAMESPACE}".error.log"
    ensure_log_files_exist "${rabbitmq_output_log}" "${rabbitmq_error_log}"
    rabbitmq_output_log="${PROJECT_DIR}/${rabbitmq_output_log}"
    rabbitmq_error_log="${PROJECT_DIR}/${rabbitmq_error_log}"

    if [ -z "${aggregate_name}" ];
    then
        echo 'Please export a valid aggregate name: export aggregate_name="news"'

        return
    fi

    local php_command
    php_command='bin/console press-review:map-aggregate-status-collection --aggregate-name="'"${aggregate_name}"'" -vvv'

    local symfony_environment
    symfony_environment="$(get_symfony_environment)"

    if [ -z "${DOCKER_MODE}" ];
    then
        command="${symfony_environment} /usr/bin/php $PROJECT_DIR/${php_command}"
        echo 'Executing command: "'$command'"'
        echo 'Logging standard output of RabbitMQ messages consumption in '"${rabbitmq_output_log}"
        echo 'Logging standard error of RabbitMQ messages consumption in '"${rabbitmq_error_log}"
        /bin/bash -c "$command >> ${rabbitmq_output_log} 2>> ${rabbitmq_error_log}"

        return
    fi

    export SCRIPT="${php_command}"

    echo 'Logging standard output of RabbitMQ messages consumption in '"${rabbitmq_output_log}"
    echo 'Logging standard error of RabbitMQ messages consumption in '"${rabbitmq_error_log}"

    execute_command "${rabbitmq_output_log}" "${rabbitmq_error_log}"
}

function run_php_unit_tests() {
    if [ -z ${DEBUG} ];
    then
        bin/phpunit -c ./phpunit.xml.dist --process-isolation
        return
    fi

    bin/phpunit -c ./phpunit.xml.dist --verbose --debug
}

function run_php_features_tests() {
    bin/behat -c ./behat.yml
}

function remove_redis_container {
    if [ `docker ps -a | grep "$(get_container_name_for "redis")" | grep -c ''` -gt 0 ];
    then
        docker rm -f `docker ps -a | grep "$(get_container_name_for "redis")" | awk '{print $1}'`
    fi
}

function today_statuses() {
    cat var/logs/dev.log | awk '{$1=$2=$3="";print $0}' | sed -e 's/^\s\+//' | grep `date -I` | awk '{$1=$2="";print $0}'
}

function follow_today_statuses() {
    tail -f var/logs/dev.log | awk '{$1=$2=$3="";print $0}' | sed -e 's/^\s\+//' | grep `date -I` | awk '{$1=$2="";print $0}'
}

function restart_web_server() {
    cd ./provisioning/containers || exit
    docker-compose restart web
}

function start_discovering_network {
    before_running_command

    if [ -z "${MEMBER_NAME}" ];
    then
        echo 'Please export a valid member name:'
        echo 'export MEMBER_NAME="richhickey"'

        return
    fi

    run_command 'app/console import-network --member-name="'${MEMBER_NAME}'"'
}
