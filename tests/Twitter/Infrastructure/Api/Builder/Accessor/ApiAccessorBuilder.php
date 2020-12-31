<?php
declare(strict_types=1);

namespace App\Tests\Twitter\Infrastructure\Api\Builder\Accessor;

use App\Twitter\Domain\Api\Accessor\ApiAccessorInterface;
use App\Twitter\Domain\Api\Selector\ListSelectorInterface;
use App\Twitter\Domain\Resource\OwnershipCollection;
use App\Twitter\Domain\Resource\OwnershipCollectionInterface;
use App\Twitter\Infrastructure\Exception\UnavailableResourceException;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Prophecy\Prophet;
use stdClass;

class ApiAccessorBuilder
{
    public const LIST_ID   = 1;
    public const LIST_NAME = 'science';

    public const MEMBER_ID          = '1';
    public const MEMBER_NAME        = 'Marie Curie';
    public const MEMBER_SCREEN_NAME = 'mariec';

    public const SCREEN_NAME = 'BobEponge';

    public static function newApiAccessorBuilder()
    {
        return new self();
    }

    private ObjectProphecy $prophecy;

    public function __construct()
    {
        $prophet = new Prophet();

        $this->prophecy = $prophet->prophesize(ApiAccessorInterface::class);
    }

    public function build(): ApiAccessorInterface
    {
        return $this->prophecy->reveal();
    }

    /**
     * @return object
     */
    public function makeMemberList(): stdClass
    {
        return (object) [
            'users' => [
                (object) [
                    'name'        => self::MEMBER_NAME,
                    'id'          => self::MEMBER_ID,
                    'screen_name' => self::MEMBER_SCREEN_NAME
                ]
            ]
        ];
    }

    public function makeOwnershipCollection(): OwnershipCollectionInterface
    {
        return OwnershipCollection::fromArray(
            [
                self::LIST_NAME => (object) [
                    'name'   => self::LIST_NAME,
                    'id'     => self::LIST_ID,
                    'id_str' => (string) self::LIST_ID,
                ]
            ]
        );
    }

    public function willGetMembersInList(
        int $listId,
        \stdClass $members
    ): self {
        $this->prophecy
            ->getListMembers($listId)
            ->willReturn($members);

        return $this;
    }

    public function willGetOwnershipCollectionForMember(
        OwnershipCollectionInterface $ownershipCollection,
        string $screenName
    ): self {
        $this->prophecy
            ->getMemberOwnerships(Argument::type(ListSelectorInterface::class))
            ->willReturn($ownershipCollection);

        return $this;
    }

    public function willThrowWhenGettingOwnershipCollectionForMember(
        string $screenName
    ): self {
        $this->prophecy
            ->getMemberOwnerships(Argument::type(ListSelectorInterface::class))
            ->willThrow(new UnavailableResourceException());

        return $this;
    }

    public function willGetOwnershipCollectionAfterThrowingForMember(
        OwnershipCollectionInterface $ownershipCollection,
        string $screenName
    ): self {
        static $calls = 0;

        $this->prophecy
            ->getMemberOwnerships(Argument::type(ListSelectorInterface::class))
            ->will(function () use (&$calls, $ownershipCollection) {
                if ($calls === 0) {
                    $calls++;

                    throw new UnavailableResourceException();
                }

                return $ownershipCollection;
            });

        return $this;
    }

    public function willGetProfileForMemberHavingScreenName(
        stdClass $profile,
        string $screenName
    ): self {
        $this->prophecy
            ->getMemberProfile($screenName)
            ->willReturn($profile);

        return $this;
    }
}