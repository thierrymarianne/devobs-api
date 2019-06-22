<?php

namespace App\Status\Entity;

use App\Member\Entity\MemberIdentity;

class StatusIdentity
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var MemberIdentity
     */
    private $memberIdentity;

    /**
     * @var string
     */
    private $twitterId;

    /**
     * @var \DateTime
     */
    private $publicationDateTime;
}
