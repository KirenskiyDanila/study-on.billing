<?php

namespace App\Controller;

use App\DTO\UserDto;
use App\Entity\User;
use App\Service\ControllerValidator;
use App\Service\PaymentService;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use JMS\Serializer\SerializerBuilder;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes\JsonContent;
use phpDocumentor\Reflection\DocBlock\Description;
use phpDocumentor\Reflection\Type;
use phpDocumentor\Reflection\Types\Array_;
use phpDocumentor\Reflection\Types\Object_;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;
use Nelmio\ApiDocBundle\Annotation\Model;

#[Route('/api/v1')]
class UserApiController extends AbstractController
{

    private ValidatorInterface $validator;
    private ManagerRegistry $doctrine;
    private JWTTokenManagerInterface $tokenManager;
    private RefreshTokenGeneratorInterface $refreshTokenGenerator;
    private RefreshTokenManagerInterface $refreshTokenManager;
    private PaymentService $paymentService;
    private ControllerValidator $controllerValidator;
    public function __construct(
        ValidatorInterface $validator,
        ManagerRegistry $doctrine,
        JWTTokenManagerInterface $tokenManager,
        RefreshTokenGeneratorInterface $refreshTokenGenerator,
        RefreshTokenManagerInterface $refreshTokenManager,
        PaymentService $paymentService,
        ControllerValidator $controllerValidator
    ) {
        $this->validator = $validator;
        $this->doctrine = $doctrine;
        $this->tokenManager = $tokenManager;
        $this->refreshTokenGenerator = $refreshTokenGenerator;
        $this->refreshTokenManager = $refreshTokenManager;
        $this->paymentService = $paymentService;
        $this->controllerValidator = $controllerValidator;
    }

    #[Route('/auth', name: 'api_auth', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/auth',
        description: "Входные данные - email и пароль.
        \nВыходные данные - JSON с JWT-токеном и refresh-токеном в случае успеха, 
        JSON с ошибками в случае возникновения ошибок",
        summary: "Аутентификация пользователя"
    )]

    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'username', type: 'string'),
                new OA\Property(property: 'password', type: 'string')
            ],
            type: 'object'
        )
    )]

    #[OA\Response(
        response: 201,
        description: 'Удачная аутентификация',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string'),
                new OA\Property(property: 'refresh_token', type: 'string')
            ],
            type: 'object'
        )
    )]

    #[OA\Response(
        response: 401,
        description: 'Неправильные данные',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 401),
                new OA\Property(property: 'message', type: 'string', example: 'Invalid credentials.')
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
        name: "User"
    )]

    public function auth() : void
    {
    }

    /**
     * @throws Exception
     */
    #[OA\Post(
        path: '/api/v1/register',
        description: "Входные данные - email и пароль.
        \nВыходные данные - JSON с JWT-токеном, refresh-токеном,
         роли пользователя и его баланс в случае успеха, JSON с ошибками в случае возникновения ошибок",
        summary: "Регистрация пользователя"
    )]

    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'username', type: 'string'),
                new OA\Property(property: 'password', type: 'string')
            ],
            type: 'object'
        )
    )]

    #[OA\Response(
        response: 201,
        description: 'Удачная регистрация',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string'),
                new OA\Property(property: 'ROLES', type: 'array', items: new OA\Items(type: "string")),
                new OA\Property(property: 'balance', type: 'integer', example: 0),
                new OA\Property(property: 'refresh_token', type: 'string')
            ],
            type: 'object'
        )
    )]

    #[OA\Response(
        response: 401,
        description: 'Регистрация с неправильными данными',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 401),
                new OA\Property(
                    property: 'errors',
                    type: 'array',
                    items: new OA\Items(type: "string")
                )
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
        name: "User"
    )]
    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $serializer = SerializerBuilder::create()->build();
        $userDto = $serializer->deserialize($request->getContent(), UserDto::class, 'json');
        $errors = $this->validator->validate($userDto);
        $dataErrorResponse = $this->controllerValidator->validateDto($errors);
        if ($dataErrorResponse !== null) {
            return $dataErrorResponse;
        }
        $em = $this->doctrine->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => $userDto->username]);
        $uniqueErrorResponse = $this->controllerValidator->validateRegistrationUnique($user);
        if ($uniqueErrorResponse !== null) {
            return $uniqueErrorResponse;
        }
        $user = User::formDTO($userDto);
        $this->paymentService->deposit($user, $_ENV['CLIENT_MONEY']);
        $em->persist($user);
        $em->flush();
        $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl(
            $user,
            (new \DateTime())->modify('+1 month')->getTimestamp()
        );
        $this->refreshTokenManager->save($refreshToken);
        $token = $this->tokenManager->create($user);
        return new JsonResponse([

            'token' => $token,
            'ROLES' => $user->getRoles(),
            'balance' => $user->getBalance(),
            'refresh_token' => $refreshToken->getRefreshToken()
        ], Response::HTTP_CREATED);
    }

    #[Route('/token/refresh', name: 'api_refresh', methods: ['POST'])]

    #[OA\Post(
        path: '/api/v1/token/refresh',
        description: "Входные данные - refresh-токен.
        \nВыходные данные - JSON с JWT-токеном и refresh-токеном в случае успеха, 
        JSON с ошибками в случае возникновения ошибок",
        summary: "Обновление JWT-токена"
    )]

    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'refresh_token', type: 'string'),
            ],
            type: 'object'
        )
    )]

    #[OA\Response(
        response: 201,
        description: 'Успешное получение токена.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string'),
                new OA\Property(property: 'refresh_token', type: 'string')
            ],
            type: 'object'
        )
    )]

    #[OA\Response(
        response: 401,
        description: 'Ошибка в refresh-токене.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 401),
                new OA\Property(property: 'message', type: 'string', example: 'JWT Refresh Token Not Found')
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
        name: "User"
    )]

    public function refresh(): void
    {
    }

    #[Route('/users/current', name: 'api_current', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/users/current',
        description: "Входные данные - JWT-токен.
        \nВыходные данные - электронная почта, роли пользователя и его баланс в случае успеха, 
        JSON с ошибками в случае возникновения ошибок",
        summary: "Получение текущего пользователя"
    )]

    #[OA\Response(
        response: 201,
        description: 'Успешное получение пользователя',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 200),
                new OA\Property(property: 'username', type: 'string'),
                new OA\Property(property: 'ROLES', type: 'array', items: new OA\Items(type: "string")),
                new OA\Property(property: 'balance', type: 'integer', example: 0)
            ],
            type: 'object'
        )
    )]

    #[OA\Response(
        response: 401,
        description: 'Ввод неправильного JWT-токена',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 401),
                new OA\Property(property: 'errors', type: 'string', example: "Invalid JWT Token")
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
        name: "User"
    )]
    #[Security(name: "Bearer")]
    public function getCurrentUser(): JsonResponse
    {
        $errorResponse = $this->controllerValidator->validateGetCurrentUser($this->getUser());
        if ($errorResponse !== null) {
            return $errorResponse;
        }

        return new JsonResponse([
            'code' => 200,
            'username' => $this->getUser()->getEmail(),
            'roles' => $this->getUser()->getRoles(),
            'balance' => $this->getUser()->getBalance(),
        ], Response::HTTP_OK);
    }
}
