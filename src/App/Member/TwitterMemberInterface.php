<?php

namespace App\Member;

interface TwitterMemberInterface extends MemberInterface
{
    /**
     * @return string
     */
    public function getTwitterID(): ?string;

    /**
     * @param string $twitterId
     * @return TwitterMemberInterface
     */
    public function setTwitterID(string $twitterId): self;

    /**
     * @return bool
     */
    public function hasTwitterId(): bool;

    /**
     * @param $twitterUsername
     * @return TwitterMemberInterface
     */
    public function setTwitterUsername(string $twitterUsername): self;

    /**
     * @return string
     */
    public function getTwitterUsername(): string;
}