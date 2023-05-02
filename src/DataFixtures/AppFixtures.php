<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{

    private UserPasswordHasherInterface $hasher;

    /**
     * @param UserPasswordHasherInterface $hasher
     */
    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void
    {
        $User = new User();

        $User->setRoles(['ROLE_USER']);
        $password = $this->hasher->hashPassword($User, 'password');
        $User->setEmail("user@gmail.com");
        $User->setPassword($password);

        $manager->persist($User);
        $manager->flush();

        $Admin = new User();

        $Admin->setRoles(['ROLE_SUPER_ADMIN']);
        $adminPassword = $this->hasher->hashPassword($Admin, 'password');
        $Admin->setEmail("admin@gmail.com");
        $Admin->setPassword($adminPassword);

        $manager->persist($Admin);
        $manager->flush();
    }
}
