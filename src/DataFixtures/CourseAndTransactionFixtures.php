<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\UserRepository;
use DateInterval;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CourseAndTransactionFixtures extends Fixture implements DependentFixtureInterface
{

    /**
     * @return list<class-string<FixtureInterface>>
     */
    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }

    private UserRepository $userRepository;

    /**
     * @param UserRepository $userRepository
     */
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function load(ObjectManager $manager): void
    {

        $course1 = new Course();
        $course1->setType(0);
        $course1->setCode('course-1');

        $course2 = new Course();
        $course2->setType(1);
        $course2->setCode('course-2');
        $course2->setPrice(2000);

        $course3 = new Course();
        $course3->setType(2);
        $course3->setCode('course-3');
        $course3->setPrice(25000);

        $manager->persist($course1);
        $manager->persist($course2);
        $manager->persist($course3);

        $manager->flush();

        $admin = $manager->getRepository(User::class)->findOneBy(['email' => 'admin@gmail.com']);
        $user = $manager->getRepository(User::class)->findOneBy(['email' => 'user@gmail.com']);

        $currentDate = new DateTimeImmutable('now');

        $transaction1 = new Transaction();
        $transaction1->setCourse($course1);
        $transaction1->setClient($user);
        $transaction1->setType(0);
        $transaction1->setAmount(0);
        $transaction1->setCreatedAt($currentDate->add(new DateInterval('P2D')));

        $transaction2 = new Transaction();
        $transaction2->setCourse($course2);
        $transaction2->setClient($admin);
        $transaction2->setType(0);
        $transaction2->setAmount($course2->getPrice());
        $transaction2->setCreatedAt($currentDate->add(new DateInterval('P1D')));
        $transaction2->setExpiresAt($currentDate->add(new DateInterval('P8D')));

        $transaction3 = new Transaction();
        $transaction3->setCourse($course3);
        $transaction3->setClient($admin);
        $transaction3->setType(0);
        $transaction3->setAmount($course3->getPrice());
        $transaction3->setCreatedAt($currentDate->add(new DateInterval('P3D')));

        $transaction4 = new Transaction();
        $transaction4->setClient($admin);
        $transaction4->setType(1);
        $transaction4->setAmount(50000);
        $transaction4->setCreatedAt($currentDate);

        $year = 2023;
        $month = 2;
        $day = 16;
        $hour = 7;
        $minute = 24;
        $second = 10;

        $oldDate =(new DateTimeImmutable)
            ->setTime($hour, $minute, $second)
            ->setDate($year, $month, $day);

        $transaction5 = new Transaction();
        $transaction5->setCourse($course2);
        $transaction5->setClient($admin);
        $transaction5->setType(0);
        $transaction5->setAmount($course2->getPrice());
        $transaction5->setCreatedAt($oldDate);
        $transaction5->setExpiresAt($oldDate->add(new DateInterval('P8D')));

        $manager->persist($transaction1);
        $manager->persist($transaction2);
        $manager->persist($transaction3);
        $manager->persist($transaction4);
        $manager->persist($transaction5);

        $manager->flush();
    }
}
