<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Repository\CourseRepository;
use App\Service\PaymentService;
use DateInterval;
use DateTime;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Nelmio\ApiDocBundle\Annotation\Security;
use phpDocumentor\Reflection\Type;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

#[Route('/api/v1')]
class CourseApiController extends AbstractController
{

    private EntityManagerInterface $em;
    private CourseRepository $courseRepository;
    private PaymentService $paymentService;

    public function __construct(
        EntityManagerInterface $em,
        CourseRepository $courseRepository,
        PaymentService $paymentService
    ) {
        $this->em = $em;
        $this->courseRepository = $courseRepository;
        $this->paymentService = $paymentService;
    }

    #[Route('/courses', name: 'api_courses', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/courses',
        description: "Выходные данные - список курсов.",
        summary: "Получение всех курсов"
    )]

    #[OA\Response(
        response: 200,
        description: 'Успешное получение курсов',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(properties: [
                new OA\Property(
                    property: ' ',
                    properties: [
                        new OA\Property(property: 'code', type: 'string', example: 'landshaftnoe-proektirovanie'),
                        new OA\Property(property: 'type', type: 'string', example: 'rent'),
                        new OA\Property(property: 'price', type: 'float', example: '99.90')],
                    type: 'object'
                ),
                new OA\Property(
                    properties: [
                        new OA\Property(property: 'code', type: 'string', example: 'barber-muzhskoy-parikmaher'),
                        new OA\Property(property: 'type', type: 'string', example: 'free')],
                    type: 'object'
                ),
            ])
        )
    )]

    #[OA\Response(
        response: 500,
        description: 'Неизвестная ошибка',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string'),
                new OA\Property(property: 'message', type: 'string')
            ],
            type: 'object'
        )
    )]


    #[OA\Tag(
        name: "Course"
    )]
    public function getCourses(): JsonResponse
    {
        $courses = $this->courseRepository->findAll();

        $json = array();

        foreach ($courses as $course) {
            $jsonCourse['code'] = $course->getCode();
            $jsonCourse['type'] = $course->getStringType();
            if ($course->getPrice() !== null) {
                $jsonCourse['price'] = $course->getPrice();
            }
            $json[] = $jsonCourse;
        }
        return new JsonResponse(
            $json,
            Response::HTTP_OK
        );
    }

    #[Route('/courses/{code}', name: 'api_course', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/courses/{code}',
        description: "Входные данные - код курса
        \nВыходные данные - код, тип и цена курса.",
        summary: "Получение курса"
    )]

    #[OA\Response(
        response: 201,
        description: 'Успешное получение курса',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'landshaftnoe-proektirovanie'),
                new OA\Property(property: 'type', type: 'string', example: 'rent'),
                new OA\Property(property: 'price', type: 'float', example: '99.90'),
                ],
            type: 'object'
        )
    )]

    #[OA\Response(
        response: 401,
        description: 'Курс не найден',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string'),
                new OA\Property(property: 'message', type: 'string', example: 'Не найден курс с данным кодом.')
            ],
            type: 'object'
        )
    )]

    #[OA\Response(
        response: 500,
        description: 'Неизвестная ошибка',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string'),
                new OA\Property(property: 'message', type: 'string')
            ],
            type: 'object'
        )
    )]


    #[OA\Tag(
        name: "Course"
    )]
    public function getCourse(string $code): JsonResponse
    {
        $course = $this->courseRepository->findOneBy(['code' => $code]);
        if ($course === null) {
            return new JsonResponse([
                'code' => 401,
                'message' => 'Не найден курс с данным кодом.'
            ], Response::HTTP_UNAUTHORIZED);
        }
        $jsonCourse['code'] = $course->getCode();
        $jsonCourse['type'] = $course->getStringType();
        if ($course->getPrice() !== null) {
            $jsonCourse['price'] = $course->getPrice();
        }
        return new JsonResponse(
            $jsonCourse,
            Response::HTTP_OK
        );
    }

    /**
     * @throws PaymentServiceException
     * @throws Exception
     * @throws ORMException
     */
    #[Route('/courses/{code}/pay', name: 'api_course_pay', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/courses/{code}/pay',
        description: "Входные данные - JWT-токен в header и код курса в URI.
        \nВыходные данные - JSON с с типом курса и датой истечения аренды в случае успеха, 
        JSON с ошибками в случае возникновения ошибок",
        summary: "Оплата курса"
    )]
    #[OA\Response(
        response: 201,
        description: 'Удачная оплата',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'bool', example: 'true'),
                new OA\Property(property: 'course_type', type: 'string', example: 'rent'),
                new OA\Property(property: 'expires_at', type: 'string', example: '2019-05-20T13:46:07+00:00')
            ],
            type: 'object'
        )
    )]

    #[OA\Response(
        response: 401,
        description: 'Ошибка в входных данных',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'int', example: 401),
                new OA\Property(property: 'message', type: 'string', example: 'Требуется токен авторизации!')
            ],
            type: 'object'
        )
    )]

    #[OA\Response(
        response: 406,
        description: 'На счету пользователя недостаточно средств',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'int', example: 406),
                new OA\Property(property: 'message', type: 'string', example: 'На вашем счету недостаточно средств.')
            ],
            type: 'object'
        )
    )]

    #[OA\Response(
        response: 500,
        description: 'Неизвестная ошибка',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string'),
                new OA\Property(property: 'message', type: 'string')
            ],
            type: 'object'
        )
    )]


    #[OA\Tag(
        name: "Course"
    )]
    #[Security(name: "Bearer")]
    public function payCourse(string $code): JsonResponse
    {
        $user = $this->getUser();
        if ($user === null) {
            return new JsonResponse([
                'code' => 401,
                'message' => 'Требуется токен авторизации!'
            ], Response::HTTP_UNAUTHORIZED);
        }
        $course = $this->courseRepository->findOneBy(['code' => $code]);
        if ($course === null) {
            return new JsonResponse([
            'code' => 401,
            'message' => 'Не найден курс с данным кодом.'
            ], Response::HTTP_OK);
        }
        if ($course->getType() !== 0 && $user->getBalance() < $course->getPrice()) {
            return new JsonResponse([
                'code' => 406,
                'message' => 'На вашем счету недостаточно средств.'
            ], Response::HTTP_OK);
        }
        $json = $this->paymentService->payment($user, $course);

        return new JsonResponse($json, Response::HTTP_OK);
    }
}
