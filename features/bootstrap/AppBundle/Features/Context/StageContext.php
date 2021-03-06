<?php

namespace AppBundle\Features\Context;

use CalculatorBundle\Repository\StageRepository;
use CalculatorBundle\Service\CRUD\CRUDStage;
use AppKernel;
use Behat\Gherkin\Node\TableNode;
use Symfony\Component\DependencyInjection\ContainerInterface;

class StageContext extends CommonContext
{
    /** @var CRUDStage */
    private $CRUDStage;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->CRUDStage = $container->get('crud_stage');
    }

    /**
     * @When j'ajoute les étapes suivantes au voyage :voyageName :
     */
    public function jAjouteLesÉtapesSuivantesAuVoyage($voyageName, TableNode $tableStages)
    {
        $voyage = $this->findVoyageByName($voyageName);
        foreach ($tableStages as $stageRow) {
            $destination = $this->findDestinationByName($stageRow['destination']);
            $this->CRUDStage->add($destination, $voyage, $stageRow['nombre de jour']);
        }
    }

    /**
     * @When je supprime l'étape :destinationName à la position :position du voyage :voyageName
     */
    public function jeSupprimeLÉtapeALaPositionDuVoyage($destinationName, $position, $voyageName)
    {
        $destination = $this->findDestinationByName($destinationName);
        $voyage = $this->findVoyageByName($voyageName);
        $stages = $this->findStageByDestinationAndVoyage($destination, $voyage);

        foreach ($stages as $stage) {
            if ($stage->getPosition() == $position) {
                $this->CRUDStage->remove($stage);
                return;
            }
        }

        $this->fail("Stage '$destinationName' position $position not found");
    }

    /**
     * @Then la voyage :voyageName à les étapes suivantes :
     */
    public function laVoyageÀLesÉtapesSuivantes($voyageName, TableNode $tableStages)
    {
        $voyage = $this->findVoyageByName($voyageName);
        $stages = $voyage->getStages();

        $this->assertSameSize($tableStages, $stages);

        /** @var StageRepository $stageRepository */
        $stageRepository = $this->em->getRepository('CalculatorBundle:Stage');

        foreach ($tableStages as $stageRow) {
            $destination = $this->findDestinationByName($stageRow['destination']);
            $this->assertNotNull(
                $stageRepository->findOneBy(['voyage' => $voyage, 'destination' => $destination, 'position' => $stageRow['position']]),
                "Can't find destination " . $destination->getName());
        }
    }

    /**
     * @When je change l'étape :destinationName du voyage :voyageName de la position :oldPosition à la position :newPosition
     */
    public function jeChangeLÉtapeDuVoyageDeLaPositionÀLaPosition($destinationName, $voyageName, $oldPosition, $newPosition)
    {
        $destination = $this->findDestinationByName($destinationName);
        $voyage = $this->findVoyageByName($voyageName);
        $stages = $this->findStageByDestinationAndVoyage($destination, $voyage);

        foreach ($stages as $stage) {
            if ($stage->getPosition() == $oldPosition) {
                $this->CRUDStage->changePosition($stage, $oldPosition, $newPosition);
                return;
            }
        }

        $this->fail("Stage '$destinationName' position $oldPosition not found");
    }
}
