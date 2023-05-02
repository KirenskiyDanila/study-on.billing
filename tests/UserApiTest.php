<?php

namespace App\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class UserApiTest extends ApiTestCase
{
    /**
     * @throws \Exception
     */
    protected function getFixtures(): array
    {
        $userPassHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        return [new \App\DataFixtures\AppFixtures($userPassHasher)];
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
    protected function loadFixtures(array $fixtures = []) : void
    {
        $loader = new Loader();

        foreach ($fixtures as $fixture) {
            if (!\is_object($fixture)) {
                $fixture = new $fixture();
            }

            if ($fixture instanceof ContainerAwareInterface) {
                $fixture->setContainer(static::getContainer());
            }

            $loader->addFixture($fixture);
        }

        $em = static::getEntityManager();
        $purger = new ORMPurger($em);
        $executor = new ORMExecutor($em, $purger);
        $executor->execute($loader->getFixtures());
    }

    /**
     * @throws \Exception
     */
    protected function setUp(): void
    {
        $this->loadFixtures($this->getFixtures());
    }

    final protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    public function testRegistration(): void
    {
        $client = static::createClient();

        $goodBody = ['username' => 'user123@gmail.com',
            'password' => 'password'];

        $badJSONBody = ['u32se123145rname' => 'user123@gmail.com',
            'pa14212532532523ssword' => 'password',
            'smthing else' => 'value'];

        $badUsernameBody = ['username' => 'user123',
            'password' => 'password'];

        $badPasswordBody = ['username' => 'user123@gmail.com',
            'password' => 'pa'];

        $client->request('POST', '/api/v1/register', ['json' => $badJSONBody]);
        self::assertJsonEquals(['code' => '401', 'errors' => ['JSON' => 'Неверный JSON-формат данных!']]);

        $client->request('POST', '/api/v1/register', ['json' => $badUsernameBody]);
        self::assertJsonEquals(['code' => '401', 'errors' => ['username' => 'Email заполнен не по формату |почтовыйАдрес@почтовыйДомен.домен| .']]);

        $client->request('POST', '/api/v1/register', ['json' => $badPasswordBody]);
        self::assertJsonEquals(['code' => '401', 'errors' => ['password' => 'Пароль должен содержать минимум 6 символов.']]);

        $json = $client->request('POST', '/api/v1/register', ['json' => $goodBody])->getContent();
        self::assertJson($json);
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('token', $array);
        self::assertArrayHasKey('balance', $array);
        self::assertArrayHasKey('ROLES', $array);


        // проверка на уникальность пользователя
        $client->request('POST', '/api/v1/register', ['json' => $goodBody]);
        self::assertJsonEquals(['code' => '401', 'errors' => ['unique' => 'Пользователь с такой электронной почтой уже существует!']]);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    public function testAuthorization(): void
    {
        $client = static::createClient();

        $goodBody = ['username' => 'user@gmail.com',
            'password' => 'password'];

        $badBody = ['username' => 'user123142352@gmail.com',
            'password' => 'password'];

        $client->request('POST', '/api/v1/auth', ['json' => $badBody]);
        self::assertJsonEquals(['code' => '401', 'message' => 'Invalid credentials.']);

        $json = $client->request('POST', '/api/v1/auth', ['json' => $goodBody])->getContent();
        self::assertJson($json);
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('token', $array);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    public function testCurrentUsers(): void
    {
        $client = static::createClient();

        $goodBody = ['username' => 'user@gmail.com',
            'password' => 'password'];

        $json = $client->request('POST', '/api/v1/auth', ['json' => $goodBody])->getContent();
        self::assertJson($json);
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('token', $array);
        $token = $array['token'];

        $goodClient = static::createClient([], ['headers' => ['authorization' => ('Bearer '.$token)]]);

        $json = $goodClient->request('GET', '/api/v1/users/current')->getContent();
        self::assertJson($json);
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('username', $array);
        self::assertArrayHasKey('balance', $array);
        self::assertArrayHasKey('roles', $array);
        self::assertEquals('user@gmail.com', $array['username']);

        $badClient = static::createClient([], ['headers' => ['authorization' => ('Bearer '.$token.'123')]]);
        $badClient->request('GET', '/api/v1/users/current');
        self::assertJsonEquals(['code' => '401', 'message' => 'Invalid JWT Token']);
    }
}
