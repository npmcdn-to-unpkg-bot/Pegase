<?php

namespace CalculatorBundle\Service\CRUD;

use AppBundle\Entity\Destination;
use CalculatorBundle\Entity\Stage;
use CalculatorBundle\Entity\Voyage;
use CalculatorBundle\Repository\AvailableJourneyRepository;
use CalculatorBundle\Repository\StageRepository;
use CalculatorBundle\Service\Journey\BestJourneyFinder;
use CalculatorBundle\Service\Journey\JourneyService;
use Doctrine\ORM\EntityManager;

class CRUDStage
{

    const TRAIN = "TRAIN";
    const BUS = "BUS";
    const FLY = "FLY";
    const NONE = "NONE";

    /**
     * @var EntityManager
     */
    private $em;

    /** @var StageRepository */
    private $stageRepository;

    /** @var JourneyService */
    private $journeyService;

    public function __construct(EntityManager $em, JourneyService $journeyService)
    {
        $this->em = $em;
        $this->journeyService = $journeyService;
        $this->stageRepository = $em->getRepository('CalculatorBundle:Stage');
    }


    /**
     * @param Destination $destination
     * @param Voyage $voyage
     * @param int $nbDays
     * @return Stage
     */
    public function add(Destination $destination, Voyage $voyage, $nbDays)
    {
        $nbStages = count($voyage->getStages());
        $stage = new Stage();
        $stage->setDestination($destination);
        $stage->setNbDays($nbDays);
        $stage->setPosition($nbStages + 1);
        $stage->setVoyage($voyage);
        $this->em->persist($stage);

        $voyage->addStage($stage);
        $this->em->persist($voyage);

        $this->em->flush();

        if ($nbStages == 0) {
            $this->journeyService->updateJourneyByVoyage($voyage);
        } else {
            $stageBefore = $this->stageRepository->findStageByPosition($voyage, $nbStages);
            $this->journeyService->updateJourneyByStage($stageBefore);
        }

        return $stage;
    }

    /**
     * @param Stage $stage
     */
    public function remove(Stage $stage)
    {
        $voyage = $stage->getVoyage();
        $position = $stage->getPosition();

        $originalPosition = $position;

        /** @var Stage $stageToChange */
        $stageToChange = $this->stageRepository->findOneBy(['voyage' => $voyage, 'position' => $position + 1]);
        while (!is_null($stageToChange)) {
            $stageToChange->setPosition($position);
            $this->em->persist($stageToChange);
            $this->em->flush();
            $position++;
            $stageToChange = $this->stageRepository->findOneBy(['voyage' => $voyage, 'position' => $position + 1]);
        }

        $this->em->remove($stage);
        $this->em->flush();

        $this->updateJourney($voyage, $originalPosition - 1);
    }


    /**
     * @param Stage $stage
     * @param int $oldPosition
     * @param int $newPosition
     * @return Stage
     */
    public function changePosition(Stage $stage, $oldPosition, $newPosition)
    {
        $voyage = $stage->getVoyage();
        if ($newPosition < $oldPosition) {
            $itPosition = $newPosition;
            while ($itPosition != $oldPosition) {
                /** @var Stage $stageIt */
                $stageIt = $this->stageRepository->findOneBy(['voyage' => $voyage, 'position' => $itPosition]);
                $itPosition++;
                $stageIt->setPosition($itPosition);
                $this->em->persist($stageIt);
            }
        } elseif ($newPosition > $oldPosition) {
            $itPosition = $newPosition;
            while ($itPosition != $oldPosition) {
                /** @var Stage $stageIt */
                $stageIt = $this->stageRepository->findOneBy(['voyage' => $voyage, 'position' => $itPosition]);
                $itPosition--;
                $stageIt->setPosition($itPosition);
                $this->em->persist($stageIt);
            }
        }

        $stage->setPosition($newPosition);
        $this->em->persist($stage);
        $this->em->flush();

        $this->journeyService->updateJourneyByStage($stage);

        if ($newPosition < $oldPosition) {
            $this->updateJourney($voyage, $oldPosition);
        } elseif ($newPosition > $oldPosition) {
            $this->updateJourney($voyage, $oldPosition - 1);
        }
        $this->updateJourney($voyage, $newPosition - 1);

        return $stage;
    }

    /**
     * @param Stage $stage
     * @param float $nbDays
     */
    public function changeNumberDays($stage, $nbDays)
    {
        $stage->setNbDays($nbDays);
        $this->em->persist($stage);
        $this->em->flush();
    }

    /**
     * @param Stage $stage
     * @param string $transportType
     */
    public function changeTransportType(Stage $stage, $transportType)
    {
        $stage->setTransportType($transportType);
        $this->em->persist($stage);
        $this->em->flush();
    }

    /**
     * @param Voyage $voyage
     * @param $position
     */
    private function updateJourney(Voyage $voyage, $position)
    {
        $stageBefore = $this->stageRepository->findStageByPosition($voyage, $position);
        if (is_null($stageBefore)) {
            $this->journeyService->updateJourneyByVoyage($voyage);
        } else {
            $this->journeyService->updateJourneyByStage($stageBefore);
        }
    }
}
