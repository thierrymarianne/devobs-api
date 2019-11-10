FROM php:7.3.10-cli-stretch

# Update package lists for upgrades
# @see https://askubuntu.com/a/222352/172316
RUN apt-get update && apt install -y git apt-utils gnupg2 wget unzip libcurl4-gnutls-dev && \
    /bin/bash -c "wget -q -O - https://packages.blackfire.io/gpg.key | apt-key add -" && \
    echo "deb http://packages.blackfire.io/debian any main" | tee /etc/apt/sources.list.d/blackfire.list && \
    apt-get update && \
    cd /tmp && \
    # Install PHP extensions (bcmath, blackfire, mysql, pcntl, sockets, xdebug)
    docker-php-ext-install bcmath mysqli pcntl pdo_mysql && \
    apt -y install blackfire-agent blackfire-agent blackfire-php && \
    docker-php-ext-install sockets && \
    wget https://github.com/xdebug/xdebug/archive/2.7.2.zip && \
    unzip 2.7.2.zip && cd xdebug-2.7.2 && phpize . && ./configure --with-php-config=`which php-config` && make && make install && \
    # DataDog PHP Tracer extension
    wget https://github.com/DataDog/dd-trace-php/archive/0.29.0.tar.gz -O /tmp/datadog-php-tracer.tar.gz && \
    cd /tmp && tar -xvzf /tmp/datadog-php-tracer.tar.gz && cd dd-trace-php-0.29.0 && \
    phpize . && ./configure --with-php-config=`which php-config` && make && make install && \
    # Add user to run application as someone else than root
    groupadd -g 1001 jenkins && useradd -s /bin/bash -u 1001 -g root -g jenkins -G www-data jenkins

ADD templates/20-xdebug.ini.dist /usr/local/etc/php/conf.d/20-xdebug.ini

CMD ["php", "-a"]
