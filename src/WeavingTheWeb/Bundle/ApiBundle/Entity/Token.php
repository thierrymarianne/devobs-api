<?php

namespace WeavingTheWeb\Bundle\ApiBundle\Entity;

use Doctrine\ORM\Mapping as ORM,
    Doctrine\Common\Collections\ArrayCollection;
use WTW\UserBundle\Entity\User;

/**
 * Token
 *
 * @ORM\Table(name="weaving_access_token")
 * @ORM\Entity
 */
class Token
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="oauth_token", type="string", length=255)
     */
    protected $oauthToken;

    /**
     * @var string
     *
     * @ORM\Column(name="oauth_token_secret", type="string", length=255)
     */
    protected $oauthTokenSecret;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    protected $createdAt;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updated_at", type="datetime")
     */
    protected $updatedAt;

    /**
     * @ORM\ManyToMany(targetEntity="WTW\UserBundle\Entity\User", mappedBy="tokens")
     */
    protected $users;


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
     * Set oauthToken
     *
     * @param string $oauthToken
     * @return Token
     */
    public function setOauthToken($oauthToken)
    {
        $this->oauthToken = $oauthToken;
    
        return $this;
    }

    /**
     * Get oauthToken
     *
     * @return string 
     */
    public function getOauthToken()
    {
        return $this->oauthToken;
    }

    /**
     * Set oauthTokenSecret
     *
     * @param string $oauthTokenSecret
     * @return Token
     */
    public function setOauthTokenSecret($oauthTokenSecret)
    {
        $this->oauthTokenSecret = $oauthTokenSecret;
    
        return $this;
    }

    /**
     * Get oauthTokenSecret
     *
     * @return string 
     */
    public function getOauthTokenSecret()
    {
        return $this->oauthTokenSecret;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     * @return Token
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
     * Set updatedAt
     *
     * @param \DateTime $updatedAt
     * @return Token
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
     * Constructor
     */
    public function __construct()
    {
        $this->users = new ArrayCollection();
    }
    
    /**
     * Add users
     *
     * @param \WTW\UserBundle\Entity\User $users
     * @return Token
     */
    public function addUser(User $users)
    {
        $this->users[] = $users;
    
        return $this;
    }

    /**
     * Remove users
     *
     * @param \WTW\UserBundle\Entity\User $users
     */
    public function removeUser(User $users)
    {
        $this->users->removeElement($users);
    }

    /**
     * Get users
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getUsers()
    {
        return $this->users;
    }
}