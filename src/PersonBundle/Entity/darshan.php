<?php

namespace PersonBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * darshan
 *
 * @ORM\Table()
 * @ORM\Entity
 */
class darshan
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
     * @var integer
     *
     * @ORM\Column(name="bhakth_id", type="integer")
     */
    private $bhakthId;

    /**
     * @var integer
     *
     * @ORM\Column(name="baba_id", type="integer")
     */
    private $babaId;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="shuru_kab_hua", type="datetime")
     */
    private $shuruKabHua;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="khatam_kab_hoga", type="datetime")
     */
    private $khatamKabHoga;


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
     * Set bhakthId
     *
     * @param integer $bhakthId
     * @return darshan
     */
    public function setBhakthId($bhakthId)
    {
        $this->bhakthId = $bhakthId;

        return $this;
    }

    /**
     * Get bhakthId
     *
     * @return integer 
     */
    public function getBhakthId()
    {
        return $this->bhakthId;
    }

    /**
     * Set babaId
     *
     * @param integer $babaId
     * @return darshan
     */
    public function setBabaId($babaId)
    {
        $this->babaId = $babaId;

        return $this;
    }

    /**
     * Get babaId
     *
     * @return integer 
     */
    public function getBabaId()
    {
        return $this->babaId;
    }

    /**
     * Set shuruKabHua
     *
     * @param \DateTime $shuruKabHua
     * @return darshan
     */
    public function setShuruKabHua($shuruKabHua)
    {
        $this->shuruKabHua = $shuruKabHua;

        return $this;
    }

    /**
     * Get shuruKabHua
     *
     * @return \DateTime 
     */
    public function getShuruKabHua()
    {
        return $this->shuruKabHua;
    }

    /**
     * Set khatamKabHoga
     *
     * @param \DateTime $khatamKabHoga
     * @return darshan
     */
    public function setKhatamKabHoga($khatamKabHoga)
    {
        $this->khatamKabHoga = $khatamKabHoga;

        return $this;
    }

    /**
     * Get khatamKabHoga
     *
     * @return \DateTime 
     */
    public function getKhatamKabHoga()
    {
        return $this->khatamKabHoga;
    }
}
