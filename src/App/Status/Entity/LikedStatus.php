<?php

namespace App\Status\Entity;

use App\Member\MemberInterface;
use App\TimeRange\TimeRangeAwareTrait;
use App\TimeRange\TimeRangeAwareInterface;
use WeavingTheWeb\Bundle\ApiBundle\Entity\Aggregate;
use WeavingTheWeb\Bundle\ApiBundle\Entity\ArchivedStatus;
use WeavingTheWeb\Bundle\ApiBundle\Entity\Status;
use WeavingTheWeb\Bundle\ApiBundle\Entity\StatusInterface;

class LikedStatus implements TimeRangeAwareInterface
{
    use TimeRangeAwareTrait;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    private $id;

    /**
     * @var Status
     */
    private $status;

    /**
     * @var ArchivedStatus
     */
    private $archivedStatus;

    /**
     * @var bool
     */
    private $isArchivedStatus = false;

    /**
     * @var Aggregate
     */
    private $aggregate;

    /**
     * @var string
     */
    private $aggregateName;

    /**
     * @param Aggregate $aggregate
     * @return $this
     */
    public function setAggregate(Aggregate $aggregate): self
    {
        $this->aggregate = $aggregate;
        $this->aggregateName = $aggregate->getName();

        return $this;
    }

    /**
     * @var MemberInterface
     */
    private $member;

    /**
     * @var MemberInterface
     */
    private $likedBy;

    /**
     * @var string
     */
    private $memberName;

    /**
     * @var string
     */
    private $likedByMemberName;

    /**
     * @var int
     */
    private $timeRange;

    /**
     * @var \DateTime
     */
    private $publicationDateTime;

    /**
     * @param StatusInterface $status
     */
    public function __construct(
        StatusInterface $status,
        MemberInterface $likedBy,
        Aggregate $aggregate,
        MemberInterface $member
    ) {
        if ($status instanceof Status) {
            $this->status = $status;
        }

        if ($status instanceof ArchivedStatus) {
            $this->archivedStatus = $status;
            $this->isArchivedStatus = true;
        }

        $this->setAggregate($aggregate);

        $this->member = $member;
        $this->memberName = $status->getScreenName();

        $this->likedBy = $likedBy;
        $this->likedByMemberName  = $likedBy->getTwitterUsername();

        $this->publicationDateTime = $status->getCreatedAt();

        $this->updateTimeRange();
    }
}
