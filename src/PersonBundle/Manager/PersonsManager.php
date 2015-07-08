<?php

namespace Practo\ApiBundle\Manager;

use PersonBundle\Entity\Person;
use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;

/**
 * Vital Sign Manager
 */
class VitalSignManager
{

    protected $doctrine;
    /**
     * Constructor
     *
     * @param Doctrine                 $doctrine           - Doctrine
     *
     */
    public function __construct(Doctrine $doctrine)
    {
        $this->doctrine           = $doctrine;
    }

     /**
     * Load  Vital Sign By Id
     *
     * @param integer $vitalSignId - Vital Sign Id
     *
     * @return vitalSign
     */
    public function load($vitalSignId)
    {
        $er = $this->doctrine->getManager()->getRepository('PractoApiBundle:VitalSign');
        $vitalSign = $er->retrieve($vitalSignId);

        if (is_null($vitalSign)) {
            return null;
        } else if ($this->securityContext->isGranted('READ', $vitalSign)) {
            $pppMapper = $this->legacyMapperLoader->load('PracticePatientProfile');
            $ppp = $pppMapper->retrieve($vitalSign->getPatientId());
            $vitalSign->setPatient($ppp);

            $pdpMapper = $this->legacyMapperLoader->load('PracticeDoctorProfile');
            $pdp = $pdpMapper->retrieve($vitalSign->getDoctorId(), true);
            $vitalSign->setDoctor($pdp);

            return $vitalSign;
        } else {
            throw new AccessDeniedException("This user or role is not allowed to retrieve this vital sign");
        }
    }

    /**
     * Load All
     *
     * @param integer $patientId      - Patient Id
     * @param string  $observedBefore - Observed Before
     * @param string  $observedAfter  - Observed After
     * @param string  $sortOn         - Sort Parameter
     * @param string  $reverse        - Reverse
     * @param string  $limit          - Limit
     * @param string  $offset         - Offset
     * @param boolean $withPatients   - With Patients
     * @param boolean $withDoctors    - With Doctors
     *
     * @return array
     */
    public function loadAll($patientId = null, $observedBefore=null, $observedAfter=null,
            $sortOn = null, $reverse = null ,$limit = null ,$offset=0,
            $withPatients=false, $withDoctors=false)
    {
        $practiceProfileId = $this->securityContext
                                  ->getToken()
                                  ->getSession()
                                  ->getPracticeProfileId();
        if (null === $practiceProfileId) {
            return null;
        }

        $dummyVitalSign = new VitalSign();
        $dummyVitalSign->setPracticeProfileId($practiceProfileId);
        if (!$this->securityContext->isGranted('READ', $dummyVitalSign)) {
            throw new AccessDeniedException("This user or role is not allowed to retrieve these patient vital sign");
        }

        if (!is_null($observedBefore)) {
            $ob = \DateTime::createFromFormat('Y-m-d H:i:s', $observedBefore);
            if (!($ob instanceof \DateTime)) {
                throw new ValidationError('Not valid observed before date time');
            }
        }
        if (!is_null($observedAfter)) {
            $of = \DateTime::createFromFormat('Y-m-d H:i:s', $observedAfter);
            if (!($of instanceof \DateTime)) {
                throw new ValidationError('Not valid observed after date time');
            }
        }
        if (!empty($observedBefore) && !empty($observedAfter)) {
            if ($ob < $of) {
                throw new ValidationError("observed after date time cannot be greater than observed before date time");
            }
        }

        $acceptedSortKeys = array(
            'observed_at'
        );
        if (!empty($sortOn) && !in_array($sortOn, $acceptedSortKeys)) {
            throw new ValidationError('Key '.$sortOn.' is not a valid sort key. Valid Keys are ['.implode(', ', $acceptedSortKeys) . ']');
        }

        $er = $this->doctrine->getManager()->getRepository('PractoApiBundle:VitalSign');

        $vitalSigns = $er->retrieveAll(
            $practiceProfileId,
            $patientId,
            $observedBefore,
            $observedAfter,
            $sortOn,
            $reverse,
            $limit,
            $offset
        );

        if ($withPatients || $withDoctors) {
            $pppMapper = $this->legacyMapperLoader->load('PracticePatientProfile');
            $pdpMapper = $this->legacyMapperLoader->load('PracticeDoctorProfile');
            foreach ($vitalSigns as $vitalSign) {
                if ($withPatients) {
                    $ppp = $pppMapper->retrieve($vitalSign->getPatientId());
                    $vitalSign->setPatient($ppp);
                }
                if ($withDoctors) {
                    $pdp = $pdpMapper->retrieve($vitalSign->getDoctorId());
                    $vitalSign->setDoctor($pdp);
                }
            }
        }

        return $vitalSigns;
    }

