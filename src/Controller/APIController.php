<?php

namespace App\Controller;

use App\DTO\UserDto;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
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
class APIController extends AbstractController
{

    #[Route('/auth', name: 'api_auth', methods: ['POST'])]
    #[OA\Post (
        path: '/api/v1/auth',
        description: "Входные данные - email и пароль.\nВыходные данные - JSON с JWT-токеном в случае успеха, JSON с ошибками в случае возникновения ошибок",
        summary: "Аутентификация пользователя"
    )]

    #[OA\RequestBody (
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
                new OA\Property(property: 'token', type: 'string')
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

    #[OA\Parameter (
        name: 'body',
        description: 'JSON Payload',
        in: 'query',
    )]

    #[OA\Tag(
        name: "User"
    )]

    public function auth() : void
    {

    }


    #[OA\Post (
        path: '/api/v1/register',
        description: "Входные данные - email и пароль.\nВыходные данные - JSON с JWT-токеном, роли пользователя и его баланс в случае успеха, JSON с ошибками в случае возникновения ошибок",
        summary: "Регистрация пользователя"
    )]

    #[OA\RequestBody (
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
                new OA\Property(property: 'ROLES', type: 'array', items: new OA\Items(type: "string"))
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
                new OA\Property(property: 'errors', type: 'array',
                    items: new OA\Items(type: "string"))
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

    #[OA\Parameter (
        name: 'body',
        description: 'JSON Payload',
        in: 'query',
    )]

    #[OA\Tag(
        name: "User"
    )]
    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request, ValidatorInterface $validator, ManagerRegistry $doctrine, JWTTokenManagerInterface $tokenManager): JsonResponse
    {
        $serializer = SerializerBuilder::create()->build();
        $userDto = $serializer->deserialize($request->getContent(), UserDto::class, 'json');
        $errors = $validator->validate($userDto);
        $em = $doctrine->getManager();
        if ( $userDto->username === null || $userDto->password === null) {
            return new JsonResponse([
                'code' => 401,
                'errors' => [
                    "JSON" => "Неверный JSON-формат данных!"
                ]
            ], Response::HTTP_UNAUTHORIZED);
        }
        $user = $em->getRepository(User::class)->findOneBy(['email' => $userDto->username]);
        if ($user !== null) {
            return new JsonResponse([
                'code' => 401,
                'errors' => [
                    "unique" => "Пользователь с такой электронной почтой уже существует!"
                ]
            ], Response::HTTP_UNAUTHORIZED);
        }
        if (count($errors) > 0) {
            $jsonErrors = [];
            foreach ($errors as $error) {
                $jsonErrors[$error->getPropertyPath()] = $error->getMessage();
            }
            return new JsonResponse([
                'code' => 401,
                'errors' => $jsonErrors
                ], Response::HTTP_UNAUTHORIZED);
        }
        $user = User::formDTO($userDto);
        $em->persist($user);
        $em->flush();
        $token = $tokenManager->create($user);
        return new JsonResponse([

            'token' => $token,
            'ROLES' => $user->getRoles(),
            'balance' => $user->getBalance()
        ], Response::HTTP_CREATED);
    }

    #[Route('/users/current', name: 'api_current', methods: ['GET'])]
    #[OA\Get (
        path: '/api/v1/users/current',
        description: "Входные данные - JWT-токен.\nВыходные данные - электронная почта, роли пользователя и его баланс в случае успеха, JSON с ошибками в случае возникновения ошибок",
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

    #[OA\Parameter (
        name: 'body',
        description: 'JSON Payload',
        in: 'query',
    )]

    #[OA\Tag(
        name: "User"
    )]
    #[Security(name: "Bearer")]
    public function currentUser(): JsonResponse
    {
        return new JsonResponse([
            'code' => 200,
            'username' => $this->getUser()->getEmail(),
            'roles' => $this->getUser()->getRoles(),
            'balance' => $this->getUser()->getBalance(),
        ], Response::HTTP_OK);
    }
}
