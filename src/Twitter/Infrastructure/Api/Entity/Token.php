<?php
declare(strict_types=1);

namespace App\Twitter\Infrastructure\Api\Entity;

use App\Twitter\Domain\Api\Model\TokenInterface;
use App\Twitter\Infrastructure\Api\Exception\InvalidSerializedTokenException;
use App\Membership\Infrastructure\Entity\Legacy\Member;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use function array_key_exists;

/**
 * @ORM\Table(name="weaving_access_token")
 * @ORM\Entity
 */
class Token implements TokenInterface
{
    use TokenTrait;

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\ManyToOne(targetEntity="TokenType", inversedBy="tokens", cascade={"all"})
     * @ORM\JoinColumn(name="type", referencedColumnName="id")
     */
    protected $type;

    /**
     * @ORM\Column(name="token", type="string", length=255)
     */
    protected string $oauthToken;

    public function setAccessToken(string $accessToken): TokenInterface
    {
        $this->oauthToken = $accessToken;

        return $this;
    }

    public function getAccessToken(): string
    {
        return $this->oauthToken;
    }

    /**
     * @ORM\Column(name="secret", type="string", length=255, nullable=true)
     */
    protected ?string $oauthTokenSecret;

    public function setAccessTokenSecret(string $accessTokenSecret): TokenInterface
    {
        $this->oauthTokenSecret = $accessTokenSecret;

        return $this;
    }

    public function getAccessTokenSecret(): string
    {
        return $this->oauthTokenSecret;
    }

    /**
     * @ORM\Column(name="consumer_key", type="string", length=255, nullable=true)
     */
    public ?string $consumerKey = null;

    public function getConsumerKey(): string
    {
        return $this->consumerKey;
    }

    public function setConsumerKey(?string $consumerKey): self
    {
        $this->consumerKey = $consumerKey;

        return $this;
    }

    public function hasConsumerKey(): bool
    {
        return $this->consumerKey !== null;
    }

    /**
     * @var string
     *
     * @ORM\Column(name="consumer_secret", type="string", length=255, nullable=true)
     */
    public ?string $consumerSecret = null;

    public function getConsumerSecret(): string
    {
        return $this->consumerSecret;
    }

    public function setConsumerSecret(?string $consumerSecret): self
    {
        $this->consumerSecret = $consumerSecret;

        return $this;
    }

    /**
     * @ORM\Column(name="frozen_until", type="datetime", nullable=true)
     */
    protected ?DateTimeInterface $frozenUntil;

    /**
     * @ORM\Column(name="created_at", type="datetime")
     */
    protected ?DateTimeInterface $createdAt;

    /**
     * @ORM\Column(name="updated_at", type="datetime", nullable=true)
     */
    protected ?DateTimeInterface $updatedAt;

    /**
     * @ORM\ManyToMany(targetEntity="App\Membership\Infrastructure\Entity\Legacy\Member", mappedBy="tokens")
     */
    protected Collection $users;

    /**
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set createdAt
     *
     * @param DateTime $createdAt
     *
     * @return Token
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function setUpdatedAt(DateTimeInterface $updatedAt): TokenInterface
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function updatedAt(): DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    public function __construct()
    {
        $this->users = new ArrayCollection();
    }

    /**
     * @param Member $users
     *
     * @return Token
     */
    public function addUser(Member $users)
    {
        $this->users[] = $users;

        return $this;
    }

    /**
     * @param Member $users
     */
    public function removeUser(Member $users)
    {
        $this->users->removeElement($users);
    }

    /**
     * @return Collection
     */
    public function getUsers()
    {
        return $this->users;
    }

    /**
     * @param DateTimeInterface $frozenUntil
     *
     * @return $this
     */
    protected function setFrozenUntil(DateTimeInterface $frozenUntil): self
    {
        $this->frozenUntil = $frozenUntil;

        return $this;
    }

    public function getFrozenUntil(): \DateTimeInterface
    {
        return $this->frozenUntil;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getAccessToken();
    }

    public function setType(TokenType $type = null)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isFrozen(): bool
    {
        return $this->getFrozenUntil() !== null &&
            $this->getFrozenUntil()->getTimestamp() >
                (new DateTime('now', new DateTimeZone('UTC')))
                    ->getTimestamp();
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isNotFrozen(): bool
    {
        return !$this->isFrozen();
    }

    /**
     * @param array $properties
     *
     * @return static
     * @throws InvalidSerializedTokenException
     */
    public static function fromArray(array $properties): self
    {
        $token = new self();

        if (!array_key_exists('token', $properties)) {
            InvalidSerializedTokenException::throws('A token is required');
        }

        if (!array_key_exists('secret', $properties)) {
            InvalidSerializedTokenException::throws('A secret is required');
        }

        $consumerKey = null;
        if (array_key_exists('consumer_key', $properties)) {
            $consumerKey = $properties['consumer_key'];
        }

        $consumerSecret = null;
        if (array_key_exists('consumer_secret', $properties)) {
            $consumerSecret = $properties['consumer_secret'];
        }

        $token->setAccessToken($properties['token']);
        $token->setAccessTokenSecret($properties['secret']);
        $token->setConsumerKey($consumerKey);
        $token->setConsumerSecret($consumerSecret);

        return $token;
    }

    public function isValid(): bool
    {
        return $this->getAccessToken() !== null
            && $this->getAccessTokenSecret() !== null;
    }

    public function firstIdentifierCharacters(): string
    {
        return substr($this->getAccessToken(), 0, 8);
    }

    /** The token is frozen when the "frozen until" date is in the future */
    public function freeze(): TokenInterface
    {
        return $this->setFrozenUntil($this->nextFreezeEndsAt());
    }

    public function unfreeze(): TokenInterface
    {
        return $this->setFrozenUntil(
            new DateTimeImmutable(
                'now - 15min',
                new DateTimeZone('UTC')
            )
        );
    }

    public function nextFreezeEndsAt(): DateTimeInterface
    {
        return new DateTimeImmutable(
            'now + 15min',
            new DateTimeZone('UTC')
        );
    }
}
