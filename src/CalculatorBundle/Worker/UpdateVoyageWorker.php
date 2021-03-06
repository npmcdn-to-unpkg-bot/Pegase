<?php

namespace CalculatorBundle\Worker;

use AppBundle\Entity\Destination;
use CalculatorBundle\Entity\Stage;
use CalculatorBundle\Entity\Voyage;
use CalculatorBundle\Repository\AvailableJourneyRepository;
use CalculatorBundle\Repository\StageRepository;
use CalculatorBundle\Repository\VoyageRepository;
use CalculatorBundle\Service\Journey\BestJourneyFinder;
use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerInterface;

class UpdateVoyageWorker
{

    /** @var EntityManager */
    private $em;

    /** @var LoggerInterface */
    private $logger;

    /** @var AvailableJourneyRepository */
    private $availableJourneyRepository;

    /** @var VoyageRepository */
    private $voyageRepository;

    /** @var StageRepository */
    private $stageRepository;

    /** @var BestJourneyFinder */
    private $bestJourneyFinder;

    public function __construct(EntityManager $em, BestJourneyFinder $bestJourneyFinder, LoggerInterface $logger)
    {
        $this->em = $em;
        $this->bestJourneyFinder = $bestJourneyFinder;
        $this->logger = $logger;

        $this->availableJourneyRepository = $em->getRepository('CalculatorBundle:AvailableJourney');
        $this->voyageRepository = $em->getRepository('CalculatorBundle:Voyage');
        $this->stageRepository = $em->getRepository('CalculatorBundle:Stage');
    }

    public function run()
    {
        /** @var Voyage[] $voyages */
        $voyages = $this->voyageRepository->findAll();

        foreach ($voyages as $voyage) {
            $this->updateAvailableJourneys($voyage);
            $this->em->flush();
        }
    }

    /**
     * @param Voyage $voyage
     */
    private function updateAvailableJourneys(Voyage $voyage)
    {
        /** @var Stage[] $stages */
        $stages = $this->stageRepository->findBy(['voyage' => $voyage], ['position' => 'ASC']);

        if (empty($stages)) {
            $this->resetVoyageOrStage($voyage);

            return;
        } else {
            $firstStage = $stages[0];
            $this->updateAvailableJourneyIfNeeded($voyage, $voyage->getStartDestination(), $firstStage->getDestination());
        }

        $fromDestination = null;
        $toDestination = null;

        for ($i = 1; $i < count($stages); $i++) {
            $stageA = $stages[$i - 1];
            $stageB = $stages[$i];

            $this->updateAvailableJourneyIfNeeded($stageA, $stageA->getDestination(), $stageB->getDestination());
        }
    }

    /**
     * @param Voyage|Stage $voyageOrStage
     * @param Destination $fromDestination
     * @param Destination $toDestination
     */
    private function updateAvailableJourneyIfNeeded($voyageOrStage, Destination $fromDestination, Destination $toDestination)
    {
        $availableJourney = $voyageOrStage->getAvailableJourney();

        if (!is_null($availableJourney) &&
            $availableJourney->getFromDestination()->getId() == $fromDestination->getId() &&
            $availableJourney->getToDestination()->getId() == $toDestination->getId() &&
            !is_null($voyageOrStage->getTransportType())
        ) {
            return;
        }

        $availableJourney = $this->availableJourneyRepository->findOneBy(['fromDestination' => $fromDestination, 'toDestination' => $toDestination]);

        if (is_null($availableJourney)) {
            $this->resetVoyageOrStage($voyageOrStage);
            return;
        }

        $voyageOrStage->setAvailableJourney($availableJourney);
        $voyageOrStage->setTransportType($this->bestJourneyFinder->chooseBestTransportType($availableJourney));

        $this->em->persist($voyageOrStage);
    }

    /**
     * @param Voyage|Stage $voyageOrStage
     */
    private function resetVoyageOrStage($voyageOrStage)
    {
        $voyageOrStage->setAvailableJourney(null);
        $voyageOrStage->setTransportType(null);

        $this->em->persist($voyageOrStage);
    }
}
