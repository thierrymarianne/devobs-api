<?php
declare(strict_types=1);

namespace App\Twitter\Infrastructure\Curation\Repository;

use App\Twitter\Domain\Api\MemberOwnershipsAccessorInterface;
use App\Twitter\Domain\Api\Selector\ListSelectorInterface;
use App\Twitter\Domain\Curation\Exception\OwnershipBatchNotFoundException;
use App\Twitter\Domain\Curation\Repository\OwnershipBatchCollectedEventRepositoryInterface;
use App\Twitter\Domain\Resource\OwnershipCollection;
use App\Twitter\Domain\Resource\OwnershipCollectionInterface;
use App\Twitter\Domain\Resource\PublishersList;
use App\Twitter\Infrastructure\Curation\Entity\OwnershipBatchCollectedEvent;
use App\Twitter\Infrastructure\DependencyInjection\LoggerTrait;
use App\Twitter\Infrastructure\Operation\Correlation\CorrelationId;
use Assert\Assert;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Throwable;
use const JSON_THROW_ON_ERROR;

/**
 * @method OwnershipBatchCollectedEventRepositoryInterface|null find($id, $lockMode = null, $lockVersion = null)
 * @method OwnershipBatchCollectedEventRepositoryInterface|null findOneBy(array $criteria, array $orderBy = null)
 * @method OwnershipBatchCollectedEventRepositoryInterface[]    findAll()
 * @method OwnershipBatchCollectedEventRepositoryInterface[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OwnershipBatchCollectedEventRepository extends ServiceEntityRepository implements OwnershipBatchCollectedEventRepositoryInterface
{
    use LoggerTrait;

    public function collectedOwnershipBatch(
        MemberOwnershipsAccessorInterface $accessor,
        ListSelectorInterface $selector
    ): OwnershipCollectionInterface {
        $event               = $this->startCollectOfOwnershipBatch($selector);
        $ownershipCollection = $accessor->getMemberOwnerships($selector);
        $this->finishCollectOfOwnershipBatch(
            $event,
            json_encode(
                [
                    'method'   => __METHOD__,
                    'options'  => [
                        self::OPTION_SCREEN_NAME => $selector->screenName(),
                        self::OPTION_NEXT_PAGE => $selector->cursor(),
                    ],
                    'response' => array_map(
                        fn (PublishersList $ownership) => $ownership->toArray(),
                        $ownershipCollection->toArray()
                    )
                ],
                JSON_THROW_ON_ERROR
            ),
            $ownershipCollection->isEmpty()
        );

        return $ownershipCollection;
    }

    private function finishCollectOfOwnershipBatch(
        OwnershipBatchCollectedEvent $event,
        string $payload,
        bool $nothingToSave
    ): OwnershipBatchCollectedEvent {
        $event->finishCollect($payload);

        if ($nothingToSave) {
            return $this->remove($event);
        }

        return $this->save($event);
    }

    private function save(OwnershipBatchCollectedEvent $event): OwnershipBatchCollectedEvent
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

    private function remove(OwnershipBatchCollectedEvent $event): OwnershipBatchCollectedEvent
    {
        $entityManager = $this->getEntityManager();

        try {
            $entityManager->remove($event);
            $entityManager->flush();
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage());
        }

        return $event;
    }

    private function startCollectOfOwnershipBatch(
        ListSelectorInterface $selector
    ): OwnershipBatchCollectedEvent {
        $now = new \DateTimeImmutable();

        $event = new OwnershipBatchCollectedEvent(
            $selector,
            $now,
            $now
        );

        return $this->save($event);
    }

    public function byScreenName(string $screenName): OwnershipCollectionInterface
    {
        $connection = $this->getEntityManager()->getConnection();

        $query =<<<QUERY
            SELECT correlation_id
            FROM ownership_batch_collected_event e,
            (
                SELECT 
                max(occurred_at) most_recent_occurrence_date,
                screen_name
                FROM ownership_batch_collected_event
                WHERE screen_name = :screen_name
                AND payload IS NOT NULL
                GROUP BY screen_name 
            ) _subquery
            WHERE (e.occurred_at, e.screen_name) IN ((
                _subquery.most_recent_occurrence_date,
                _subquery.screen_name
            )) and payload IS NOT NULL
QUERY
;
        $statement = $connection->executeQuery($query, ['screen_name' => $screenName]);
        $record = $statement->fetchAssociative();

        if (!empty($record)) {
            $batches = $this->findBy(['correlationId' => CorrelationId::fromString($record['correlation_id'])]);
            $lists = array_map(
                static function (OwnershipBatchCollectedEvent $event) {
                    $decodedPayload = json_decode(
                        $event->payload(),
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );

                    Assert::lazy()
                        ->that($decodedPayload)->isArray()
                        ->that($decodedPayload)->keyExists('response')
                        ->that($decodedPayload['response'])->isArray()
                    ->tryAll();

                    return array_map(
                        static fn ($publishersList) => new PublishersList($publishersList['id'], $publishersList['name']),
                        $decodedPayload['response']
                    );
                },
                $batches,
            );

            $mergedLists = array_merge([], ...$lists);

            usort(
                $mergedLists,
                static fn (PublishersList $leftList, PublishersList $rightList) => $leftList->id() <=> $rightList->id()
            );

            return OwnershipCollection::fromArray($mergedLists);
        }

        OwnershipBatchNotFoundException::throws($screenName);
    }
}