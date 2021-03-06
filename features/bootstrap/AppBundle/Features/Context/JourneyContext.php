<?php

namespace AppBundle\Features\Context;

use AppKernel;
use Behat\Behat\Tester\Exception\PendingException;
use Behat\Gherkin\Node\TableNode;
use CalculatorBundle\Entity\AvailableJourney;
use CalculatorBundle\Service\CRUD\CRUDAvailableJourney;
use CalculatorBundle\Worker\FetchAvailableJourney;
use CalculatorBundle\Worker\UpdateVoyageWorker;
use Symfony\Component\DependencyInjection\ContainerInterface;

class JourneyContext extends CommonContext
{
    /** @var FetchAvailableJourney */
    private $fetchAvailableJourney;

    /** @var UpdateVoyageWorker */
    private $updateVoyageWorker;

    /** @var CRUDAvailableJourney */
    private $CRUDAvailableJourney;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->fetchAvailableJourney = $container->get('fetch_available_journey_worker');
        $this->updateVoyageWorker = $container->get('update_voyages_worker');
        $this->CRUDAvailableJourney = $container->get('crud_available_journey');
    }

    /**
     * @Given les possibilitées de transports :
     */
    public function lesPossibilitéesDeTransports(TableNode $tableAvailableJourney)
    {
        foreach ($tableAvailableJourney as $availableJourneyRow) {
            $availableJourney = new AvailableJourney();
            $availableJourney->setFromDestination($this->findDestinationByName($availableJourneyRow['depuis']))
                ->setToDestination($this->findDestinationByName($availableJourneyRow["jusqu'à"]));

            if ($availableJourneyRow['prix avion']) {
                $availableJourney->setFlyPrices($availableJourneyRow['prix avion'])
                    ->setFlyTime($availableJourneyRow['temps avion']);
            }
            if ($availableJourneyRow['prix bus']) {
                $availableJourney->setBusPrices($availableJourneyRow['prix bus'])
                    ->setBusTime($availableJourneyRow['temps bus']);
            }
            if ($availableJourneyRow['prix train']) {
                $availableJourney->setTrainPrices($availableJourneyRow['prix train'])
                    ->setTrainTime($availableJourneyRow['temps train']);
            }

            $this->em->persist($availableJourney);
        }
        $this->em->flush();
    }

    /**
     * @When je change le mode de transport à :transportType pour le trajet de :fromDestination à :toDestination du voyage :voyageName
     */
    public function jeChangeLeModeDeTransportAPourLeTrajetDeÀDeLUtilisateur($transportType, $fromDestination, $toDestination, $voyageName)
    {
        $voyage = $this->findVoyageByName($voyageName);
        $destination = $this->findDestinationByName($fromDestination);
        $stages = $this->findStageByDestinationAndVoyage($destination, $voyage);

        $stage = $stages[0];
        $stage->setTransportType($transportType);
    }

    /**
     * @Then il existe les transports suivants au voyage :voyageName :
     */
    public function ilExisteLesTransportsSuivantsAuVoyage($voyageName, TableNode $tableJourney)
    {
        $voyage = $this->findVoyageByName($voyageName);

        $nbJourney = 0;

        if (!is_null($voyage->getAvailableJourney())) {
            $nbJourney++;
        }

        $stages = $voyage->getStages();
        foreach ($stages as $stage) {
            if (!is_null($stage->getAvailableJourney())) {
                $nbJourney++;
            }
        }

        $this->assertEquals(count($tableJourney->getHash()), $nbJourney);

        foreach ($tableJourney as $journeyRow) {
            $fromDestinationName = $journeyRow['depuis'];
            $toDestinationName = $journeyRow["jusqu'à"];
            $transportTypeExpected = $journeyRow['type de transport'];

            $destinationFrom = $this->findDestinationByName($fromDestinationName);

            $transportType = null;
            $availableJourney = null;

            if ($destinationFrom->getId() === $voyage->getStartDestination()->getId()) {
                $transportType = $voyage->getTransportType();
                $availableJourney = $voyage->getAvailableJourney();
            } else {
                $stages = $this->findStageByDestinationAndVoyage($destinationFrom, $voyage);
                $transportType = $stages[0]->getTransportType();
                $availableJourney = $stages[0]->getAvailableJourney();
            }

            $this->assertEquals($transportTypeExpected, $transportType);
            $this->assertEquals($fromDestinationName, $availableJourney->getFromDestination()->getName());
            $this->assertEquals($toDestinationName, $availableJourney->getToDestination()->getName());
        }

    }

    /**
     * @When je lance la récupération des transports possibles
     */
    public function jeLanceLaRécupérationDesTransportsPossibles()
    {
        $this->fetchAvailableJourney->fetch();
    }

    /**
     * @Then les possibilitées de transports sont :
     */
    public function lesPossibilitéesDeTransportsSont(TableNode $tableAvailableJourney)
    {
        $availableJourneyRepository = $this->em->getRepository('CalculatorBundle:AvailableJourney');

        $this->assertSameSize($tableAvailableJourney->getHash(), $availableJourneyRepository->findAll());

        foreach ($tableAvailableJourney as $availableJourneyRow) {
            $fromDestination = $this->findDestinationByName($availableJourneyRow['depuis']);
            $toDestination = $this->findDestinationByName($availableJourneyRow["jusqu'à"]);

            /** @var AvailableJourney $availableJourney */
            $availableJourney = $availableJourneyRepository->findOneBy(['fromDestination' => $fromDestination, 'toDestination' => $toDestination]);

            $this->assertNotNull($availableJourney, $fromDestination->getName() . " - " . $toDestination->getName());

            $this->assertEquals($availableJourneyRow['prix avion'], $availableJourney->getFlyPrices(), $fromDestination->getName() . " - " . $toDestination->getName());
            $this->assertEquals($availableJourneyRow['temps avion'], $availableJourney->getFlyTime(), $fromDestination->getName() . " - " . $toDestination->getName());
            $this->assertEquals($availableJourneyRow['prix bus'], $availableJourney->getBusPrices(), $fromDestination->getName() . " - " . $toDestination->getName());
            $this->assertEquals($availableJourneyRow['temps bus'], $availableJourney->getBusTime(), $fromDestination->getName() . " - " . $toDestination->getName());
            $this->assertEquals($availableJourneyRow['prix train'], $availableJourney->getTrainPrices(), $fromDestination->getName() . " - " . $toDestination->getName());
            $this->assertEquals($availableJourneyRow['temps train'], $availableJourney->getTrainTime(), $fromDestination->getName() . " - " . $toDestination->getName());
        }

    }

    /**
     * @When je met à jour les voyages avec les trajets disponibles
     */
    public function jeMetÀJourLesVoyagesAvecLesTrajetsDisponibles()
    {
        $this->updateVoyageWorker->run();
    }

    /**
     * @When j'affiche le trajet trouvé entre :fromDestinationName et :toDestinationName
     */
    public function jAfficheLeTrajetTrouvéEntreEt($fromDestinationName, $toDestinationName)
    {
        $fileName = $fromDestinationName . '-' . $toDestinationName;
        $file = file_get_contents(__DIR__ . sprintf("/../../data/%s.json", $fileName));

        var_dump($this->fetchAvailableJourney->extractAvailableJourney(json_decode($file, true)));
    }

    /**
     * @When je supprime les transports liés à la destination :destinationName
     */
    public function jeSupprimeLesTransportsLiésÀLaDestination($destinationName)
    {
        $destination = $this->findDestinationByName($destinationName);
        $this->CRUDAvailableJourney->removeAvailableJourneyByDestination($destination);
    }
}
