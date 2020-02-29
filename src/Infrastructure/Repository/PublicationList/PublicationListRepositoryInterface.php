<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository\PublicationList;

use App\Domain\Publication\PublicationListInterface;

/**
 * @method PublicationListInterface|null find($id, $lockMode = null, $lockVersion = null)
 * @method PublicationListInterface|null findOneBy(array $criteria, array $orderBy = null)
 * @method PublicationListInterface[]    findAll()
 * @method PublicationListInterface[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
interface PublicationListRepositoryInterface
{
    public function make(string $screenName, string $listName);

    public function unlockAggregate(PublicationListInterface $publicationList);
}