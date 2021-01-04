<?php
declare(strict_types=1);

namespace App\Twitter\Infrastructure\Curation\Repository;

use App\Twitter\Domain\Api\Accessor\ApiAccessorInterface;
use App\Twitter\Domain\Api\Resource\MemberCollectionInterface;
use App\Twitter\Domain\Curation\Exception\PublishersListNotFoundException;
use App\Twitter\Domain\Curation\Repository\PublishersListCollectedEventRepositoryInterface;
use App\Twitter\Domain\Resource\MemberIdentity;
use App\Twitter\Infrastructure\Api\Resource\MemberCollection;
use App\Twitter\Infrastructure\Curation\Entity\PublishersListCollectedEvent;
use App\Twitter\Infrastructure\DependencyInjection\Api\ApiAccessorTrait;
use App\Twitter\Infrastructure\DependencyInjection\LoggerTrait;
use Assert\Assert;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Throwable;
use const JSON_THROW_ON_ERROR;

class PublishersListCollectedEventRepository extends ServiceEntityRepository implements PublishersListCollectedEventRepositoryInterface
{
    use LoggerTrait;
    use ApiAccessorTrait;

    public function collectedPublishersList(
        ApiAccessorInterface $accessor,
        array $options
    ): MemberCollectionInterface {
        $listId   = $options[self::OPTION_PUBLISHERS_LIST_ID];
        $listName = $options[self::OPTION_PUBLISHERS_LIST_NAME];

        try {
            $membershipCollection = $this->byListId($listId);
        } catch (PublishersListNotFoundException $e) {
            $event                = $this->startCollectOfPublishersList(
                $listId,
                $listName
            );
            $membershipCollection = $accessor->getListMembers($listId);
            $this->finishCollectOfPublishersList(
                $event,
                json_encode(
                    [
                        'method'   => 'getListMembers',
                        'options'  => $options,
                        'response' => array_map(
                            static fn (MemberIdentity $memberIdentity) => $memberIdentity->toArray(),
                            $membershipCollection->toArray()
                        )
                    ],
                    JSON_THROW_ON_ERROR
                )
            );
        }

        return $membershipCollection;
    }

    public function byListId(string $listId): MemberCollectionInterface
    {
        $event = $this->findOneBy(['listId' => $listId], ['occurredAt' => 'DESC']);

        if ($event instanceof PublishersListCollectedEvent) {
            $decodedPayload = json_decode($event->payload(), true, 512, JSON_THROW_ON_ERROR);

            Assert::lazy()
                ->that($decodedPayload)->isArray()
                ->that($decodedPayload)->keyExists('response')
                ->that($decodedPayload['response'])->isArray()
            ->tryAll();

            return MemberCollection::fromArray(array_map(
                static fn ($member) => new MemberIdentity($member['screen_name'], $member['id']),
                $decodedPayload['response']
            ));
        }

        PublishersListNotFoundException::throws($listId);
    }

    private function finishCollectOfPublishersList(
        PublishersListCollectedEvent $event,
        string $payload
    ): PublishersListCollectedEvent {
        $event->finishCollect($payload);

        return $this->save($event);
    }

    private function save(PublishersListCollectedEvent $event): PublishersListCollectedEvent
    {
        $entityManager = $this->getEntityManager();

        try {
            $entityManager->persist($event);
            $entityManager->flush();
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage());
        }

        return $event;
    }

    private function startCollectOfPublishersList(
        string $listId,
        string $listName
    ): PublishersListCollectedEvent {
        $now = new \DateTimeImmutable();

        $event = new PublishersListCollectedEvent(
            $listId,
            $listName,
            $now,
            $now
        );

        return $this->save($event);
    }
}