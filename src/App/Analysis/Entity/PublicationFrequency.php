<?php

namespace App\Analysis\Entity;

use App\Member\MemberInterface;

class PublicationFrequency
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var MemberInterface
     */
    private $member;

    /**
     * @var string
     */
    private $perDayOfWeek;

    /**
     * @var string
     */
    private $perHourOfDay;

    /**
     * @var \DateTime
     */
    private $updatedAt;
}
