<?php

namespace App\Member\Repository;

use App\Http\PaginationParams;
use App\Member\Entity\MemberSubscription;
use App\Member\MemberInterface;
use Doctrine\DBAL\Statement;
use Doctrine\ORM\EntityRepository;
use JsonSchema\Exception\JsonDecodingException;
use WTW\UserBundle\Repository\UserRepository;

class MemberSubscriptionRepository extends EntityRepository
{
    /**
     * @var UserRepository
     */
    public $memberRepository;

    /**
     * @param MemberInterface $member
     * @param MemberInterface $subscription
     * @return MemberSubscription
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function saveMemberSubscription(
        MemberInterface $member,
        MemberInterface $subscription
    ) {
        $memberSubscription = $this->findOneBy(['member' => $member, 'subscription' => $subscription]);

        if (!($memberSubscription instanceof MemberSubscription)) {
            $memberSubscription = new MemberSubscription($member, $subscription);
        }

        $this->getEntityManager()->persist($memberSubscription->markAsNotBeingCancelled());
        $this->getEntityManager()->flush();

        return $memberSubscription;
    }

    /**
     * @param MemberInterface $member
     * @param array           $subscriptions
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function findMissingSubscriptions(MemberInterface $member, array $subscriptions)
    {
        $query = <<< QUERY
            SELECT GROUP_CONCAT(sm.usr_twitter_id) subscription_ids
            FROM member_subscription s,
            weaving_user sm
            WHERE sm.usr_id = s.subscription_id
            AND member_id = :member_id1
            AND (s.has_been_cancelled IS NULL OR s.has_been_cancelled = 0)
            AND sm.usr_twitter_id is not null
            AND sm.usr_twitter_id in (:subscription_ids)
QUERY;

        $connection = $this->getEntityManager()->getConnection();
        $statement = $connection->executeQuery(
            strtr(
                $query,
                [
                    ':member_id' => $member->getId(),
                    ':subscription_ids' => (string) implode(',', $subscriptions)
                ]
            )
        );

        $results = $statement->fetchAll();

        $remainingSubscriptions = $subscriptions;
        if (array_key_exists(0, $results) && array_key_exists('subscription_ids', $results[0])) {
            $subscriptionIds = array_map(
                'intval',
                explode(',', $results[0]['subscription_ids'])
            );
            $remainingSubscriptions = array_diff(
                array_values($subscriptions),
                $subscriptionIds
            );
        }

        return $remainingSubscriptions;
    }

    /**
     * @param MemberInterface $member
     * @return bool
     * @throws \Doctrine\DBAL\DBALException
     */
    public function cancelAllSubscriptionsFor(MemberInterface $member)
    {
        $query = <<< QUERY
            UPDATE member_subscription ms, weaving_user u
            SET has_been_cancelled = 1
            WHERE ms.member_id = :member_id
            AND ms.subscription_id = u.usr_id
            AND u.suspended = 0
            AND u.protected = 0
            AND u.not_found = 0
QUERY;

        $connection = $this->getEntityManager()->getConnection();
        $statement = $connection->executeQuery(
            strtr(
                $query,
                [':member_id' => $member->getId()]
            )
        );

        return $statement->closeCursor();
    }

    /**
     * @param MemberInterface  $member
     * @param PaginationParams $paginationParams
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getMemberSubscriptions(MemberInterface $member, PaginationParams $paginationParams): array
    {
        $memberSubscriptions = [];

        $totalPages = $this->countMemberSubscriptions($member);
        if ($totalPages) {
            $memberSubscriptions = $this->selectMemberSubscriptions($member, $paginationParams);
        }

        return [
            'subscriptions' => $memberSubscriptions,
            'total_subscriptions' => $totalPages,
        ];
    }

    /**
     * @param MemberInterface $member
     * @return array|mixed
     * @throws \Doctrine\DBAL\DBALException
     */
    public function countMemberSubscriptions(MemberInterface $member)
    {
        $queryTemplate = <<< QUERY
            SELECT 
            {selection}
            {constraints}
QUERY;
        $query = strtr($queryTemplate, [
            '{selection}' => 'COUNT(*) count_',
            '{constraints}' => $this->getConstraints(),
        ]);

        $connection = $this->getEntityManager()->getConnection();
        $statement = $connection->executeQuery(
            strtr(
                $query,
                [
                    ':member_id' => $member->getId(),
                ]
            )
        );

        $results = $statement->fetchAll();
        if (!array_key_exists(0, $results) || !array_key_exists('count_', $results[0])) {
            return 0;
        }

        return $results[0]['count_'];
    }

    /**
     * @param MemberInterface  $member
     * @param PaginationParams $paginationParams
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function selectMemberSubscriptions(MemberInterface $member, PaginationParams $paginationParams)
    {
        $queryTemplate = <<< QUERY
            SELECT 
            {selection}
            {constraints}
            GROUP BY u.usr_twitter_username
            ORDER BY u.usr_twitter_username ASC            
            LIMIT :offset, :page_size
QUERY;
        $query = strtr($queryTemplate, [
            '{selection}' => $this->getSelection(),
            '{constraints}' => $this->getConstraints(),
        ]);

        $connection = $this->getEntityManager()->getConnection();
        $statement = $connection->executeQuery(
            strtr(
                $query,
                [
                    ':member_id' => $member->getId(),
                    ':offset' => $paginationParams->getFirstItemIndex(),
                    ':page_size' => $paginationParams->pageSize,
                ]
            )
        );

        $results = $statement->fetchAll();
        if (!array_key_exists(0, $results)) {
            return [];
        }

        return array_map(function (array $row) {
            $row['aggregates'] = json_decode($row['aggregates'], $asArray = true);

            $lastJsonError = json_last_error();
            if ($lastJsonError !== JSON_ERROR_NONE) {
                throw new JsonDecodingException($lastJsonError);
            }

            return $row;
        }, $results);
    }

    public function getSelection()
    {
        return <<<QUERY
            u.usr_twitter_username as username,
            u.usr_twitter_id as member_id,
            u.description,
            u.url,
            IF (
              COALESCE(a.id, 0),
              CONCAT(
                '{',
                GROUP_CONCAT(
                  CONCAT('"', a.id, '": "', a.name, '"') SEPARATOR ","
                ), 
                '}'
              ),
              '{}'
            ) as aggregates
QUERY;
    }

    /**
     * @return string
     */
    public function getConstraints()
    {
        return <<<QUERY
            FROM member_subscription ms,
            weaving_user u
            LEFT JOIN weaving_aggregate a
            ON a.screen_name = u.usr_twitter_username
            AND a.name NOT LIKE 'user ::%'
            WHERE member_id = :member_id 
            AND ms.has_been_cancelled = 0
            AND ms.subscription_id = u.usr_id
            AND u.suspended = 0
            AND u.protected = 0
            AND u.not_found = 0
QUERY;
    }
}
