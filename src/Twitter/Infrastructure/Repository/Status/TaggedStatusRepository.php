<?php
declare(strict_types=1);

namespace App\Twitter\Infrastructure\Repository\Status;

use App\Twitter\Infrastructure\Publication\Entity\PublishersList;
use App\Twitter\Infrastructure\Api\Entity\ArchivedStatus;
use App\Twitter\Infrastructure\Api\Entity\Status;
use App\Twitter\Domain\Publication\Repository\TaggedStatusRepositoryInterface;
use App\Twitter\Domain\Publication\StatusInterface;
use App\Twitter\Infrastructure\Publication\Dto\TaggedStatus;
use App\Twitter\Infrastructure\DependencyInjection\Status\StatusRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Psr\Log\LoggerInterface;

class TaggedStatusRepository extends ServiceEntityRepository implements TaggedStatusRepositoryInterface
{
    use StatusRepositoryTrait;

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->entityManager    = $entityManager;
        $this->logger           = $logger;
    }

    public function convertPropsToStatus(
        array $properties,
        ?PublishersList $aggregate
    ): StatusInterface {
        $taggedStatus = TaggedStatus::fromLegacyProps($properties);

        if ($this->statusHavingHashExists($taggedStatus->hash())) {
            return $this->statusRepository->reviseDocument($taggedStatus);
        }

        return $taggedStatus->toStatus(
            $this->entityManager,
            $this->logger,
            $aggregate
        );
    }

    /**
     * @param $hash
     *
     * @return bool
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function archivedStatusHavingHashExists(string $hash): bool
    {
        $queryBuilder = $this->entityManager
            ->getRepository(ArchivedStatus::class)
            ->createQueryBuilder('s');
        $queryBuilder->select('count(s.id) as count_')
                     ->andWhere('s.hash = :hash');

        $queryBuilder->setParameter('hash', $hash);
        $count = (int) $queryBuilder->getQuery()->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * @param $hash
     *
     * @return bool
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function statusHavingHashExists(string $hash): bool
    {
        if ($this->archivedStatusHavingHashExists($hash)) {
            return true;
        }

        $queryBuilder = $this->entityManager
            ->getRepository(Status::class)
            ->createQueryBuilder('s');
        $queryBuilder->select('count(s.id) as count_')
                     ->andWhere('s.hash = :hash');

        $queryBuilder->setParameter('hash', $hash);
        $count = (int) $queryBuilder->getQuery()->getSingleScalarResult();

        return $count > 0;
    }
}