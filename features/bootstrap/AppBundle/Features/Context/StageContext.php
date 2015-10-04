<?php

namespace AppBundle\Features\Context;

use AppBundle\Repository\StageRepository;
use AppBundle\Service\CRUD\CRUDStage;
use AppKernel;
use Behat\Behat\Tester\Exception\PendingException;
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
     * @Given les étapes suivantes au voyage :voyageName :
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
     * @When je supprime l'étape :destinationName du voyage :voyageName
     */
    public function jeSupprimeLÉtapeDuVoyage($destinationName, $voyageName)
    {
        $destination = $this->findDestinationByName($destinationName);
        $voyage = $this->findVoyageByName($voyageName);

        /** @var StageRepository $stageRepository */
        $stageRepository = $this->em->getRepository('AppBundle:Stage');

        $this->CRUDStage->remove($stageRepository->findOneStageFromDestinationAndVoyage($destination, $voyage));
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
        $stageRepository = $this->em->getRepository('AppBundle:Stage');

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

        /** @var StageRepository $stageRepository */
        $stageRepository = $this->em->getRepository('AppBundle:Stage');

        $stage = $stageRepository->findOneStageFromDestinationAndVoyage($destination, $voyage);

        $this->CRUDStage->changePosition($stage, $oldPosition, $newPosition);
    }
}