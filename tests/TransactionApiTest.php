<?php

namespace App\Tests;

use App\DataFixtures\CourseAndTransactionFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PaymentService;
use App\Tests\AbstractTest;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TransactionApiTest extends AbstractTest
{

    protected function getFixtures(): array
    {
        $userPassHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $paymentService = self::getContainer()->get(PaymentService::class);
        return [new \App\DataFixtures\UserFixtures($userPassHasher, $paymentService),
            new \App\DataFixtures\CourseAndTransactionFixtures()];
    }

    /**
     * @throws \JsonException
     * @throws \Exception
     */
    public function testTransactions(): void
    {
        $client = self::$client;

        $credentials = ['username' => 'admin@gmail.com',
            'password' => 'password'];

        $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($credentials, JSON_THROW_ON_ERROR)
        );
        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('token', $array);
        $token = $array['token'];

        $em = self::getEntityManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'admin@gmail.com']);
        $transactions = $em->getRepository(Transaction::class)->findBy(['client' => $user->getId()]);

        $totalCount = count($transactions);

        $client->request(
            'GET',
            '/api/v1/transactions',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json',]
        );

        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertCount($totalCount, $array);

        $transactions = $em->getRepository(Transaction::class)->findBy(['client' => $user->getId(), 'type' => 0]);

        $filter = array();
        $filter['type'] = 'payment';

        $client->request(
            'GET',
            '/api/v1/transactions?' . http_build_query($filter),
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json',]
        );

        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertNotCount($totalCount, $array);
        self::assertCount(count($transactions), $array);

        $transactions = $em->getRepository(Transaction::class)->findBy(['client' => $user->getId(), 'type' => 1]);

        $filter = array();
        $filter['type'] = 'deposit';

        $client->request(
            'GET',
            '/api/v1/transactions?' . http_build_query($filter),
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json',]
        );

        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertNotCount($totalCount, $array);
        self::assertCount(count($transactions), $array);

        $transactions = $em->getRepository(Transaction::class)->createQueryBuilder('t')
            ->andWhere('t.client = :user')
            ->setParameter(
                'user',
                $em->getRepository(User::class)->findOneBy(['email' => 'admin@gmail.com'])
            )
            ->innerJoin('t.course', 'c', 'WITH', 'c.id = t.course')
            ->andWhere('c.code = :code')
            ->setParameter('code', 'course-2')
            ->getQuery()
            ->getResult();

        $filter = array();
        $filter['course_code'] = 'course-2';

        $client->request(
            'GET',
            '/api/v1/transactions?' . http_build_query($filter),
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json',]
        );

        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertNotCount($totalCount, $array);
        self::assertCount(count($transactions), $array);
        $countWithoutSkip = count($transactions);


        $transactions = $em->getRepository(Transaction::class)->createQueryBuilder('t')
            ->andWhere('t.client = :user')
            ->setParameter(
                'user',
                $em->getRepository(User::class)->findOneBy(['email' => 'admin@gmail.com'])
            )
            ->innerJoin('t.course', 'c', 'WITH', 'c.id = t.course')
            ->andWhere('c.code = :code')
            ->setParameter('code', 'course-2')
            ->andWhere('t.expiresAt > :now OR t.expiresAt is null')
            ->setParameter('now', new \DateTime('now'))
            ->getQuery()
            ->getResult();

        $filter = array();
        $filter['course_code'] = 'course-2';
        $filter['skip_expired'] = 'true';

        $client->request(
            'GET',
            '/api/v1/transactions?' . http_build_query($filter),
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json',]
        );

        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertNotCount($totalCount, $array);
        self::assertCount(count($transactions), $array);
        self::assertNotCount($countWithoutSkip, $array);

        self::assertSame($array[0]['course_code'], 'course-2');
    }
}
