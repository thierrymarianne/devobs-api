<?php


namespace App\Status\Repository;

/**
 * @package App\Status\Repository
 */
interface ExtremumAwareInterface
{
    public const FINDING_IN_ASCENDING_ORDER = 'asc';
    public const FINDING_IN_DESCENDING_ORDER = 'desc';

    /**
     * @param string      $memberName
     * @param string|null $before
     * @return array
     */
    public function findLocalMaximum(
        string $memberName,
        ?string $before = null
    ): array;

    /**
     * @param string         $screenName
     * @param string         $direction
     * @param string|null $before
     * @return mixed
     */
    public function findNextExtremum(
        string $screenName,
        string $direction = self::FINDING_IN_ASCENDING_ORDER,
        ?string $before = null
    ): array;

    /**
     * @param string $memberName
     * @return array
     */
    public function getIdsOfExtremeStatusesSavedForMemberHavingScreenName(string $memberName): array;
}
