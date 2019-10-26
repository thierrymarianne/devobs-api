<?php

namespace WeavingTheWeb\Bundle\ApiBundle\Entity;

use App\Aggregate\Entity\MemberAggregateSubscription;

interface MemberSubscriptionInterface
{

    /**
     * @return bool
     */
    public function isMemberAggregate(): bool;

    /**
     * @param MemberAggregateSubscription $memberAggregateSubscription
     *
     * @return $this
     */
    public function setMemberSubscription(
        MemberAggregateSubscription $memberAggregateSubscription
    ): self;
}