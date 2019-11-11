<?php

namespace App\Member\Consumer;

use App\Amqp\AmqpMessageAwareTrait;
use App\Member\Repository\NetworkRepository;
use Exception;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

class NetworkConsumer implements ConsumerInterface
{
    use AmqpMessageAwareTrait;

    /**
     * @var LoggerInterface
     */
    public $logger;

    /**
     * @var NetworkRepository
     */
    public $networkRepository;

    /**
     * @param AMQPMessage $message
     *
     * @return bool
     */
    public function execute(AMQPMessage $message): bool
    {
        try {
            $members = $this->parseMessage($message);
            $this->networkRepository->saveNetwork($members);
        } catch (Exception $exception) {
            $this->logger->critical($exception->getMessage());

            return false;
        }

        return true;
    }
}
