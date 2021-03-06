<?php

namespace AppBundle\Features\Context;

use AppBundle\Entity\Country;
use AppBundle\Entity\Currency;
use AppBundle\Entity\Destination;
use AppBundle\Entity\User;
use AppBundle\Repository\CurrencyRepository;
use CalculatorBundle\Entity\Stage;
use CalculatorBundle\Entity\Voyage;
use AppBundle\Repository\CountryRepository;
use AppBundle\Repository\DestinationRepository;
use AppBundle\Repository\UserRepository;
use CalculatorBundle\Repository\StageRepository;
use CalculatorBundle\Repository\VoyageRepository;
use Behat\Behat\Context\Context;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CommonContext extends \PHPUnit_Framework_TestCase implements Context
{

    /** @var  EntityManager */
    protected $em;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->em = $container->get('doctrine')->getManager();
    }

    /**
     * @param string $name
     * @return Country|null
     */
    protected function findCountryByName($name)
    {
        /** @var CountryRepository $countryRepository */
        $countryRepository = $this->em->getRepository('AppBundle:Country');
        return $countryRepository->findOneByName($name);
    }

    /**
     * @param string $code
     * @return Currency|null
     */
    protected function findCurrencyByCode($code)
    {
        /** @var CurrencyRepository $currencyRepository */
        $currencyRepository = $this->em->getRepository('AppBundle:Currency');
        return $currencyRepository->findOneByCode($code);
    }

    /**
     * @return User[]
     */
    protected function findUsers()
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->em->getRepository('AppBundle:User');
        return $userRepository->findAll();
    }

    /**
     * @param string $name
     * @return Destination
     */
    protected function findDestinationByName($name)
    {
        /** @var DestinationRepository $destinationRepository */
        $destinationRepository = $this->em->getRepository('AppBundle:Destination');
        return $destinationRepository->findOneByName($name);
    }

    /**
     * @param string $name
     * @return Voyage
     */
    protected function findVoyageByName($name)
    {
        /** @var VoyageRepository $voyageRepository */
        $voyageRepository = $this->em->getRepository('CalculatorBundle:Voyage');
        return $voyageRepository->findOneByName($name);
    }

    /**
     * @param Destination $destination
     * @param Voyage $voyage
     * @return Stage[]
     */
    protected function findStageByDestinationAndVoyage(Destination $destination, Voyage $voyage)
    {
        /** @var StageRepository $stageRepository */
        $stageRepository = $this->em->getRepository('CalculatorBundle:Stage');

        return $stageRepository->findStagesFromDestinationAndVoyage($destination, $voyage);
    }

}
