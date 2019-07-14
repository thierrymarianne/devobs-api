<?php

namespace WeavingTheWeb\Bundle\ApiBundle\Entity;

use App\Status\Entity\StatusIdentity;
use App\Status\Entity\StatusTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="WeavingTheWeb\Bundle\ApiBundle\Repository\ArchivedStatusRepository")
 * @ORM\Table(
 *      name="weaving_archived_status",
 *      uniqueConstraints={
 *          @ORM\UniqueConstraint(
 *              name="unique_hash", columns={"ust_hash", "ust_access_token", "ust_full_name"}),
 *      },
 *      indexes={
 *          @ORM\Index(name="hash", columns={"ust_hash"}),
 *          @ORM\Index(name="screen_name", columns={"ust_full_name"}),
 *          @ORM\Index(name="status_id", columns={"ust_status_id"}),
 *          @ORM\Index(name="ust_created_at", columns={"ust_created_at"})
 *      }
 * )
 */
class ArchivedStatus implements StatusInterface
{
    use StatusTrait;

    /**
     * @var integer
     *
     * @ORM\Column(name="ust_id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="ust_hash", type="string", length=40, nullable=true)
     */
    protected $hash;

    /**
     * @ORM\Column(name="ust_full_name", type="string", length=32)
     */
    protected $screenName;

    /**
     * @ORM\Column(name="ust_name", type="text")
     */
    protected $name;

    /**
     * @ORM\Column(name="ust_text", type="text")
     */
    protected $text;

    /**
     * @ORM\Column(name="ust_avatar", type="string", length=255)
     */
    protected $userAvatar;

    /**
     * @ORM\Column(name="ust_access_token", type="string", length=255)
     */
    protected $identifier;

    /**
     * @ORM\Column(name="ust_status_id", type="string", length=255, nullable=true)
     */
    protected $statusId;

    /**
     * @ORM\Column(name="ust_api_document", type="text", nullable=true)
     */
    protected $apiDocument;

    /**
     * @ORM\Column(name="ust_starred", type="boolean", options={"default": false})
     */
    protected $starred = false;

    /**
     * @param $starred
     * @return $this
     */
    public function setStarred($starred)
    {
        $this->starred = $starred;

        return $this;
    }

    /**
     * @return mixed
     */
    public function isStarred()
    {
        return $this->starred;
    }

    /**
     * @ORM\Column(name="ust_indexed", type="boolean", options={"default": false})
     */
    protected $indexed;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="ust_created_at", type="datetime")
     */
    protected $createdAt;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="ust_updated_at", type="datetime", nullable=true)
     */
    protected $updatedAt;

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $hash
     */
    public function setHash($hash)
    {
        $this->hash = $hash;
    }

    /**
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * @param $screenName
     * @return $this
     */
    public function setScreenName($screenName)
    {
        $this->screenName = $screenName;

        return $this;
    }

    /**
     * Get screeName
     *
     * @return string
     */
    public function getScreenName()
    {
        return $this->screenName;
    }

    /**
     * @param $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param $text
     * @return $this
     */
    public function setText($text)
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Get text
     *
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @param $userAvatar
     * @return $this
     */
    public function setUserAvatar($userAvatar)
    {
        $this->userAvatar = $userAvatar;

        return $this;
    }

    /**
     * Get userAvatar
     *
     * @return string
     */
    public function getUserAvatar()
    {
        return $this->userAvatar;
    }

    /**
     * @param $identifier
     * @return $this
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * Get identifier
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    public function setStatusId($statusId)
    {
        $this->statusId = $statusId;
    }

    public function getStatusId()
    {
        return $this->statusId;
    }

    public function setApiDocument($apiDocument)
    {
        $this->apiDocument = $apiDocument;
    }

    public function getApiDocument()
    {
        return $this->apiDocument;
    }

    /**
     * @param $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param $updatedAt
     * @return $this
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get updatedAt
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param $indexed
     * @return $this
     */
    public function setIndexed($indexed)
    {
        $this->indexed = $indexed;
    
        return $this;
    }

    /**
     * Get indexed
     *
     * @return boolean 
     */
    public function getIndexed()
    {
        return $this->indexed;
    }

    /**
     * @ORM\ManyToMany(targetEntity="Aggregate", inversedBy="userStreams", cascade={"persist"})
     * @ORM\JoinTable(name="weaving_archived_status_aggregate",
     *      joinColumns={@ORM\JoinColumn(name="status_id", referencedColumnName="ust_id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="aggregate_id", referencedColumnName="id")}
     * )
     */
    protected $aggregates;

    public function __construct()
    {
        $this->aggregates = new ArrayCollection();
    }

    /**
     * @return ArrayCollection
     */
    public function getAggregates()
    {
        return $this->aggregates;
    }

    /**
     * @param Aggregate $aggregate
     * @return StatusInterface
     */
    public function removeFrom(Aggregate $aggregate): StatusInterface
    {
        $this->aggregates->remove($aggregate);

        return $this;
    }

    /**
     * @var StatusIdentity
     */
    private $statusIdentity;
}
