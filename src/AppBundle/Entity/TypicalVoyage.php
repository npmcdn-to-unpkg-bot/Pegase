<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Knp\DoctrineBehaviors\Model as ORMBehaviors;

/**
 * @ORM\Table(name="typical_voyage")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\TypicalVoyageRepository")
 */
class TypicalVoyage
{

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var Voyage
     * @ORM\OneToOne(targetEntity="Voyage")
     */
    private $voyage;

    /**
     * @var int
     * @ORM\Column(type="integer", nullable=true)
     */
    private $nbDays = 0;

    /**
     * @var int
     * @ORM\Column(type="integer", nullable=true)
     */
    private $price = 0;


    public function __construct()
    {
    }

    /**
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return Voyage
     */
    public function getVoyage()
    {
        return $this->voyage;
    }

    /**
     * @param Voyage $voyage
     * @return $this
     */
    public function setVoyage($voyage)
    {
        $this->voyage = $voyage;

        return$this;
    }

    /**
     * @return int
     */
    public function getNbDays()
    {
        return $this->nbDays;
    }

    /**
     * @param int $nbDays
     * @return $this
     */
    public function setNbDays($nbDays)
    {
        $this->nbDays = $nbDays;

        return $this;
    }

    /**
     * @return int
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @param int $price
     * @return $this
     */
    public function setPrice($price)
    {
        $this->price = $price;

        return $this;
    }

}
