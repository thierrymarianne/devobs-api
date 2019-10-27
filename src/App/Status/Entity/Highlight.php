<?php

namespace App\Status\Entity;

use App\Member\MemberInterface;
use DateTime;
use Predis\Configuration\Option\Aggregate;
use WeavingTheWeb\Bundle\ApiBundle\Entity\Status;
use WeavingTheWeb\Bundle\ApiBundle\Entity\StatusInterface;
use WTW\UserBundle\Entity\User;

class Highlight
{
    private $id;

    /**
     * @var DateTime
     */
    private $publicationDateTime;

    /**
     * @var Status
     */
    private $status;

    /**
     * @var User
     */
    private $member;

    /**
     * @var boolean
     */
    private $isRetweet;

    /**
     * @var Aggregate
     */
    private $aggregate;

    /**
     * @var string
     */
    private $aggregateName;

    /**
     * @var DateTime
     */
    private $retweetedStatusPublicationDate;

    /**
     * @var int
     */
    private $totalRetweets;

    /**
     * @var int
     */
    private $totalFavorites;

    public function __construct(
        MemberInterface $member,
        StatusInterface $status,
        DateTime $publicationDateTime
    ) {
        $this->publicationDateTime = $publicationDateTime;
        $this->member = $member;
        $this->status = $status;
    }
}
