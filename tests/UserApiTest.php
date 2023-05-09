<?php

namespace App\Tests;

use App\Service\PaymentService;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use JMS\Serializer\Serializer;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class UserApiTest extends AbstractTest
{

    /**
     * @throws \Exception
     */
    protected function getFixtures(): array
    {
        $userPassHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $paymentService = self::getContainer()->get(PaymentService::class);
        return [new \App\DataFixtures\UserFixtures($userPassHasher, $paymentService)];
    }

    /**
     * @throws \Exception
     */
    protected static function getEntityManager()
    {
        return static::getContainer()->get('doctrine')->getManager();
    }

    /**
     * @throws \Exception
     */

    /**
     * @throws \Exception
     */

    /**
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    public function testRegistration(): void
    {
        $client = self::getClient();

        $goodBody = ['username' => 'user123@gmail.com',
            'password' => 'password'];

        $badJSONBody = ['u32se123145rname' => 'user123@gmail.com',
            'pa14212532532523ssword' => 'password',
            'smthing else' => 'value'];

        $badUsernameBody = ['username' => 'user123',
            'password' => 'password'];

        $badPasswordBody = ['username' => 'user123@gmail.com',
            'password' => 'pa'];

        $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['JSON' => $badJSONBody], JSON_THROW_ON_ERROR)
        );
        $json = $client->getResponse()->getContent();
        self::assertJson($json);
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertEquals('401', $array['code']);
        self::assertEquals('Email пуст!', $array['errors']['username']);
        self::assertEquals('Пароль пуст!', $array['errors']['password']);

        $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($badUsernameBody, JSON_THROW_ON_ERROR)
        );
        $json = $client->getResponse()->getContent();
        self::assertJson($json);
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertEquals('401', $array['code']);
        self::assertEquals(
            'Email заполнен не по формату |почтовыйАдрес@почтовыйДомен.домен| .',
            $array['errors']['username']
        );

        $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($badPasswordBody, JSON_THROW_ON_ERROR)
        );
        $json = $client->getResponse()->getContent();
        self::assertJson($json);
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertEquals('401', $array['code']);
        self::assertEquals('Пароль должен содержать минимум 6 символов.', $array['errors']['password']);

        $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($goodBody, JSON_THROW_ON_ERROR)
        );
        $json = $client->getResponse()->getContent();
        self::assertJson($json);
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('token', $array);
        self::assertArrayHasKey('balance', $array);
        self::assertArrayHasKey('ROLES', $array);


        // проверка на уникальность пользователя
        $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($goodBody, JSON_THROW_ON_ERROR)
        );
        $json = $client->getResponse()->getContent();
        self::assertJson($json);
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertEquals('401', $array['code']);
        self::assertEquals('Пользователь с такой электронной почтой уже существует!', $array['errors']['unique']);
    }

    /**
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    public function testAuthorization(): void
    {
        $client = self::$client;

        $goodBody = ['username' => 'user@gmail.com',
            'password' => 'password'];

        $badBody = ['username' => 'user123142352@gmail.com',
            'password' => 'password'];

        $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($badBody, JSON_THROW_ON_ERROR)
        );
        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertEquals('401', $array['code']);
        self::assertEquals('Invalid credentials.', $array['message']);

        $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($goodBody, JSON_THROW_ON_ERROR)
        );
        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('token', $array);
    }

    /**
     * @throws \JsonException
     */
    public function getClientTokens($client): array
    {
        $credentials = ['username' => 'user@gmail.com',
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
        return $array;
    }

    /**
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    public function testCurrentUsers(): void
    {
        $client = self::$client;

        $token = $this->getClientTokens($client)['token'];

        $client->request(
            'GET',
            '/api/v1/users/current',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json',]
        );
        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('username', $array);
        self::assertArrayHasKey('balance', $array);
        self::assertArrayHasKey('roles', $array);
        self::assertEquals('user@gmail.com', $array['username']);

        $client->request(
            'GET',
            '/api/v1/users/current',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token. '123', 'CONTENT_TYPE' => 'application/json',]
        );
        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertEquals('401', $array['code']);
        self::assertEquals('Invalid JWT Token', $array['message']);
    }

    public function testPayCourse() {
        $client = self::$client;

    }
}
