<?php

namespace CalculatorBundle\Worker;

use AppBundle\Entity\Destination;
use AppBundle\Repository\DestinationRepository;
use CalculatorBundle\Entity\AvailableJourney;
use CalculatorBundle\Repository\AvailableJourneyRepository;
use CalculatorBundle\Service\CRUD\CRUDStage;
use CalculatorBundle\Service\Journey\JourneyFetcherInterface;
use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerInterface;

class FetchAvailableJourney
{

    /** @var EntityManager */
    private $em;

    /** @var JourneyFetcherInterface */
    private $journeyFetcher;

    /** @var AvailableJourneyRepository */
    private $availableJourneyRepository;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(EntityManager $em, JourneyFetcherInterface $journeyFetcher, LoggerInterface $logger)
    {
        $this->em = $em;
        $this->journeyFetcher = $journeyFetcher;
        $this->logger = $logger;

        $this->availableJourneyRepository = $em->getRepository('CalculatorBundle:AvailableJourney');
    }

    /**
     * @return AvailableJourney
     * @throws \Exception
     */
    public function fetchAll()
    {
        /** @var DestinationRepository $destinationRepository */
        $destinationRepository = $this->em->getRepository('AppBundle:Destination');

        /** @var Destination[] $fromDestinations */
        $fromDestinations = $destinationRepository->findAll();
        /** @var Destination[] $toDestinations */
        $toDestinations = $destinationRepository->findAll();

        $nbAvailableJourneyExtracted = 0;

        try {
            foreach ($fromDestinations as $fromDestination) {
                foreach ($toDestinations as $toDestination) {
                    if ($fromDestination->getId() == $toDestination->getId()) {
                        continue;
                    }

                    $currentAvailableJourney = $this->availableJourneyRepository->findOneBy(['fromDestination' => $fromDestination, 'toDestination' => $toDestination]);
                    if (!is_null($currentAvailableJourney)) {
                        continue;
                    }

                    $this->logger->info("Fetch data from " . $fromDestination->getName() . " to " . $toDestination->getName());
                    $data = $this->journeyFetcher->fetch($fromDestination, $toDestination);

                    if (!$data) {
                        $this->logger->error("Can't fetch data from " . $fromDestination->getName() . " to " . $toDestination->getName());
                        continue;
                    }

                    $availableJourney = $this->extractAvailableJourney($data);

                    if (is_null($availableJourney->getFlyPrices()) && is_null($availableJourney->getBusPrices()) && is_null($availableJourney->getTrainPrices())) {
                        $this->logger->error("No prices extracted from data from " . $fromDestination->getName() . " to " . $toDestination->getName());
                        continue;
                    }

                    $availableJourney->setFromDestination($fromDestination);
                    $availableJourney->setToDestination($toDestination);

                    $nbAvailableJourneyExtracted++;

                    $this->em->persist($availableJourney);

                    if ($nbAvailableJourneyExtracted % 50 == 0) {
                        $this->em->flush();
                    }

                    sleep(12);
                }
            }
        } catch (\Exception $e) {
            $this->em->flush();
            $this->logger->error($e->getMessage());
            throw $e;
        }

        $this->em->flush();
    }

    /**
     * @param array $data
     * @return AvailableJourney
     * @throws \Exception
     */
    private function extractAvailableJourney($data)
    {
        try {
            $routes = $data['routes'];

            $availableRoutes = [];

            foreach ($routes as $route) {
                $indicativePrice = $route['indicativePrice'];

                if (empty($indicativePrice)) {
                    continue;
                }

                $price = $indicativePrice['price'];
                $duration = $route['duration'];

                $mainType = $this->findMainType($route['segments']);

                if (is_null($mainType)) {
                    continue;
                }

                $availableRoutes[$mainType][] = ['price' => $price, 'duration' => $duration];
            }

            return $this->createAvailableJourney($availableRoutes);
        } catch (\Exception $e) {
            $this->logger->error(serialize($data));
            throw $e;
        }
    }

    /**
     * @param array $availableRoutes
     * @return AvailableJourney
     */
    private function createAvailableJourney($availableRoutes)
    {
        $availableJourney = new AvailableJourney();

        foreach ($availableRoutes as $typeOfTransport => $pricesAndDurations) {

            $bestPriceAndDuration = null;

            foreach ($pricesAndDurations as $priceAndDuration) {
                if (is_null($bestPriceAndDuration) || $priceAndDuration['price'] < $bestPriceAndDuration['price']) {
                    $bestPriceAndDuration = $priceAndDuration;
                }
            }

            if ($typeOfTransport === CRUDStage::FLY) {
                $availableJourney->setFlyPrices($bestPriceAndDuration['price']);
                $availableJourney->setFlyTime($bestPriceAndDuration['duration']);
            } elseif ($typeOfTransport === CRUDStage::TRAIN) {
                $availableJourney->setTrainPrices($bestPriceAndDuration['price']);
                $availableJourney->setTrainTime($bestPriceAndDuration['duration']);
            } elseif ($typeOfTransport === CRUDStage::BUS) {
                $availableJourney->setBusPrices($bestPriceAndDuration['price']);
                $availableJourney->setBusTime($bestPriceAndDuration['duration']);
            }

        }

        return $availableJourney;
    }

    /**
     * @param array $segments
     * @return string
     */
    private function findMainType($segments)
    {
        $distanceByType = [];
        foreach ($segments as $segment) {
            $typeOfTransport = $segment['kind'];
            $distance = $segment['distance'];

            if (!isset($distanceByType[$typeOfTransport])) {
                $distanceByType[$typeOfTransport] = 0;
            }
            $distanceByType[$typeOfTransport] = $distanceByType[$typeOfTransport] + $distance;
        }

        arsort($distanceByType);

        $mainTransportType = key($distanceByType);

        if ($mainTransportType === 'flight') {
            return CRUDStage::FLY;
        }
        if ($mainTransportType === 'bus') {
            return CRUDStage::BUS;
        }
        if ($mainTransportType === 'train') {
            return CRUDStage::TRAIN;
        } else {
            return null;
        }
    }
}