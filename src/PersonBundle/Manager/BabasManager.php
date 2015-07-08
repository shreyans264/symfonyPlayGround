<?php
namespace PersonBundle\Manager;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Babas Manager
 */
class BabasManager
{
    protected $doctrine;
    /**
     * Constructor
     *
     * @param SecurityContextInterface $securityContext    - Security Context
     * @param Doctrine                 $doctrine           - Doctrine
     */
    public function __construct(
         SecurityContextInterface $securityContext,
         Doctrine $doctrine)
    {
        $this->doctrine           = $doctrine;
    }
     /**
     * Load  baba By Id
     *
     * @param integer $babaId - Baba ID
     *
     * @return offersId
     */
    public function load($babaId)
    {
        $er = $this->doctrine->getManager()->getRepository('PersonBundle:Offer');
        $baba = $er->retrieve($babaId);
        if (is_null($baba)) {
            return null;
        } else if ($this->securityContext->isGranted('READ', $baba)) {
            return $baba;
        } else {
            throw new AccessDeniedException("Baba ko Moksh mil gaya,Try another baba.");
        }
    }
    /**
     * Load All offers
     *
     * @param boolean $withVisible - is offer is visible
     * @param limit   $limit       - limit
     * @param offset  $offset      - offset
     *
     * @return array
     */
    public function loadAll()
    {
        $dummyOffer= new Offer();
        if (!$this->securityContext->isGranted('READ', $dummyOffer)) {
            throw new AccessDeniedException("aap kisi bhi baba se mil nahi sakte.");
        }
        $er = $this->doctrine->getManager()->getRepository('PractoApiBundle:Offer');
        $offers = $er->retrieveAll($withVisible, $practiceProfileId, $limit, $offset);
        return $offers;
    }
    /**
     * Get Count
     *
     * @param boolean $withVisible - With visible
     * @param boolean $withDeleted - With Deleted
     *
     * @return array
     */
    public function getCount( $withVisible = true, $withDeleted=false)
    {
        $practiceProfileId = $this->securityContext
                                  ->getToken()
                                  ->getSession()
                                  ->getPracticeProfileId();
        if (null === $practiceProfileId) {
            return null;
        }
        $dummyOffer= new Offer();
        $dummyOffer->setPracticeProfileId($practiceProfileId);
        if (!$this->securityContext->isGranted('READ', $dummyOffer)) {
            throw new AccessDeniedException("This user or role is not allowed to retrieve these offers.");
        }
        $er = $this->doctrine->getManager()->getRepository('PractoApiBundle:Offer');
        return $er->findCount($practiceProfileId, $withVisible, $withDeleted);
    }
    /**
     * Add New offer
     *
     * @param array $requestParams - Request parameters
     *
     * @return $array
     */
    public function add($requestParams)
    {
        $user = $this->securityContext->getToken()->getUser();
        $practiceProfileId = $this->securityContext
                                ->getToken()
                                ->getSession()
                                ->getPracticeProfileId();
        if (null === $practiceProfileId) {
            return null;
        }
        $dummyOffer= new Offer();
        $dummyOffer->setPracticeProfileId($practiceProfileId);
        $er = $this->doctrine->getManager()->getRepository('PractoApiBundle:Offer');
        if (!$this->securityContext->isGranted('READ', $dummyOffer)) {
            throw new AccessDeniedException("This user or role is not allowed to retrieve these offers.");
        }
        $errors = array();
        if (array_key_exists('offer_code', $requestParams) && strlen("".$requestParams["offer_code"])>0) {
           if (strlen($requestParams["offer_code"]) >15) {
                @$errors['offer_code'][] = "Offer code length can't be more than 15 characters.";
           } else {
             $requestOfferCode =  $requestParams["offer_code"];
             if ($er->checkDuplicateOffer($practiceProfileId, $requestOfferCode)) {
                @$errors['offer_code'][]= "This offer code already exists.";
             }
           }
        } else {
                @$errors['offer_code'][] = "Offer code can't be empty.";
        }
        if (array_key_exists('discount', $requestParams) && strlen("".$requestParams["discount"])>0) {
            if (!is_numeric($requestParams["discount"])) {
                @$errors['discount'][] = "Discount type must be a numeric value.";
            } else {
                if (round($requestParams["discount"], 2) <=0) {
                    @$errors['discount'][]=  "This value should be greater than 0.01";
                }
            }
        } else {
                @$errors['discount'][] = "Discount can't be empty.";
        }
        if (array_key_exists('discount_units', $requestParams) && !empty($requestParams["discount_units"])) {
        if (array_key_exists('discount', $requestParams) && strlen("".$requestParams["discount"])>0) {
            if (strcmp($requestParams["discount_units"], "PERCENT")== 0 && ($requestParams["discount"]) > 100) {
                @$errors['discount'][] = "Discount percent can't be more than 100.";
            }
        }
            if (strcmp($requestParams["discount_units"], "PERCENT")!=0 && strcmp($requestParams["discount_units"], "NUMBER")!=0) {
                @$errors['discount_units'][] = "The value you selected is not a valid choice.";
            }
        } else {
                @$errors['discount_units'][] = "Discount unit can't be empty.";
        }
        if (array_key_exists('offer_description', $requestParams) && !empty($requestParams["offer_description"] ) && strlen($requestParams["offer_description"]) >30) {
               @$errors['offer_description'][] = "Offer description length can't be more than 30 characters.";
        }
        if (0 < count($errors)) {
            throw new ValidationError($errors);
        }
        $offer = new Offer();
        $offer->setPracticeProfileId($practiceProfileId);
        $offer->setCreatedAt(new \DateTime('now'));
        $offer->setCreatedByUserId($user);
        if ($this->securityContext->isGranted('CREATE', $offer)) {
            $offer->setSoftDeleted(false);
            $offer->setVisible(true);
            $this->updateFields($offer, $requestParams, $user, $practiceProfileId);
            $em = $this->doctrine->getManager();
            $em->persist($offer);
            $em->flush();
        } else {
            throw new AccessDeniedException("This user or role is not allowed to create offer.");
        }
        return $offer;
    }
 /**
     * Update Fields
     *
     * @param offer   $offer             - offer
     * @param array   $requestParams     - Request parameters
     * @param User    $user              - User
     * @param integer $practiceProfileId - Practice Profile Id
     *
     * @return null
     */
    public function updateFields($offer, $requestParams, $user, $practiceProfileId)
    {
        $errors = array();
        $offer->setAttributes($requestParams);
        $offer->setModifiedAt(new \DateTime('now'));
        $offer->setModifiedByUserId($user);
        $validationErrors = $this->validator->validate($offer);
        if (0 < count($validationErrors)) {
            foreach ($validationErrors as $validationError) {
              $pattern = '/([a-z])([A-Z])/';
              $replace = function ($m) {
                  return $m[1] . '_' . strtolower($m[2]);
              };
              $attribute = preg_replace_callback($pattern, $replace, $validationError->getPropertyPath());
              @$errors[$attribute][] = $validationError->getMessage();
            }
        }
        if (0 < count($errors)) {
            throw new ValidationError($errors);
        }
        return;
    }
    /**
     * update offer visibilty patch
     *
     * @param offer $offer         - offer
     * @param array $requestParams - Request parameters
     */
    public function update($offer, $requestParams)
    {
       if (count($requestParams)>1) {
        $message['visible'] = "Too many params to patch.";
        throw new ValidationError($message);
       }
       if (array_key_exists('visible', $requestParams) && !empty($requestParams["visible"])) {
            $session = $this->securityContext->getToken()->getSession();
            $user = $session->getUser();
            $practiceId = $session->getPracticeProfileId();
            if ($this->securityContext->isGranted('UPDATE', $offer)) {
                $this->updateFields($offer, $requestParams, $user, $practiceId);
                $em = $this->doctrine->getManager();
                $em->persist($offer);
                $em->flush();
            } else {
                throw new AccessDeniedException("This user or role is not allowed to delete or update this offer.");
            }
       } else {
            $message['visible'] = "Invalid patch request.";
            throw new ValidationError($message);
       }
    }
     /**
     * Delete a offer (softDeleted true false)
     *
     * @param offer $offer - offer
     *
     * @return null
     */
    public function delete($offer)
    {
        if ($this->securityContext->isGranted('DELETE', $offer)) {
            $offer->setModifiedAt(new \DateTime('now'));
            $user = $this->securityContext
                         ->getToken()
                         ->getSession()
                         ->getUser();
            $offer->setModifiedByUserId($user);
            $offer->setSoftDeleted(true);
            $em = $this->doctrine->getManager();
            $em->persist($offer);
            $em->flush();
        } else {
            throw new AccessDeniedException("This user or role is not allowed to delete this offer.");
        }
    }
}