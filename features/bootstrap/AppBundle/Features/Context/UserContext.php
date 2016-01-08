<?php

namespace AppBundle\Features\Context;

use AppBundle\Entity\User;
use AppKernel;
use Behat\Gherkin\Node\TableNode;
use FOS\UserBundle\Model\UserManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class UserContext extends CommonContext
{
    /** @var UserManagerInterface */
    private $userManager;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->userManager = $container->get('fos_user.user_manager');
    }

    /**
     * @Given les utilisateurs :
     */
    public function lesUtilisateurs(TableNode $tableUsers)
    {
        foreach ($tableUsers as $userRow) {
            $name = $userRow['nom'];
            $password = isset($userRow['mot de passe']) ? $userRow['mot de passe'] : $name;

//            $user = new User();
//
            $user = $this->userManager->createUser();
            $user->setUsername($name);
            $user->setPlainPassword($password);
            $user->setEmail('guilhem@guilhem.com');
            $this->userManager->updateUser($user);

//            $this->em->persist($user);
        }
//        $this->em->flush();

    }
}
