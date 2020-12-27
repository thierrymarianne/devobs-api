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

function get_project_name() {
    local project_name
    project_name='devobs'

    if [ -n "${PROJECT_NAME}" ]; then
        project_name="${PROJECT_NAME}"
    fi

    echo "${project_name}"
}

function get_docker_network() {
    echo "$(get_project_name)-network"
}

function create_network() {
    local network
    network="$(get_docker_network)"

    local command
    command="$(echo -n 'docker network create '"${network}"' \
    --subnet=192.169.193.0/28 \
    --ip-range=192.169.193.0/28 \
    --gateway=192.169.193.1')"

    /bin/bash -c "${command}"
}

function build_stack_images() {
    docker-compose -f ./provisioning/containers/docker-compose.yml \
    --project-name="$(get_project_name)" build
}

function stop_workers() {
    local project_name
    project_name=$(get_project_name)

    local symfony_environment
    symfony_environment="$(get_symfony_environment)"

    local script
    script='bin/console messenger:stop-workers -e prod'
    command="docker-compose --project-name=${project_name} exec -T -e ${symfony_environment} worker ${script}"

    echo '=> About to stop consumers'
    /bin/bash -c "${command}"
}

function consume_fetch_publication_messages {
    local command_suffix
    command_suffix="${1}"

    if [ -z "${command_suffix}" ];
    then
      command_suffix='publications'
    fi

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
        export PROJECT_DIR='/var/www/api'
    fi

    local minimum_execution_time=10
    if [ -n "${MINIMUM_EXECUTION_TIME}" ];
    then
        minimum_execution_time="${MINIMUM_EXECUTION_TIME}"
    fi

    remove_exited_containers

    local rabbitmq_output_log
    rabbitmq_output_log="./var/logs/rabbitmq.out.log"

    local rabbitmq_error_log
    rabbitmq_error_log="./var/logs/rabbitmq.error.log"

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

    command="docker-compose --project-name=${project_name} run --rm --name ${SUPERVISOR_PROCESS_NAME} -T -e ${symfony_environment} worker ${SCRIPT}"
    echo 'Executing command: "'$command'"'
    echo 'Logging standard output of RabbitMQ messages consumption in '"${rabbitmq_output_log}"
    echo 'Logging standard error of RabbitMQ messages consumption in '"${rabbitmq_error_log}"
    /bin/bash -c "$command >> ${rabbitmq_output_log} 2>> ${rabbitmq_error_log}"
    cd "../../"

    /bin/bash -c "sleep ${minimum_execution_time}"
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

function get_project_dir {
    local project_dir='/var/www/api'

    if [ -n "${PROJECT_DIR}" ];
    then
        project_dir="${PROJECT_DIR}"
    fi

    echo "${project_dir}"
}

# @see https://getcomposer.org/doc/articles/authentication-for-private-packages.md#github-oauth
function install_php_dependencies {
    local project_dir
    project_dir="$(get_project_dir)"

    local production_option
    production_option=''
    if [ -n "${APP_ENV}" ] && [ "${APP_ENV}" = 'prod' ];
    then
        production_option='--apcu-autoloader '
    fi

    if [ -z "${GITHUB_TOKEN}" ];
    then
        echo 'Please export a valid github token e.g.'
        echo 'export GITHUB_TOKEN="__fill_me__"'
        return 1
    fi

    local command
    command=$(echo -n '/bin/bash -c "cd '"${project_dir}"' &&
    source '"${project_dir}"'/bin/install-composer.sh &&
    php '"${project_dir}"'/composer.phar config github-oauth.github.com '"${GITHUB_TOKEN}"' \
    && '"${project_dir}"'/composer.phar install '"${production_option}"'--prefer-dist -n"')
    echo "${command}" | make run-php
}

function remove_exited_containers() {
    /bin/bash -c "docker ps -a | grep Exited | awk ""'"'{print $1}'"'"" | xargs docker rm -f >> /dev/null 2>&1"
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

    local project_name=''
    project_name="$(get_project_name)"

    local container_name
    container_name="$(echo "${script}" | sha256sum | awk '{print $1}')"

    local command

    if [ -z "${interactive_mode}" ];
    then
        command="$(echo -n 'cd provisioning/containers && \
        docker-compose --project-name='"${project_name}"' run -T --rm --name='"${container_name}"' '"${option_detached}"'worker '"${script}")"
    else
        command="$(echo -n 'cd provisioning/containers && \
        docker-compose --project-name='"${project_name}"' exec '"${option_detached}"'worker '"${script}")"
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

    local project_name=''
    project_name="$(get_project_name)"

    local command
    command=$(echo -n 'docker-compose --project-name='"${project_name}"' -f docker-compose.yml exec -T worker '"${arguments}")

    echo 'About to execute '"${command}"
    /bin/bash -c "${command}"
}

function run_stack() {
    ensure_blackfire_is_configured

    cd provisioning/containers || exit

    local project_name
    project_name="$(get_project_name)"

    docker-compose --project-name="${project_name}" up
    cd ../..
}

function run_worker() {
    cd provisioning/containers || exit

    local project_name
    project_name="$(get_project_name)"

    docker-compose --project-name="${project_name}" up worker
    cd ../..
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

function before_running_command() {
    make remove-php-container

    export XDEBUG_CONFIG="idekey='phpstorm-xdebug'"

    if [ -z "${PROJECT_DIR}" ];
    then
        export PROJECT_DIR='/var/www/api'
    fi
}

function run_command {
    local php_command
    php_command=${1}

    local rabbitmq_output_log
    rabbitmq_output_log="var/logs/rabbitmq.out.log"

    local rabbitmq_error_log
    rabbitmq_error_log="var/logs/rabbitmq.error.log"

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

    echo 'Logging standard output of worker in '"${rabbitmq_output_log}"
    echo 'Logging standard error of worker in '"${rabbitmq_error_log}"

    execute_command "${rabbitmq_output_log}" "${rabbitmq_error_log}"
}

function ensure_blackfire_is_configured() {
    local working_directory
    working_directory="$(pwd)"

    cd ./provisioning/containers/apache/templates/blackfire || exit

    if [ ! -e zz-blackfire.ini ];
    then
        cp zz-blackfire.ini{.dist,}
        echo 'Copied "zz-blackfire.ini" configuration file'
    else
        echo '"zz-blackfire.ini" configuration file already exists'
    fi

    if [ ! -e .blackfire.ini ];
    then
        cp .blackfire.ini{.dist,}
        echo 'Copied ".blackfire.ini" configuration file'
    else
        echo '".blackfire.ini" configuration file already exists'
    fi

    if [ ! -e agent ];
    then
        cp agent{.dist,}
        echo 'Copied "agent" configuration file'
    else
        echo '"agent" configuration file already exists'
    fi

    cd "${working_directory}" || exit
}

function dispatch_fetch_publications_messages {
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
    arguments="${priority_option}"' '"${list_option}"' '"${query_restriction}"' '"${username}"
    arguments="${arguments}${cursor_argument}"
    run_command 'bin/console devobs:dispatch-messages-to-fetch-member-statuses '"${arguments}"
}

function run_php_unit_tests() {
    if [ -z "${DEBUG}" ];
    then
        bin/phpunit -c ./phpunit.xml.dist --process-isolation
        return
    fi

    bin/phpunit -c ./phpunit.xml.dist --verbose --debug
}

function run_php_features_tests() {
    bin/behat -c ./behat.yml
}

function restart_web_server() {
    cd ./provisioning/containers || exit

    local project_name
    project_name="$(get_project_name)"

    docker-compose --project-name="${project_name}" restart web
}

function install_local_ca_store() {
    mkcert -install
}

function generate_development_tls_certificate_and_key() {
    local destination
    destination='./provisioning/containers/reverse-proxy/certificates'

    local project_name
    project_name="$(get_project_name)"

    local domain_name
    domain_name="api.local.${project_name}.app"

    mkcert \
      -cert-file="${destination}/${domain_name}.pem" \
      -key-file="${destination}/${domain_name}-key.pem" \
      "${domain_name}"
}

function create_test_database() {
  rm ./src/Twitter/Infrastructure/Database/Migrations/Version* -f

  export INTERACTIVE_MODE=true
  export SCRIPT='php bin/console cache:clear --no-warmup -e test -vvvv' && make run-php-script && \
  export SCRIPT='php bin/console cache:warmup -e test -vvvv' && make run-php-script && \
  export SCRIPT='php bin/console doc:database:drop --force --if-exists -e test -vvvv' && make run-php-script && \
  export SCRIPT='php bin/console doc:database:create -e test --if-not-exists -vvvv' && make run-php-script && \
  export SCRIPT='php bin/console doc:mig:diff -n -e test -vvvv' && make run-php-script && \
  export SCRIPT='php bin/console doc:mig:mig -n -e test -vvvv' && make run-php-script
}

function load_production_fixtures() {
  local script
  script="php bin/console devobs:load-production-fixtures \
    ${API_TWITTER_USER_TOKEN} \
    ${API_TWITTER_USER_SECRET} \
    ${API_TWITTER_CONSUMER_KEY} \
    ${API_TWITTER_CONSUMER_SECRET} \
    ${API_ACCESS_TOKEN} \
    -vvvv"

  run_php_script "${script}" 'interactive_mode'
}

function set_up_amqp_queues() {
  local script
  script='php bin/console messenger:setup-transports -vvvv'

  run_php_script "${script}" 'interactive_mode'
}

function list_amqp_queues() {
    local rabbitmq_vhost
    rabbitmq_vhost="$(get_rabbitmq_virtual_host)"

    cd provisioning/containers || exit

    local project_name
    project_name="$(get_project_name)"

    /bin/bash -c "docker-compose --project-name=${project_name} exec messenger watch -n1 'rabbitmqctl list_queues -p ${rabbitmq_vhost}'"
}

function get_rabbitmq_virtual_host() {
    local virtual_host
    virtual_host="$(cat <(cat .env.local | grep "PUBLICATIONS='amqp" | sed -E 's#.+(/.+)/[^/]*$#\1#' | sed -E 's/\/%2f/\//g'))"

    echo "${virtual_host}"
}

function purge_queues() {
    local rabbitmq_vhost
    rabbitmq_vhost="$(get_rabbitmq_virtual_host)"

    cd provisioning/containers || exit

    local project_name
    project_name="$(get_project_name)"

    /bin/bash -c "docker-compose --project-name=${project_name} exec -d messenger rabbitmqctl purge_queue publications -p ${rabbitmq_vhost}"
    /bin/bash -c "docker-compose --project-name=${project_name} exec -d messenger rabbitmqctl purge_queue failures -p ${rabbitmq_vhost}"
}

function stop_workers() {
    cd provisioning/containers || exit

    local project_name
    project_name="$(get_project_name)"

    /bin/bash -c "docker-compose --project-name=${project_name} exec worker bin/console messenger:stop-workers"
}
