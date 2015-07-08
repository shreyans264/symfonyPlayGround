<?php

namespace PersonBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use PersonBundle\Entity\Person;
use FOS\Rest\Util\Codes;
use FOS\RestBundle\View\View;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;


/**
 * Persons Controller
 *
 * @Route("/persons")
 */
class PersonsController extends Controller
{

    /**
     * Get persons
     *
     * @return View|array
     */
    public function getPersonsAction()
    {
        $request = $this->getRequest();
        $fileManager = $this->get('person.persons_manager');

       
        return array('files' => $output, 'count' => $filesCount);

    }

    /**
     * Upload file
     *
     * @return array
     */
    public function postPersonsAction()
    {
        $postData = $this->getRequest()->request->all();
        $fileManager = $this->get('practo_api.file_manager');
        try {
            $file = $fileManager->add($postData, $this->getRequest()->files);
        } catch (AccessDeniedException $e) {
            return View::create($e->getMessage(), Codes::HTTP_FORBIDDEN);
        } catch (ValidationError $e) {
            return View::create(json_decode($e->getMessage(), true), Codes::HTTP_BAD_REQUEST);
        } catch (BadAttributeException $e) {
            return View::create($e->getMessage(), Codes::HTTP_BAD_REQUEST);
        } catch(InvalidUUIDException $e){
            return View::create($e->getMessage(), Codes::HTTP_BAD_REQUEST);
        }

        $router = $this->get('router');
        $fileURL = $router->generate('get_file', array(
            'fileId' => $file->getId()), true);


        return View::create($file->serialise(),
            Codes::HTTP_CREATED,
            array('Location' => $fileURL));
    }

    /**
     * Delete File
     *
     * @param integer $fileId - File Id
     *
     * @return View
     */
    public function deletePersonAction($personId)
    {
        $fileManager = $this->get('practo_api.file_manager');

        try {
            $file = $fileManager->load($fileId);
        } catch (AccessDeniedException $e) {
            return View::create($e->getMessage(), Codes::HTTP_FORBIDDEN);
        }
        if (null === $file) {
            return View::create(null, Codes::HTTP_NOT_FOUND);
        } else if ($file->isSoftDeleted()) {
            return View::create(null, Codes::HTTP_GONE);
        }

        try {
            $fileManager->delete($file);
        } catch (AccessDeniedException $e) {
            return View::create($e->getMessage(), Codes::HTTP_FORBIDDEN);
        }

        return View::create(null, Codes::HTTP_NO_CONTENT);
    }
}


