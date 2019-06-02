<?php

namespace App\Member\Repository;

use App\Member\Entity\MemberSubscription;
use App\Member\MemberInterface;
use Doctrine\ORM\EntityRepository;
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

        $this->getEntityManager()->persist($memberSubscription);
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
            AND member_id = :member_id
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
     * @return mixed[]
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getMemberSubscriptions(MemberInterface $member)
    {
        $query = <<< QUERY
            SELECT 
            u.usr_twitter_username as username,
            u.usr_twitter_id as member_id,
            u.description,
            u.url
            FROM member_subscription ms,
            weaving_user u
            WHERE member_id = :member_id 
            AND ms.subscription_id = u.usr_id
            AND u.suspended = 0
            AND u.not_found = 0
QUERY;

        $connection = $this->getEntityManager()->getConnection();
        $statement = $connection->executeQuery(
            strtr(
                $query,
                [':member_id' => $member->getId(),]
            )
        );

        return $statement->fetchAll();
    }
}
