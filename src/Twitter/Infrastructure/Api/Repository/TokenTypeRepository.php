<?php

declare(strict_types=1);

namespace App\Twitter\Infrastructure\Api\Repository;

use App\Twitter\Domain\Api\Repository\TokenTypeRepositoryInterface;
use App\Twitter\Infrastructure\Api\Entity\TokenType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * @method TokenType|null find($id, $lockMode = null, $lockVersion = null)
 * @method TokenType|null findOneBy(array $criteria, array $orderBy = null)
 * @method TokenType[]    findAll()
 * @method TokenType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TokenTypeRepository extends ServiceEntityRepository implements TokenTypeRepositoryInterface
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    public function __construct(
        ManagerRegistry $registry,
        string $entityClass,
        LoggerInterface $logger
    )
    {
        $this->logger = $logger;
        parent::__construct($registry, $entityClass);
    }

    public function ensureTokenTypesExist(): void
    {
        $entityManager = $this->getEntityManager();

        try {
            $applicationTokenTypes = $this->findBy(['name' => TokenType::APPLICATION]);
            if (empty($applicationTokenTypes)) {
                $applicationTokenType = self::applicationTokenType();
                $entityManager->persist($applicationTokenType);
            }

            $userTokenTypes = $this->findBy(['name' => TokenType::USER]);
            if (empty($userTokenTypes)) {
                $userTokenType = self::userTokenType();
                $entityManager->persist($userTokenType);
            }

            $entityManager->flush();
        } catch (ORMException $exception) {
              $this->logger->error($exception->getMessage(), ['exception' => $exception]);
        }
    }

    public static function applicationTokenType(): TokenType
    {
        return new TokenType(TokenType::APPLICATION);
    }

    public static function userTokenType(): TokenType
    {
        return new TokenType(TokenType::USER);
    }
}
