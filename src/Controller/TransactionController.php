<?php

namespace App\Controller;

use App\Repository\CourseRepository;
use App\Repository\TransactionRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/v1')]
class TransactionController extends AbstractController
{

    private TranslatorInterface $translator;
    private TransactionRepository $transactionRepository;
    public function __construct(TransactionRepository $transactionRepository, TranslatorInterface $translator)
    {
        $this->transactionRepository = $transactionRepository;
        $this->translator = $translator;
    }

    #[Route('/transactions', name: 'api_transactions', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/transactions',
        description: "Входные данные - поисковые фильтры.
        \nВыходные данные - список транзакций пользователя с примененными фильтрами.",
        summary: "Получение транзакций"
    )]

    #[OA\Response(
        response: 200,
        description: 'Успешное получение транзакций',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(properties: [
                new OA\Property(
                    property: ' ',
                    properties: [
                        new OA\Property(property: 'id', type: 'int', example: 11),
                        new OA\Property(property: 'created_at', type: 'string', example: '2019-05-20T13:46:07+00:00'),
                        new OA\Property(property: 'type', type: 'string', example: 'payment'),
                        new OA\Property(property: 'course_code', type: 'string', example: 'course-1'),
                        new OA\Property(property: 'amount', type: 'float', example: '159.90')],
                    type: 'object'
                ),
                new OA\Property(
                    properties: [
                        new OA\Property(property: 'id', type: 'int', example: 9),
                        new OA\Property(property: 'created_at', type: 'string', example: '2019-05-20T13:45:11+00:00'),
                        new OA\Property(property: 'type', type: 'string', example: 'deposit'),
                        new OA\Property(property: 'amount', type: 'float', example: '5000.00')],
                    type: 'object'
                ),
            ])
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

    #[OA\Parameter(
        name: 'type',
        description: 'Тип транзакции',
        in: 'query',
    )]

    #[OA\Parameter(
        name: 'course_code',
        description: 'Код курса',
        in: 'query',
    )]

    #[OA\Parameter(
        name: 'skip_expired',
        description: 'Флаг, позволяющий убрать транзакции, аренды которых уже истекли',
        in: 'query',
    )]

    #[OA\Tag(
        name: "Transaction"
    )]
    #[Security(name: "Bearer")]
    public function getTransactions(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if ($user === null) {
            return new JsonResponse([
                'code' => 401,
                'message' => $this->translator->trans(
                    'authTokenError',
                    [],
                    'messages'
                )
            ], Response::HTTP_UNAUTHORIZED);
        }
        if ($request->query->get("type", null) === 'payment') {
            $type = 0;
        } elseif ($request->query->get("type", null) === 'deposit') {
            $type = 1;
        } else {
            $type = null;
        }
        $courseCode = $request->query->get("course_code", null);
        $skipExpired = (bool)$request->query->get("skip_expired", null);

        $transactions = $this->transactionRepository->findWithFilter($user, $type, $courseCode, $skipExpired);

        $json = array();

        foreach ($transactions as $transaction) {
            $jsonTransaction = [];
            $jsonTransaction['id'] = $transaction->getId();
            $jsonTransaction['created_at'] = date_format($transaction->getCreatedAt(), "Y-m-dTH:i:s");
            $jsonTransaction['type'] = $transaction->getStringType();
            if ($transaction->getStringType() !== 'deposit') {
                $jsonTransaction['course_code'] = $transaction->getCourse()->getCode();
                if ($transaction->getExpiresAt() !== null) {
                    $jsonTransaction['expires_at'] = date_format($transaction->getExpiresAt(), "Y-m-dTH:i:s");
                }
            }
            $jsonTransaction['amount'] = $transaction->getAmount();
            $json[] = $jsonTransaction;
        }
        return new JsonResponse(
            $json,
            Response::HTTP_OK
        );
    }
}