    /**
     * Get Count
     *
     * @param integer $patientId   - Patient Id
     * @param boolean $withDeleted - With Deleted
     *
     * @return array
     */
    public function getCount($patientId=null, $withDeleted=false)
    {
        $practiceProfileId = $this->securityContext
                                  ->getToken()
                                  ->getSession()
                                  ->getPracticeProfileId();
        if (null === $practiceProfileId) {
            return null;
        }
        if ('' === $patientId) {
            $patientId = null;
        }

        $dummyVitalSign= new VitalSign();
        $dummyVitalSign->setPracticeProfileId($practiceProfileId);
        if (!$this->securityContext->isGranted('READ', $dummyVitalSign)) {
            throw new AccessDeniedException("This user or role is not allowed to retrieve these SOAP notes");
        }

        $er = $this->doctrine->getManager()->getRepository('PractoApiBundle:VitalSign');

        return $er->findCount($practiceProfileId, $patientId, $withDeleted);
    }

     /**
     * Update Fields
     *
     * @param vitalSign $vitalSign         - Vital Sign
     * @param array     $requestParams     - Request parameters
     * @param User      $user              - User
     * @param integer   $practiceProfileId - Practice Profile Id
     *
     * @return null
     */
    public function updateFields($vitalSign, $requestParams, $user, $practiceProfileId)
    {
        $errors = array();
        if (array_key_exists('patient_id', $requestParams)) {
            if (($patient = $vitalSign->getPatient()) && ($pId = $patient->getId())) {
                if ($pId != $requestParams['patient_id']) {
                    // Patient cannot be changed for an vitalSign once set
                    @$errors['patient_id'][] = 'Patient cannot be changed once set';
                }
            } else {
                $pppMapper = $this->legacyMapperLoader->load('PracticePatientProfile');
                $patient = $pppMapper->retrieve($requestParams['patient_id']);
                if (is_null($patient)) {
                    @$errors['patient_id'][] = 'Patient not found';
                } else if ($patient->isSoftDeleted()) {
                    @$errors['patient_id'][] = 'Patient is deleted';
                } else if ($patient->getPracticeProfileId() != $practiceProfileId) {
                    @$errors['patient_id'][] = 'Patient does not belong to this practice';
                } else {
                    $vitalSign->setPatient($patient);
                    $vitalSign->setPatientId($patient->getId());
                }
            }
            unset($requestParams['patient_id']);
        }

        // check for doctor id
        if (array_key_exists('doctor_id', $requestParams)) {
            if ($vitalSign->getDoctorId() == $requestParams['doctor_id']) {
                // Do nothing
            } else {
                $pdpMapper = $this->legacyMapperLoader->load('PracticeDoctorProfile');
                $pdp = $pdpMapper->retrieve($requestParams['doctor_id']);

                if (is_null($pdp)) {
                    @$errors['doctor_id'][] = 'Doctor does not exists';
                } else if ($pdp->isSoftDeleted()) {
                    @$errors['doctor_id'][] = 'Doctor is deleted';
                } else if ($pdp->getPracticeProfileId() != $practiceProfileId) {
                    @$errors['doctor_id'][] = 'Doctor does not belong to this practice';
                } else {
                    $vitalSign->setDoctor($pdp);
                    $vitalSign->setDoctorId($pdp->getId());
                }
            }
            unset($requestParams['doctor_id']);
        }
        if (array_key_exists('temperature_in_fahrenheit', $requestParams) &&
            !empty($requestParams['temperature_in_fahrenheit'])) {
            $requestParams['temperature_in_fahrenheit'] = round($requestParams['temperature_in_fahrenheit'], 2);
            if (!array_key_exists('temperature_measurement_method', $requestParams)
                    || empty($requestParams['temperature_measurement_method'])) {
                    @$errors['temperature_measurement_method'][] = "This value cannot be null for temperature field";
            }
        }

        if (array_key_exists('bp_systolic', $requestParams) && !empty($requestParams['bp_systolic'])
            || array_key_exists('bp_diastolic', $requestParams) && !empty($requestParams['bp_diastolic'])) {
            if (!array_key_exists('bp_measurement_method', $requestParams)
                || empty($requestParams['bp_measurement_method'])) {
                @$errors['bp_measurement_method'][] = "This value cannot be null for bp field";
            }
        }

        $vitalSign->setAttributes($requestParams);
        $vitalSign->setModifiedAt(new \DateTime('now'));
        $vitalSign->setModifiedByUserId($user);
        $validationErrors = $this->validator->validate($vitalSign);

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
     * Add New vital sign
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

        $vitalSign = new VitalSign();
        $vitalSign->setPracticeProfileId($practiceProfileId);
        $vitalSign->setCreatedAt(new \DateTime('now'));
        $vitalSign->setCreatedByUserId($user);
        if ($this->securityContext->isGranted('CREATE', $vitalSign)) {
            $vitalSign->setSoftDeleted(false);
            $this->updateFields($vitalSign, $requestParams, $user, $practiceProfileId);
            $em = $this->doctrine->getManager();
            $em->persist($vitalSign);
            $em->flush();
            $this->publishActivity(
                'add',
                'vital signs',
                $vitalSign->getId(),
                'patient',
                $vitalSign->getPatientId()
            );
        } else {
            throw new AccessDeniedException("This user or role is not allowed to create vital signs");
        }

        return $vitalSign;
    }

