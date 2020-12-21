# Devobs

[![Codeship Status for thierrymarianne/daily-press-review](https://app.codeship.com/projects/24369620-8f96-0136-7068-0e8ef5ba2310/status?branch=main)](https://app.codeship.com/projects/304052)

Easing observation of Twitter lists related to software development

## Installation

The shell scripts written to install the project dependencies
have been tested under Ubuntu 19.10.

### Requirements

Install git by following instructions from the [official documentation](https://git-scm.org/).

Install Docker by following instructions from the [official documentation](https://docs.docker.com/install/linux/docker-ce/ubuntu/).

Install Docker compose by following instructions from the [official documentation](https://docs.docker.com/compose/install/).

Intall all PHP vendors

```
make install-php-dependencies
```

Require a PHP vendor

```
export VENDOR_NAME='symfony/symfony:^3.4.x' && make add-php-dependency
```

Remove a PHP vendor

```
export VENDOR_NAME='symfony/symfony' && make remove-php-dependency
```

### MySQL

```
# Generate queries to be executed
# when the project data model has been modified
make diff-schema
```

### RabbitMQ

List AMQP messages

```
make list-amqp-messages
```

## Running containers

```
make run-stack
```

## Available commands

Add members to a list

```
bin/console add-members-to-aggregate -e prod \
--member-name="username-of-list-owner" \
--aggregate-name="list-name" \
--member-list="member-username"
```

Import subscriptions related to a list

```
bin/console import-aggregates -e prod \
--member-name="username-of-list-owner" \
--find-ownerships
```

Add members from a list to another list 
(requires importing source list beforehand)

```
bin/console add-members-to-aggregate -e prod \
--member-name="username-of-list-owner" \
--aggregate-name="name-of-destination-list" 
--list="name-of-source-list"
```

Search statuses with regards to a given topic

```
export $USERNAME='__FILL_ME__'
export $TOPIC='__FILL_ME__'
app/console wtw:amqp:tw:prd:lm \
--screen_name=$USERNAME \
--query_restriction=$TOPIC \
--env=dev
```

Import the network of subscriptions / subscribees of a Twitter member 

```
export $USERNAME='__FILL_ME__'
app/console.php import-network -e prod \
--member-name=$USERNAME
```

## Testing

Run unit tests with PHPUnit 

```
make run-php-unit-tests
```
