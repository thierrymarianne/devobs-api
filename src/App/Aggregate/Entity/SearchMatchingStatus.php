<?php

namespace App\Aggregate\Entity;

use App\TimeRange\TimeRangeAwareInterface;
use App\TimeRange\TimeRangeAwareTrait;
use WeavingTheWeb\Bundle\ApiBundle\Entity\Status;
use WeavingTheWeb\Bundle\ApiBundle\Entity\StatusInterface;

class SearchMatchingStatus implements TimeRangeAwareInterface
{
    use TimeRangeAwareTrait;

    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $memberName;

    /**
     * @var \DateTime
     */
    private $publicationDateTime;

    /**
     * @var Status
     */
    public $status;

    /**
     * @var SavedSearch
     */
    private $savedSearch;

    /**
     * @var int
     */
    private $timeRange;

    /**
     * @param StatusInterface $status
     */
    public function __construct(StatusInterface $status, SavedSearch $savedSearch)
    {
        $this->status = $status;
        $this->savedSearch = $savedSearch;
        $this->publicationDateTime = $status->getCreatedAt();
        $this->memberName  = $status->getScreenName();

        $this->updateTimeRange();
    }
}
