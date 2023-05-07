<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Service\PaymentService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{

    private UserPasswordHasherInterface $hasher;
    private PaymentService $paymentService;

    /**
     * @param UserPasswordHasherInterface $hasher
     */
    public function __construct(UserPasswordHasherInterface $hasher, PaymentService $paymentService)
    {
        $this->hasher = $hasher;
        $this->paymentService = $paymentService;
    }

    /**
     * @throws Exception
     */
    public function load(ObjectManager $manager): void
    {
        $user = new User();

        $user->setRoles(['ROLE_USER']);
        $password = $this->hasher->hashPassword($user, 'password');
        $user->setEmail("user@gmail.com");
        $user->setPassword($password);
        $this->paymentService->deposit($user, $_ENV['CLIENT_MONEY']);

        $manager->persist($user);
        $manager->flush();

        $admin = new User();

        $admin->setRoles(['ROLE_SUPER_ADMIN']);
        $adminPassword = $this->hasher->hashPassword($admin, 'password');
        $admin->setEmail("admin@gmail.com");
        $admin->setPassword($adminPassword);
        $this->paymentService->deposit($admin, 1000000);
        $manager->persist($admin);
        $manager->flush();
    }
}
