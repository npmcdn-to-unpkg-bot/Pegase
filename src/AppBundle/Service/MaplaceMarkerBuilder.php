<?php

namespace AppBundle\Service;

use AppBundle\Entity\Destination;
use AppBundle\Entity\Stage;
use AppBundle\Entity\Voyage;

class MaplaceMarkerBuilder
{

    /**
     * @var \Twig_Environment
     */
    private $twig;

    public function __construct(\Twig_Environment $twig)
    {
        $this->twig = $twig;
    }

    private function defaultOptions()
    {
        return [
            'disableHtml' => false,
            'disableZoom' => false,
        ];
    }

    /**
     * @param Destination $destination
     * @param array $options
     * @return array
     */
    public function buildMarkerFromDestination(Destination $destination, $options = [])
    {
        $options = array_merge($this->defaultOptions(), $options);
        $dataMaplace = [
            'lat'   => $destination->getLatitude(),
            'lon'   => $destination->getLongitude(),
            'title' => $destination->getName(),
        ];

        if (!$options['disableHtml']) {
            $dataMaplace['html'] = $this->twig->render('AppBundle:Destination:googleMarker.html.twig', ['destination' => $destination]);
        }

        if (!$options['disableZoom']) {
            $dataMaplace['zoom'] = 11;
        }

        return $dataMaplace;
    }

    /**
     * @param Destination[] $destinations
     * @param array $options
     * @return array
     */
    public function buildMarkerFromDestinations($destinations, $options = [])
    {
        $dataMaplace = [];
        foreach ($destinations as $destination) {
            $dataMaplace[] = $this->buildMarkerFromDestination($destination, $options);
        }

        return $dataMaplace;
    }

    /**
     * @param Stage[] $stages
     * @param array $options
     * @return array
     */
    public function buildMarkerFromStages($stages, $options = [])
    {
        $destinations = [];
        foreach ($stages as $stage) {
            $destinations[] = $stage->getDestination();
        }
        return $this->buildMarkerFromDestinations($destinations, $options);
    }

}
