<?php

namespace Intracto\SecretSantaBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Intracto\SecretSantaBundle\Validator\PoolHasValidExcludes;

/**
 * Intracto\SecretSantaBundle\Entity\Pool
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="Intracto\SecretSantaBundle\Entity\PoolRepository")
 * @ORM\HasLifecycleCallbacks
 *
 * @PoolHasValidExcludes(groups={"exclude_entries"})
 */
class Pool
{
    /**
     * @var int $id
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string $listurl
     *
     * @ORM\Column(name="listurl", type="string", length=255)
     */
    private $listurl;

    /**
     * @var string $message
     *
     * @ORM\Column(name="message", type="text")
     */
    private $message;

    /**
     * @var \DateTime $sentdate
     *
     * @ORM\Column(name="sentdate", type="datetime", length=255, nullable=true)
     */
    private $sentdate;

    /**
     * @var \DateTime $date
     *
     * @Assert\NotBlank()
     * @ORM\Column(name="eventdate", type="datetime", length=255, nullable=true)
     */
    private $date;

    /**
     * @var string $amount
     *
     * @Assert\NotBlank()
     * @ORM\Column(name="amount", type="string", length=255, nullable=true)
     */
    private $amount;

    /**
     * @var ArrayCollection $entries
     *
     * @ORM\OneToMany(targetEntity="Entry", mappedBy="pool", cascade={"persist", "remove"})
     *
     * @Assert\Valid()
     */
    private $entries;

    /**
     * @var bool $created
     *
     * @ORM\Column(name="created", type="boolean")
     */
    private $created = false;

    /**
     * @var string $locale
     *
     * @ORM\Column(name="locale", type="string", length=7)
     */
    private $locale = 'en';

    /**
     * @var bool $exposed
     *
     * @ORM\Column(name="exposed", type="boolean")
     */
    private $exposed = false;

    /**
     * Constructor
     */
    public function __construct($createDefaults = true)
    {
        $this->entries = new \Doctrine\Common\Collections\ArrayCollection();

        if ($createDefaults) {
            // Create default minimum entries
            for ($i = 0; $i < 3; $i++) {
                $entry = new Entry();
                $this->addEntry($entry);
            }
        }
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set listurl
     *
     * @param string $listurl
     *
     * @return Pool
     */
    public function setListurl($listurl)
    {
        $this->listurl = $listurl;

        return $this;
    }

    /**
     * Get listurl
     *
     * @return string
     */
    public function getListurl()
    {
        return $this->listurl;
    }

    /**
     * Set message
     *
     * @param string $message
     *
     * @return Pool
     */
    public function setMessage($message)
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Get message
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Get owner_name
     *
     * @return string
     */
    public function getOwnerName()
    {
        return $this->entries->first()->getName();
    }

    /**
     * Get owner_email
     *
     * @return string
     */
    public function getOwnerEmail()
    {
        return $this->entries->first()->getEmail();
    }

    /**
     * Set sentdate
     *
     * @param \DateTime $sentdate
     *
     * @return Pool
     */
    public function setSentdate($sentdate)
    {
        $this->sentdate = $sentdate;

        return $this;
    }

    /**
     * Get sentdate
     *
     * @return \DateTime
     */
    public function getSentdate()
    {
        return $this->sentdate;
    }

    /**
     * Add entry
     *
     * @param Entry $entry
     *
     * @return Pool
     */
    public function addEntry(Entry $entry)
    {
        $this->entries[] = $entry;

        return $this;
    }

    /**
     * Remove entry
     *
     * @param Entry $entry
     */
    public function removeEntry(Entry $entry)
    {
        $this->entries->removeElement($entry);
    }

    /**
     * Get entries
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getEntries()
    {
        return $this->entries;
    }

    public function __toString()
    {
        return 'Id: ' . $this->id . ' - Entries: ' . $this->entries->count() . ' - Owner: ' . $this->getOwnerName();
    }

    /**
     * @ORM\PrePersist
     */
    public function generateListurl()
    {
        $this->listurl = base_convert(sha1(uniqid(mt_rand(), true)), 16, 36);
    }

    /**
     * Add entries
     *
     * @param \Intracto\SecretSantaBundle\Entity\Entry $entries
     *
     * @return Pool
     */
    public function addEntrie(\Intracto\SecretSantaBundle\Entity\Entry $entries)
    {
        $this->entries[] = $entries;

        return $this;
    }

    /**
     * Remove entries
     *
     * @param \Intracto\SecretSantaBundle\Entity\Entry $entries
     */
    public function removeEntrie(\Intracto\SecretSantaBundle\Entity\Entry $entries)
    {
        $this->entries->removeElement($entries);
    }

    /**
     * Set date
     *
     * @param \DateTime $date
     *
     * @return Pool
     */
    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    /**
     * Get date
     *
     * @return \DateTime
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * Set amount
     *
     * @param string $amount
     *
     * @return Pool
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * Get amount
     *
     * @return string
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Set created
     *
     * @param bool $created
     *
     * @return Pool
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Get created
     *
     * @return bool
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set locale
     *
     * @param string $locale
     *
     * @return Pool
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Get locale
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @return Pool
     */
    public function expose()
    {
        $this->exposed = true;

        return $this;
    }

    /**
     * @return bool
     */
    public function getExposed()
    {
        return $this->exposed;
    }

    public function createNewForReuse()
    {
        $oldPool = $this;

        $pool = new Pool(false);
        $pool->setAmount($oldPool->getAmount());

        $oldEntries = $oldPool->getEntries();

        /** @var Entry $oldEntry */
        foreach ($oldEntries as $oldEntry) {
            $entry = new Entry();
            $entry->setEmail($oldEntry->getEmail());
            $entry->setName($oldEntry->getName());
            $pool->addEntry($entry);
        }

        return $pool;
    }
}
