<?php

namespace App\Member;

use Symfony\Component\Security\Core\User\UserInterface;

interface MemberInterface extends UserInterface
{
    /**
     * @return string
     */
    public function getApiKey(): string;

    /**
     * @return int
     */
    public function getId(): int;

    /**
     * @return string
     */
    public function getUsername();

    /**
     * @param string $fullName
     * @return MemberInterface
     */
    public function setFullName(string $fullName): MemberInterface;

    /**
     * @return string
     */
    public function getFullName(): string;

    /**
     * @param bool $protected
     * @return MemberInterface
     */
    public function setProtected(bool $protected): MemberInterface;

    /**
     * @return boolean
     */
    public function isProtected(): bool;

    /**
     * @return boolean
     */
    public function isNotProtected(): bool;

    /**
     * @param bool $suspended
     * @return MemberInterface
     */
    public function setSuspended(bool $suspended): MemberInterface;

    /**
     * @return bool
     */
    public function isSuspended(): bool;

    /**
     * @return boolean
     */
    public function isNotSuspended(): bool;

    /**
     * @param $notFound
     * @return MemberInterface
     */
    public function setNotFound(bool $notFound): MemberInterface;

    /**
     * @return boolean
     */
    public function hasBeenDeclaredAsNotFound(): bool;

    /**
     * @return bool
     */
    public function hasNotBeenDeclaredAsNotFound(): bool;

    /**
     * @return bool
     */
    public function isAWhisperer(): bool;

    public function getDescription(): ?string;

    public function getUrl(): ?string;
}
