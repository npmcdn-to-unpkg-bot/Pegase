<?php

namespace AppBundle\Command;

use AppBundle\Entity\Destination;
use AppBundle\Repository\CountryRepository;
use AppBundle\Repository\DestinationRepository;
use AppBundle\Service\CSVParser;
use AppBundle\Twig\AssetExistsExtension;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportDestinationsCommand extends ContainerAwareCommand
{

    const MAX_SIZE_DESCRIPTION_LENGTH = 950;

    /** @var  AssetExistsExtension */
    private $assetExistsExtension;

    /** @var  string */
    private $imagePath;

    protected function configure()
    {
        $this
            ->setName('app:import:destinations')
            ->setDescription("Permet d'importer et mettre à jour la liste des destinations, utiliser l'option -f pour forcer l'insert")
            ->addArgument('fileName', InputArgument::REQUIRED, 'Nom du fichier csv à importer')
            ->addOption('force', '-f');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $now = new \DateTime();
        $output->writeln('<comment>Start : ' . $now->format('d-m-Y G:i:s') . ' ---</comment>');

        $forceInsert = $input->getOption('force');
        $this->import($input, $output, $forceInsert);

        $now = new \DateTime();
        $output->writeln('<comment>End : ' . $now->format('d-m-Y G:i:s') . ' ---</comment>');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param boolean $forceInsert
     */
    private function import(InputInterface $input, OutputInterface $output, $forceInsert)
    {
        $this->assetExistsExtension = new AssetExistsExtension($this->getContainer()->get('kernel'));
        $this->imagePath = $this->getContainer()->getParameter('image_banner_destinations_path');

        /** @var EntityManager $em */
        $em = $this->getContainer()->get('doctrine')->getManager();

        /** @var CountryRepository $countryRepository */
        $countryRepository = $em->getRepository('AppBundle:Country');

        /** @var DestinationRepository $destinationRepository */
        $destinationRepository = $em->getRepository('AppBundle:Destination');

        $fileName = $input->getArgument('fileName');
        $dataDestinations = CSVParser::extract($fileName, $input, $output);
        $nbDestinations = count($dataDestinations);
        $output->writeln("<info>--- $nbDestinations destinations ont été trouvés dans le fichier ---</info>");

        $nbToFlush = 0;
        foreach ($dataDestinations as $dataDestination) {
            $countryName = $dataDestination['pays'];
            $name = $dataDestination['nom'];
            $description = $dataDestination['description'];

            $country = $countryRepository->findOneByName($countryName);
            if (is_null($country)) {
                $output->writeln("<error>Le pays '$countryName' de la destination '$name' n'a pas été trouvé. La destination n'a pas été importée.</error>");
                continue;
            }

            $destination = $destinationRepository->findOneByName($name);
            if (is_null($destination)) {
                $destination = new Destination();
                $destination->setName($name);
            }
            $destination->setCountry($country);
            $destination->setDescription(!empty($description) ? explode("\n", $description) : []);
            $destination->setTips($this->extractTips($dataDestination['bons plans']));
            $destination->setPeriodJanuary($dataDestination['janvier']);
            $destination->setPeriodFebruary($dataDestination['février']);
            $destination->setPeriodMarch($dataDestination['mars']);
            $destination->setPeriodApril($dataDestination['avril']);
            $destination->setPeriodMay($dataDestination['mai']);
            $destination->setPeriodJune($dataDestination['juin']);
            $destination->setPeriodJuly($dataDestination['juillet']);
            $destination->setPeriodAugust($dataDestination['août']);
            $destination->setPeriodSeptember($dataDestination['septembre']);
            $destination->setPeriodOctober($dataDestination['octobre']);
            $destination->setPeriodNovember($dataDestination['novembre']);
            $destination->setPeriodDecember($dataDestination['décembre']);

            $destination->setPriceAccommodation($dataDestination["prix de l'hébergement"]);
            $destination->setPriceLifeCost($dataDestination['coût de la vie']);
            $destination->setLatitude($dataDestination['latitude']);
            $destination->setLongitude($dataDestination['longitude']);
            $destination->setIsTheCapital($dataDestination['Capitale'] === 'oui');

            if ($this->isComplete($output, $destination) || $forceInsert) {
                $em->persist($destination);
                $nbToFlush++;

                $id = $destination->getId();
                if (!empty($id)) {
                    $output->writeln("<info>Modification de '$name'</info>");
                } else {
                    $output->writeln("<info>Nouvelle destination '$name'</info>");
                }
            }

            if ($nbToFlush % 50 == 0) {
                $em->flush();
                $em->clear();
            }
        }
        $em->flush();
        $em->clear();
    }

    /**
     * @param array $tipsStr
     * @return array
     */
    private function extractTips($tipsStr)
    {
        $tips = [];

        foreach (explode('>', $tipsStr) as $tip) {
            $tip = trim($tip);

            if (!empty($tip)) {
                $tips[] = $tip;
            }
        }

        return $tips;
    }


    /**
     * @param OutputInterface $output
     * @param Destination $destination
     * @return bool
     */
    private function isComplete(OutputInterface $output, Destination $destination)
    {
        $name = $destination->getName();
        $lat = $destination->getLatitude();
        $lon = $destination->getLongitude();
        $descriptions = $destination->getDescription();
        $periodJanuary = $destination->getPeriodJanuary();
        $periodFebruary = $destination->getPeriodFebruary();
        $periodMarch = $destination->getPeriodMarch();
        $periodApril = $destination->getPeriodApril();
        $periodMay = $destination->getPeriodMay();
        $periodJune = $destination->getPeriodJune();
        $periodJuly = $destination->getPeriodJuly();
        $periodAugust = $destination->getPeriodAugust();
        $periodSeptember = $destination->getPeriodSeptember();
        $periodOctober = $destination->getPeriodOctober();
        $periodNovember = $destination->getPeriodNovember();
        $periodDecember = $destination->getPeriodDecember();
        $priceAccommodation = $destination->getPriceAccommodation();
        $priceLifeCost = $destination->getPriceLifeCost();
        $tips = $destination->getTips();

        $errors = [];
        if (empty($lat)) {
            $errors[] = 'Latitude inconnue';
        }
        if (empty($lon)) {
            $errors[] = 'Longitude inconnue';
        }
        if (empty($descriptions)) {
            $errors[] = 'Description inconnue';
        } else {
            $length = 0;
            foreach ($descriptions as $description) {
                $length += strlen($description);
            }

            if ($length > self::MAX_SIZE_DESCRIPTION_LENGTH) {
                $errors[] = 'Description trop grande : ' . $length . '/' . self::MAX_SIZE_DESCRIPTION_LENGTH .
                    " caractères maximum.";
            } elseif ($length < 300) {
                $errors[] = 'Description trop petite';
            }
        }
        if (is_null($periodJanuary)) {
            $errors[] = 'Périodes Janvier inconnues';
        }
        if (is_null($periodFebruary)) {
            $errors[] = 'Périodes Février inconnues';
        }
        if (is_null($periodMarch)) {
            $errors[] = 'Périodes Mars inconnues';
        }
        if (is_null($periodApril)) {
            $errors[] = 'Périodes Avril inconnues';
        }
        if (is_null($periodMay)) {
            $errors[] = 'Périodes Mai inconnues';
        }
        if (is_null($periodJune)) {
            $errors[] = 'Périodes Juin inconnues';
        }
        if (is_null($periodJuly)) {
            $errors[] = 'Périodes Juillet inconnues';
        }
        if (is_null($periodAugust)) {
            $errors[] = 'Périodes Aout inconnues';
        }
        if (is_null($periodSeptember)) {
            $errors[] = 'Périodes Septembre inconnues';
        }
        if (is_null($periodOctober)) {
            $errors[] = 'Périodes Octobre inconnues';
        }
        if (is_null($periodNovember)) {
            $errors[] = 'Périodes Novembre inconnues';
        }
        if (is_null($periodDecember)) {
            $errors[] = 'Périodes Décembre inconnues';
        }
        if (empty($priceAccommodation)) {
            $errors[] = "Prix de l'hébergement inconnu";
        }

        if (empty($priceLifeCost)) {
            $errors[] = "Prix du coût de la vie inconnu";
        }

        if (empty($tips)) {
            $errors[] = 'Bons plans inconnus';
        }
        $destination->generateSlug();
        if (!$this->assetExistsExtension->assetExist($this->imagePath . $destination->getSlug() . '.jpg')) {
            $errors[] = "Pas d'image";
        }

        if (!empty($errors)) {
            $output->writeln("<error>Destination '$name'  --  ERREURS : " . join(' ; ', $errors) . ".</error>");

            return false;
        }

        return true;
    }
}
