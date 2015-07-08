<?php

namespace Practo\ApiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use FOS\RestBundle\View\View;
use FOS\Rest\Util\Codes;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 * Chart Legends Controller
  *
 * @Route("/chartlegends")
 */
class BabasController
{
    /**
     * Create Chart Legend Action
     *
     * @return View
     */
    public function postBabaAction()
    {
       $postData = $this->getRequest()->request->all();
        $babasManager = $this->get('practo_api.file_manager');
        try {
            $file = $babasManager->add($postData, $this->getRequest()->files);
        } catch (AccessDeniedException $e) {
            return View::create($e->getMessage(), Codes::HTTP_FORBIDDEN);
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
     * Get chartlegend Action
     *
     * @param integer $chartLegendId - Chart Legend Id
     *
     * @Route(name="get_chartlegend", methods="GET", path="/{chartLegendId}.{_format}")
     *
     * @return array
     */
    public function getChartlegendAction($chartLegendId)
    {
        $request = $this->getRequest();
        $chartLegendManager = $this->get('practo_api.chart_legend_manager');

        try {
            $chartLegend = $chartLegendManager->load(
                $chartLegendId
            );
        } catch (AccessDeniedException $e) {
            return View::create($e->getMessage(), Codes::HTTP_FORBIDDEN);
        }
        if (null === $chartLegend) {
            return View::create(null, Codes::HTTP_NOT_FOUND);
        } else if ($chartLegend->isSoftDeleted()) {
            return View::create(null, Codes::HTTP_GONE);
        }

        return $chartLegend;
    }

    /**
     * Get Chart Legends Action
     *
     * @return array
     */
    public function getChartlegendsAction()
    {
        $request = $this->getRequest();
        $chartLegendsManager = $this->get('practo_api.chart_legend_manager');
        $softDeleted = $this->parseBoolean($request->get('soft_deleted'));

        try {
            $chartLegends = $chartLegendsManager->loadAll($softDeleted);
            $chartLegendsCount = $chartLegendsManager->getCount($softDeleted);
        } catch (AccessDeniedException $e) {
            return View::create($e->getMessage(), Codes::HTTP_FORBIDDEN);
        }

        return View::create(array('chart_legends' => $chartLegends, 'count' => $chartLegendsCount),
            Codes::HTTP_OK);
    }

    /**
     * Edit ChartLegends Action
     *
     * @param integer $chartLegendId
     *
     * @return array
     */
    public function patchChartlegendAction($chartLegendId)
    {
        $patchData = $this->getRequest()->request->all();
        $chartLegendManager = $this->get('practo_api.chart_legend_manager');

        try {
            $chartLegend = $chartLegendManager->load($chartLegendId);
        } catch (AccessDeniedException $e) {
            return View::create($e->getMessage(), Codes::HTTP_FORBIDDEN);
        }

        if (null === $chartLegend) {
            return View::create(null, Codes::HTTP_NOT_FOUND);
        } else if ($chartLegend->isSoftDeleted()) {
            return View::create(null, Codes::HTTP_GONE);
        }

        try {
            $chartLegendManager->update($chartLegend, $patchData);
            $chartLegend = $chartLegendManager->load($chartLegend->getId());
        } catch (AccessDeniedException $e) {
           return View::create($e->getMessage(), Codes::HTTP_FORBIDDEN);
        } catch (ValidationError $e) {
            return View::create(json_decode($e->getMessage(), true), Codes::HTTP_BAD_REQUEST);
        } catch (BadAttributeException $e) {
           return View::create($e->getMessage(), Codes::HTTP_BAD_REQUEST);
        }

        return $chartLegend->serialise();
    }

    /**
     * Delete ChartLegend Action
     *
     * @param integer $chartLegendId
     *
     * @return array
     */
    public function deleteChartlegendAction($chartLegendId)
    {
        $chartLegendManager = $this->get('practo_api.chart_legend_manager');

        try {
            $chartLegend = $chartLegendManager->load($chartLegendId);
        } catch (AccessDeniedException $e) {
            return View::create($e->getMessage(), Codes::HTTP_FORBIDDEN);
        }
        if (null === $chartLegend) {
            return View::create(null, Codes::HTTP_NOT_FOUND);
        } else if ($chartLegend->isSoftDeleted()) {
            return View::create(null, Codes::HTTP_GONE);
        }

        try {
            $chartLegendManager->delete($chartLegend);
        } catch (AccessDeniedException $e) {
            return View::create($e->getMessage(), Codes::HTTP_FORBIDDEN);
        }

        return array('message' => 'success');
    }

}
