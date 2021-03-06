<?php

namespace App\Membership\Infrastructure\Entity;

use App\Membership\Domain\Model\MemberInterface;
use App\Twitter\Domain\Api\Model\TokenInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Selectable;

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
    public function getUsername(): ?string
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
     * @param $twitterUsername
     * @return $this
     */
    public function setTwitterScreenName(string $twitterUsername): MemberInterface
    {
        return $this;
    }

    /**
     * @return string
     */
    public function twitterScreenName(): string
    {
        return '';
    }

    /**
     * @return string
     */
    public function twitterId(): ?string
    {
        return '';
    }

    /**
     * @deprecated
     *
     * @param string $fullName
     * @return MemberInterface
     */
    public function setFullName(string $fullName): MemberInterface
    {
        return $this->setTwitterScreenName($fullName);
    }

    /**
     * @return string
     */
    public function getFullName(): string
    {
        return '';
    }

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

    public function totalStatus(): int
    {
        return 0;
    }

    public function setTotalStatus($totalStatus): MemberInterface
    {
        return $this;
    }

    public function getMinStatusId(): int
    {
        return 0;
    }

    public function setTwitterID(string $twitterId): MemberInterface
    {
        $this->twitterID = $twitterId;

        return $this;
    }

    public function hasTwitterId(): bool
    {
        return $this->twitterID === null;
    }


    public function addToken(TokenInterface $token): MemberInterface
    {
        return $this;
    }

    public function getTokens(): Selectable
    {
        return new ArrayCollection([]);
    }
}
