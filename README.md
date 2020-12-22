# DevObs

Easing observation of Twitter lists related to software development

## Installation

The shell scripts written to install the project dependencies
have been tested under Ubuntu 20.04.

### Requirements

Install git by following instructions from the [official documentation](https://git-scm.org/).

Install Docker by following instructions from the [official documentation](https://docs.docker.com/install/linux/docker-ce/ubuntu/).

Install Docker compose by following instructions from the [official documentation](https://docs.docker.com/compose/install/).

Intall all PHP vendors

```
make install-php-dependencies
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

## Testing

Run unit tests with PHPUnit 

```
make run-php-unit-tests
```
