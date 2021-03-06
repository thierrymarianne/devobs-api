<?php
declare(strict_types=1);

namespace App\Membership\Infrastructure\Entity;

use App\Membership\Domain\Model\MemberInterface;
use App\Membership\Domain\Model\TwitterMemberInterface;
use App\Membership\Infrastructure\Entity\Legacy\Member;

class ProtectedMember implements TwitterMemberInterface
{
    use MemberTrait;
    use ExceptionalUserInterfaceTrait;

    /**
     * @param bool $protected
     * @return MemberInterface
     */
    public function setProtected(bool $protected): MemberInterface
    {
        return $this;
    }

    /**
     * @return boolean
     */
    public function isProtected(): bool
    {
        return true;
    }

    /**
     * @return boolean
     */
    public function isNotProtected(): bool
    {
        return false;
    }

    public function make(string $screenName, int $id): MemberInterface
    {
        $member = new Member();
        $member->setTwitterScreenName($screenName);
        $member->setTwitterID((string) $id);
        $member->setEmail('@'.$screenName);
        $member->setProtected(true);

        return $member;
    }
}
