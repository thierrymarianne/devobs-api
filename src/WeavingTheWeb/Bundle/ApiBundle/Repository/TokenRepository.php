<?php

namespace WeavingTheWeb\Bundle\ApiBundle\Repository;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityRepository,
    Doctrine\ORM\NoResultException;

use Psr\Log\LoggerInterface;

use WeavingTheWeb\Bundle\ApiBundle\Entity\Token,
    WeavingTheWeb\Bundle\ApiBundle\Entity\TokenType;

/**
 * @author Thierry Marianne <thierry.marianne@weaving-the-web.org>
 */
class TokenRepository extends EntityRepository
{
    /**
     * @param $properties
     * @return Token
     */
    public function makeToken($properties)
    {
        $token = new Token();

        $now = new \DateTime();
        $token->setCreatedAt($now);
        $token->setUpdatedAt($now);

        $tokenRepository = $this->getEntityManager()->getRepository('WeavingTheWebApiBundle:TokenType');

        /** @var \WeavingTheWeb\Bundle\ApiBundle\Entity\TokenType $tokenType */
        $tokenType = $tokenRepository->findOneBy(['name' => TokenType::USER]);
        $token->setType($tokenType);

        $token->setOauthToken($properties['oauth_token']);
        $token->setOauthTokenSecret($properties['oauth_token_secret']);

        return $token;
    }

    /**
     * @param $oauthToken
     * @param string $until
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function freezeToken($oauthToken, $until = 'now + 15min')
    {
        $entityManager = $this->getEntityManager();

        /**
         * @var \WeavingTheWeb\Bundle\ApiBundle\Entity\Token $token
         */
        $token = $this->findOneBy(['oauthToken' => $oauthToken]);
        $token->setFrozenUntil(new \DateTime($until));

        $entityManager->persist($token);
        $entityManager->flush($token);
    }

    /**
     * @param $oauthToken
     * @param LoggerInterface|null $logger
     * @return Token
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function refreshFreezeCondition($oauthToken, LoggerInterface $logger = null)
    {
        $frozen = false;

        /** @var \WeavingTheWeb\Bundle\ApiBundle\Entity\Token $token */
        $token = $this->findOneBy(['oauthToken' => $oauthToken]);

        if (is_null($token)) {
            $token = $this->makeToken(['oauth_token' => $oauthToken, 'oauth_token_secret' => '']);

            $entityManager = $this->getEntityManager();
            $entityManager->persist($token);
            $entityManager->flush();

            $logger->info('[token creation] ' . $token->getOauthToken());
        } elseif ($this->isTokenFrozen($token)) {
            /**
             * The token is frozen if the "frozen until" date is in the future
             */
            $frozen = true;
        }
        /**
         *  else {
         *      The token was frozen but not anymore as "frozen until" date is now in the past
         *  }
         */

        $token->setFrozen($frozen);

        return $token;
    }

    /**
     * @param Token $token
     * @return bool
     */
    protected function isTokenFrozen(Token $token)
    {
        return !is_null($token->getFrozenUntil()) &&
            $token->getFrozenUntil()->getTimestamp() >
                (new \DateTime('now', new \DateTimeZone('UTC')))
                    ->getTimestamp();
    }

    /**
     * @param $oauthToken
     * @return bool
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function isOauthTokenFrozen($oauthToken)
    {
        $token = $this->findUnfrozenToken($oauthToken);

        return !($token instanceof Token);
    }

    /**
     * @param string $token
     * @return mixed|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findUnfrozenToken(string $token)
    {
        $queryBuilder = $this->createQueryBuilder('t');

        $queryBuilder->andWhere('t.oauthToken = :token');
        $queryBuilder->setParameter('token', $token);

        $this->applyUnfrozenTokenCriteria($queryBuilder);

        try {
            return $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException $exception) {
            return null;
        }
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @return QueryBuilder
     */
    private function applyUnfrozenTokenCriteria(QueryBuilder $queryBuilder): QueryBuilder
    {
        $queryBuilder->andWhere('t.type = :type');
        $tokenRepository = $this->getEntityManager()->getRepository('WeavingTheWebApiBundle:TokenType');
        $tokenType = $tokenRepository->findOneBy(['name' => TokenType::USER]);
        $queryBuilder->setParameter('type', $tokenType);

        $queryBuilder->andWhere('t.oauthTokenSecret IS NOT NULL');

        $queryBuilder->andWhere('(t.frozenUntil IS NULL or t.frozenUntil < NOW())');

        return $queryBuilder->setMaxResults(1);
    }

    /**
     * @param $applicationToken
     * @param $accessToken
     * @return Token
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function persistBearerToken($applicationToken, $accessToken)
    {
        $tokenRepository = $this->getEntityManager()->getRepository('WeavingTheWebApiBundle:TokenType');
        $tokenType = $tokenRepository->findOneBy(['name' => TokenType::APPLICATION]);

        $token = new Token();
        $token->setOauthToken($applicationToken);
        $token->setOauthTokenSecret($accessToken);
        $token->setType($tokenType);
        $token->setCreatedAt(new \DateTime());

        $entityManager = $this->getEntityManager();
        $entityManager->persist($token);
        $entityManager->flush();

        return $token;
    }

    /**
     * @return mixed|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findFirstUnfrozenToken()
    {
        $queryBuilder = $this->createQueryBuilder('t');

        $this->applyUnfrozenTokenCriteria($queryBuilder);

        try {
            return $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException $exception) {
            return null;
        }
    }

    /**
     * @return mixed|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findFirstFrozenToken()
    {
        $queryBuilder = $this->createQueryBuilder('t');

        $tokenRepository = $this->getEntityManager()->getRepository('WeavingTheWebApiBundle:TokenType');
        $tokenType = $tokenRepository->findOneBy(['name' => TokenType::USER]);

        $queryBuilder->andWhere('t.type = :type');
        $queryBuilder->setParameter('type', $tokenType);

        $queryBuilder->andWhere('t.oauthTokenSecret IS NOT NULL');

        $queryBuilder->andWhere('t.frozenUntil > :now');
        $queryBuilder->setParameter(
            'now',
            new \DateTime('now', new \DateTimeZone('UTC'))
        );

        $queryBuilder->setMaxResults(1);
        $queryBuilder->orderBy('t.frozenUntil', 'ASC');

        try {
            return $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException $exception) {
            return null;
        }
    }

    /**
     * @param string $token
     * @return mixed
     * @throws NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findTokenOtherThan(string $token)
    {
        $queryBuilder = $this->createQueryBuilder('t');
        $queryBuilder->andWhere('t.oauthToken != :token');
        $queryBuilder->setParameter('token', $token);

        $queryBuilder->andWhere('t.frozenUntil < :now');
        $queryBuilder->setParameter('now', new \DateTime('now', new \DateTimeZone('UTC')));

        $queryBuilder->setMaxResults(1);

        return $queryBuilder->getQuery()->getSingleResult();
    }
}
