<?php

namespace App\Member\Entity;

use App\Member\MemberInterface;

class MemberIdentity
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
    private $screenName;

    /**
     * @var int
     */
    private $twitterId;
}