    /**
     * update Vital Sign
     *
     * @param vitalSign $vitalSign     - vitalSign
     * @param array     $requestParams - Reque parameters
     */
    public function update($vitalSign, $requestParams)
    {
        $session = $this->securityContext->getToken()->getSession();
        $user = $session->getUser();
        $practiceId = $session->getPracticeProfileId();

        if ($this->securityContext->isGranted('UPDATE', $vitalSign)) {
            $this->updateFields($vitalSign, $requestParams, $user, $practiceId);
            $em = $this->doctrine->getManager();
            $em->persist($vitalSign);
            $em->flush();

            $this->publishActivity(
                'edit',
                'vital signs',
                $vitalSign->getId(),
                'patient',
                $vitalSign->getPatientId()
            );
        } else {
            throw new AccessDeniedException("This user or role is not allowed to update this patient note");
        }
    }

     /**
     * Delete a Vital Sign(Soft-delete)
     *
     * @param vitalSign $vitalSign - Vital Sign
     *
     * @return null
     */
    public function delete($vitalSign)
    {
        if ($this->securityContext->isGranted('DELETE', $vitalSign)) {
            $vitalSign->setModifiedAt(new \DateTime('now'));
            $user = $this->securityContext
                         ->getToken()
                         ->getSession()
                         ->getUser();
            $vitalSign->setModifiedByUserId($user);
            $vitalSign->setSoftDeleted(true);

            $em = $this->doctrine->getManager();
            $em->persist($vitalSign);
            $em->flush();

            $this->publishActivity(
                'delete',
                'vital signs',
                $vitalSign->getId(),
                'patient',
                $vitalSign->getPatientId()
            );

        } else {
            throw new AccessDeniedException("This user or role is not allowed to delete this vital sign");
        }
    }

    /**
     * Load Sample
     *
     * @return VitalSign
     */
    public function loadSample()
    {
        $practiceProfileId = $this->securityContext
                                ->getToken()
                                ->getSession()
                                ->getPracticeProfileId();

        $medicalHistory = new MedicalHistory;
        $medicalHistory->setName('HyperTension');

        $patient = new PracticePatientProfile;
        $patient->setPracticeProfileId($practiceProfileId);
        $patient->setName('Kaushik Arora');
        $patient->setPrimaryMobile('+919900990099');
        $patient->setResidingAddress('4th Floor, Abhaya Heights, Bannerghatta Road Near Jayadeva Flyover');
        $patient->setGender('M');
        $patient->addMedicalHistory($medicalHistory);
        $patient->setCity('Bangalore');
        $patient->setLocality('Bannerghatta');
        $patient->setDateOfBirth('1985-04-12');
        $patient->setBloodGroup('A+');

        $doctor = new PracticeDoctorProfile;
        $doctor->setName('Dr. Ashok Mehta');

        $vitalSign = new VitalSign;
        $vitalSign->setPracticeProfileId($practiceProfileId);
        $vitalSign->setObservedAt(new \DateTime('today'));
        $vitalSign->setPatient($patient);
        $vitalSign->setDoctor($doctor);

        $vitalSign->setTemperatureInFahrenheit(101);
        $vitalSign->setTemperatureMeasurementMethod('ORAL');
        $vitalSign->setBpSystolic(115);
        $vitalSign->setBpDiastolic(85);
        $vitalSign->setBpMeasurementMethod('SITTING');
        $vitalSign->setHeartRate(120);
        $vitalSign->setWeightInKg(85.20);

        return $vitalSign;
    }

    /**
     * Send Email To Patient
     *
     * @param VitalSign $vitalSign - Vital Sign
     * @param string    $email     - Optionally override email address
     */
    public function sendEmailToPatient($vitalSign, $email=null)
    {
        $user = $this->securityContext->getToken()->getUser();

        $errors = new ConstraintViolationList();
        if (!$email) {
            $email = $vitalSign->getPatient()->getPrimaryEmail();

            if (!$email) {
                $errors->add(new ConstraintViolation(
                    'This field is required as patient does not have email.',
                    '',
                    array(),
                    '',
                    'email',
                    null));
            }
        }

        $emailConstraint = new EmailConstraint();
        $errorList = $this->validator->validateValue($email, $emailConstraint);
        foreach ($errorList as $error) {
            $errors->add(new ConstraintViolation(
                $error->getMessage(),
                '',
                array(),
                '',
                'email',
                null));
        };

        if (0 < count($errors)) {
            throw new ValidationError($errors);
        }

        $serializedVitalSign = serialize($vitalSign);

        $payload = array(
            'host' => $this->practoDomain->getHost(),
            'reason' => 'vital_sign',
            'vital_sign' => $serializedVitalSign,
            'recipient_type' => 'patient',
            'email' => $email,
            'user_id' => $user->getId()
        );

        $this->emailProducer->publish(
            json_encode($payload),
            explode('.', explode('/', $payload['host'])[2])[0]
        );
    }
}
