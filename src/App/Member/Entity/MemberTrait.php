<?php

namespace App\Member\Entity;

use App\Member\MemberInterface;

trait MemberTrait
{
    public function getApiKey(): string
    {
        return '';
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return 0;
    }

    /**
     * @param int $groupId
     * @return MemberInterface
     */
    public function setGroupId(int $groupId): MemberInterface
    {
        return $this;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
    }

    /**
     * @param string $firstName
     * @return MemberInterface
     */
    public function setFirstName(string $firstName): MemberInterface
    {
        return $this;
    }

    /**
     * @return string
     */
    public function getFirstName(): string
    {
        return '';
    }

    /**
     * @param string $lastName
     * @return MemberInterface
     */
    public function setLastName(string $lastName): MemberInterface
    {
        return $this;
    }

    /**
     * @return string
     */
    public function getLastName(): string
    {
        return '';
    }

    /**
     * @param string $twitterId
     * @return MemberInterface
     */
    public function setTwitterID(string $twitterId): MemberInterface
    {
        return $this;
    }

    /**
     * @param $twitterUsername
     * @return $this
     */
    public function setTwitterUsername(string $twitterUsername): MemberInterface
    {
        return $this;
    }

    /**
     * @return string
     */
    public function getTwitterUsername(): string
    {
        return '';
    }

    /**
     * @return string
     */
    public function getTwitterID(): ?string
    {
        return '';
    }

    /**
     * @param string $fullName
     * @return MemberInterface
     */
    public function setFullName(string $fullName): MemberInterface
    {
        return $this;
    }

    /**
     * @return string
     */
    public function getFullName(): string
    {
        return '';
    }

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
        return false;
    }

    /**
     * @return boolean
     */
    public function isNotProtected(): bool
    {
        return true;
    }

    /**
     * @param bool $suspended
     * @return MemberInterface
     */
    public function setSuspended(bool $suspended): MemberInterface
    {
        return $this;
    }

    /**
     * @return bool
     */
    public function isSuspended(): bool
    {
        return false;
    }

    /**
     * @return boolean
     */
    public function isNotSuspended(): bool
    {
        return true;
    }

    /**
     * @param $notFound
     * @return MemberInterface
     */
    public function setNotFound(bool $notFound): MemberInterface
    {
        return $this;
    }

    /**
     * @return boolean
     */
    public function hasBeenDeclaredAsNotFound(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public function hasNotBeenDeclaredAsNotFound(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isAWhisperer(): bool
    {
        return false;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
}
