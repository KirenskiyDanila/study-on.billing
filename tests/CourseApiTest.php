<?php
namespace App\Tests;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Service\PaymentService;
use Doctrine\ORM\Exception\NotSupported;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CourseApiTest extends AbstractTest
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
    public function testGetCourses(): void
    {
        $client = self::$client;
        $client->request(
            'GET',
            '/api/v1/courses',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json',]
        );
        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $courses = self::getEntityManager()->getRepository(Course::class)->findAll();

        self::assertCount(count($array), $courses);

        foreach ($courses as $course) {
            $isFound = false;
            foreach ($array as $item) {
                if ($item['code'] === $course->getCode()) {
                    $isFound = true;
                    break;
                }
            }
            self::assertTrue($isFound);
        }
    }

    /**
     * @throws NotSupported
     * @throws \JsonException
     * @throws \Exception
     */
    public function testCourse(): void
    {
        $client = self::$client;
        $courses = self::getEntityManager()->getRepository(Course::class)->findAll();

        foreach ($courses as $course) {
            $client->request(
                'GET',
                '/api/v1/courses/' . $course->getCode(),
                [],
                [],
                ['CONTENT_TYPE' => 'application/json',]
            );
            $json = $client->getResponse()->getContent();
            $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            self::assertSame($course->getCode(), $array['code']);
            self::assertSame($course->getStringType(), $array['type']);
            if ($array['type'] === 'rent' || $array['type'] === 'buy') {
                self::assertSame($course->getPrice(), $array['price']);
            }
        }

        $client->request(
            'GET',
            '/api/v1/courses/' . 'fake_code',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json',]
        );
        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(401, $array['code']);
        self::assertSame('Не найден курс с данным кодом.', $array['message']);
    }

    /**
     * @throws \JsonException
     */
    public function getUserToken($client): string
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
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR)['token'];
    }

    public function getAdminToken($client): string
    {
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
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR)['token'];
    }

    /**
     * @throws NotSupported
     * @throws \JsonException
     * @throws \Exception
     */
    public function testPayCourse(): void
    {
        $client = self::$client;
        $courses = self::getEntityManager()->getRepository(Course::class)->findAll();

        $token = $this->getUserToken($client);
        $user = self::getEntityManager()->getRepository(User::class)->findOneBy(['email' => 'user@gmail.com']);
        $balance = $user->getBalance();

        $boughtCourseCode = '';

        foreach ($courses as $course) {
            $client->request(
                'POST',
                '/api/v1/courses/' . $course->getCode() . '/pay',
                [],
                [],
                ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json']
            );
            $json = $client->getResponse()->getContent();
            $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if ($course->getStringType() === 'free') {
                self::assertSame(406, $array['code']);
                self::assertSame('Данный курс бесплатный.', $array['message']);
            } else {
                $price = $course->getPrice();
                if ($balance >= $price) {
                    self::assertTrue($array['success']);
                    self::assertSame($course->getStringType(), $array['course_type']);
                    if ($course->getStringType() === 'rent') {
                        self::assertArrayHasKey('expires_at', $array);
                    } else {
                        self::assertArrayNotHasKey('expires_at', $array);
                    }
                    $user = self::getEntityManager()
                        ->getRepository(User::class)
                        ->findOneBy(['email' => 'user@gmail.com']);
                    $newBalance = $user->getBalance();
                    self::assertSame($balance - $price, $newBalance);
                    $balance = $newBalance;
                    $token = $this->getUserToken($client);
                    $boughtCourseCode = $course->getCode();
                } else {
                    self::assertSame(406, $array['code']);
                    self::assertSame('На вашем счету недостаточно средств.', $array['message']);
                }
            }
        }

        $client->request(
            'POST',
            '/api/v1/courses/' . $boughtCourseCode . '/pay',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json']
        );
        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(406, $array['code']);
        self::assertSame('Вы уже владете доступом к данному курсу.', $array['message']);
    }

    /**
     * @throws \JsonException
     */
    public function testAddCourse(): void
    {
        $client = self::$client;

        $token = $this->getUserToken($client);

        $emptyBody = [
            'co1d2e' => 'code',
            'ti1t1le' => 'title',
            'type1231' => 'type',
            'price123' => 1500
        ];

        $badDtoBody = [
            'code' => 'code',
            'title' => 't9',
            'type' => 'type',
            'price' => -1500
        ];

        $bodyWithoutPrice = [
            'code' => 'code',
            'title' => 't912',
            'type' => 'buy'
        ];

        $duplicateCodeBody = [
            'code' => 'course-1',
            'title' => 't912',
            'type' => 'buy',
            'price' => 1000
        ];

        $goodBody = [
            'code' => 'code',
            'title' => 't99-12',
            'type' => 'buy',
            'price' => 1500
        ];

        $goodBodyWithoutPrice = [
            'code' => 'codeTEST',
            'title' => 't99-12',
            'type' => 'free'
        ];

        $client->request(
            'POST',
            '/api/v1/courses/',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode($goodBody, JSON_THROW_ON_ERROR)
        );
        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(401, $array['code']);
        self::assertSame('У вас недостаточно прав для проведения данной операции!', $array['message']);

        $token = $this->getAdminToken($client);


        $client->request(
            'POST',
            '/api/v1/courses/',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode($emptyBody, JSON_THROW_ON_ERROR)
        );
        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(401, $array['code']);
        self::assertSame('Поле не должно быть пустым!', $array['errors']['code']);
        self::assertSame('Поле не должно быть пустым!', $array['errors']['title']);
        self::assertSame('Поле не должно быть пустым!', $array['errors']['type']);


        $client->request(
            'POST',
            '/api/v1/courses/',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode($badDtoBody, JSON_THROW_ON_ERROR)
        );
        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(401, $array['code']);
        self::assertSame('Курс не может стоить меньше 0!', $array['errors']['price']);
        self::assertSame('Название должно иметь минимум 3 символа!', $array['errors']['title']);
        self::assertSame('Выберите существующий тип оплаты!', $array['errors']['type']);

        $client->request(
            'POST',
            '/api/v1/courses/',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode($bodyWithoutPrice, JSON_THROW_ON_ERROR)
        );
        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(401, $array['code']);
        self::assertSame('Измените курсу тип или добавьте цену!', $array['message']);

        $client->request(
            'POST',
            '/api/v1/courses/',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode($duplicateCodeBody, JSON_THROW_ON_ERROR)
        );
        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(401, $array['code']);
        self::assertSame('Курс с таким кодом уже существует!', $array['errors']['unique']);

        $client->request(
            'POST',
            '/api/v1/courses/',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode($goodBody, JSON_THROW_ON_ERROR)
        );
        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($array['success']);

        $client->request(
            'POST',
            '/api/v1/courses/',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode($goodBodyWithoutPrice, JSON_THROW_ON_ERROR)
        );
        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($array['success']);
    }


    /**
     * @throws \JsonException
     * @throws \Exception
     */
    public function testEditCourse(): void
    {
        $client = self::$client;

        $token = $this->getUserToken($client);

        $emptyBody = [
            'co1d2e' => 'code',
            'ti1t1le' => 'title',
            'type1231' => 'type',
            'price123' => 1500
        ];

        $badDtoBody = [
            'code' => 'code',
            'title' => 't9',
            'type' => 'type',
            'price' => -1500
        ];

        $bodyWithoutPrice = [
            'code' => 'code',
            'title' => 't912',
            'type' => 'buy'
        ];

        $duplicateCodeBody = [
            'code' => 'course-1',
            'title' => 't912',
            'type' => 'buy',
            'price' => 1000
        ];

        $goodBody = [
            'code' => 'code',
            'title' => 't99-12',
            'type' => 'buy',
            'price' => 1500
        ];

        $goodBodyWithoutPrice = [
            'code' => 'codeTEST',
            'title' => 't99-112',
            'type' => 'free'
        ];

        $course = self::getEntityManager()->getRepository(Course::class)->findOneBy(['code' => 'code']);
        self::assertNull($course);
        $course = self::getEntityManager()->getRepository(Course::class)->findOneBy(['code' => 'codeTEST']);
        self::assertNull($course);

        $course = self::getEntityManager()->getRepository(Course::class)->findOneBy(['code' => 'course-2']);

        $oldCourse = $course;

        self::assertNotSame('code', $course->getCode());
        self::assertNotSame('t99-12', $course->getTitle());
        self::assertNotSame('1500', $course->getPrice());

        $client->request(
            'POST',
            '/api/v1/courses/' . $course->getCode(),
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode($goodBody, JSON_THROW_ON_ERROR)
        );
        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(401, $array['code']);
        self::assertSame('У вас недостаточно прав для проведения данной операции!', $array['message']);

        $token = $this->getAdminToken($client);

        $client->request(
            'POST',
            '/api/v1/courses/fake-code',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode($goodBody, JSON_THROW_ON_ERROR)
        );
        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(401, $array['code']);
        self::assertSame('Не найден курс с данным кодом.', $array['message']);


        $client->request(
            'POST',
            '/api/v1/courses/'  . $course->getCode(),
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode($emptyBody, JSON_THROW_ON_ERROR)
        );
        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(401, $array['code']);
        self::assertSame('Поле не должно быть пустым!', $array['errors']['code']);
        self::assertSame('Поле не должно быть пустым!', $array['errors']['title']);
        self::assertSame('Поле не должно быть пустым!', $array['errors']['type']);


        $client->request(
            'POST',
            '/api/v1/courses/'  . $course->getCode(),
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode($badDtoBody, JSON_THROW_ON_ERROR)
        );
        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(401, $array['code']);
        self::assertSame('Курс не может стоить меньше 0!', $array['errors']['price']);
        self::assertSame('Название должно иметь минимум 3 символа!', $array['errors']['title']);
        self::assertSame('Выберите существующий тип оплаты!', $array['errors']['type']);

        $client->request(
            'POST',
            '/api/v1/courses/' . $course->getCode(),
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode($bodyWithoutPrice, JSON_THROW_ON_ERROR)
        );
        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(401, $array['code']);
        self::assertSame('Измените курсу тип или добавьте цену!', $array['message']);

        $client->request(
            'POST',
            '/api/v1/courses/' . $course->getCode(),
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode($duplicateCodeBody, JSON_THROW_ON_ERROR)
        );
        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(401, $array['code']);
        self::assertSame('Курс с таким кодом уже существует!', $array['errors']['unique']);

        $client->request(
            'POST',
            '/api/v1/courses/' . $course->getCode(),
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode($goodBody, JSON_THROW_ON_ERROR)
        );
        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($array['success']);

        $course = self::getEntityManager()->getRepository(Course::class)->findOneBy(['code' => 'course-2']);

        self::assertNull($course);

        $course = self::getEntityManager()->getRepository(Course::class)->findOneBy(['code' => 'code']);

        self::assertNotSame('code', $oldCourse->getCode());
        self::assertNotSame('t99-12', $oldCourse->getTitle());
        self::assertNotSame('1500', $oldCourse->getPrice());

        self::assertSame('code', $course->getCode());
        self::assertSame('t99-12', $course->getTitle());
        self::assertEquals(1500.0, $course->getPrice());

        $oldCourse = $course;

        $client->request(
            'POST',
            '/api/v1/courses/' . $course->getCode(),
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode($goodBodyWithoutPrice, JSON_THROW_ON_ERROR)
        );
        $json = $client->getResponse()->getContent();
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($array['success']);

        $course = self::getEntityManager()->getRepository(Course::class)->findOneBy(['code' => 'code']);

        self::assertNull($course);

        $course = self::getEntityManager()->getRepository(Course::class)->findOneBy(['code' => 'codeTEST']);

        self::assertNotSame('codeTEST', $oldCourse->getCode());
        self::assertNotSame('t99-112', $oldCourse->getTitle());
        self::assertNotSame('free', $oldCourse->getStringType());

        self::assertSame('codeTEST', $course->getCode());
        self::assertSame('t99-112', $course->getTitle());
        self::assertSame('free', $course->getStringType());
    }
}
