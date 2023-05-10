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
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CourseAndTransactionFixtures extends Fixture implements OrderedFixtureInterface
{
    public function getOrder(): int
    {
        return 2;
    }

    public function load(ObjectManager $manager): void
    {

        $course1 = new Course();
        $course1->setType(0);
        $course1->setCode('course-1');
        $course1->setTitle('ИТ-инженер');


        $course2 = new Course();
        $course2->setTitle('Профессия Графический дизайнер');
        $course2->setType(1);
        $course2->setCode('course-2');
        $course2->setPrice(1999.90);

        $course3 = new Course();
        $course3->setTitle('Профессия Бухгалтер');
        $course3->setType(2);
        $course3->setCode('course-3');
        $course3->setPrice(24999.90);

        $manager->persist($course1);
        $manager->persist($course2);
        $manager->persist($course3);

        $manager->flush();

        $admin = $manager->getRepository(User::class)->findOneBy(['email' => 'admin@gmail.com']);

        $year = 2023;
        $month = 2;
        $day = 16;
        $hour = 7;
        $minute = 24;
        $second = 10;

        $oldDate =(new DateTimeImmutable)
            ->setTime($hour, $minute, $second)
            ->setDate($year, $month, $day);

        $transaction1 = new Transaction();
        $transaction1->setCourse($course2);
        $transaction1->setClient($admin);
        $transaction1->setType(0);
        $transaction1->setAmount($course2->getPrice());
        $transaction1->setCreatedAt($oldDate);
        $transaction1->setExpiresAt($oldDate->add(new DateInterval('P7D')));

        $currentDate = new DateTimeImmutable('now');

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

        $manager->persist($transaction1);
        $manager->persist($transaction2);
        $manager->persist($transaction3);
        $manager->persist($transaction4);

        $manager->flush();
    }
}
