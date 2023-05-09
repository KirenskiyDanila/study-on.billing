<?php

namespace App\Service;

use App\DTO\CourseDto;
use App\Entity\Course;
use App\Entity\User;
use App\Repository\CourseRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ControllerValidator
{
    private CourseRepository $courseRepository;
    public function __construct(CourseRepository $courseRepository)
    {
        $this->courseRepository = $courseRepository;
    }

    public function validatePayCourse(User $user = null, Course $course = null): ?JsonResponse
    {
        if ($user === null) {
            return new JsonResponse([
                'code' => 401,
                'message' => 'Требуется токен авторизации!'
            ], Response::HTTP_UNAUTHORIZED);
        }
        if ($course === null) {
            return new JsonResponse([
                'code' => 401,
                'message' => 'Не найден курс с данным кодом.'
            ], Response::HTTP_OK);
        }
        if ($course->getStringType() === 'free') {
            return new JsonResponse([
                'code' => 406,
                'message' => 'Данный курс бесплатный.'
            ], Response::HTTP_OK);
        }
        if ($course->getType() !== 0 && $user->getBalance() < $course->getPrice()) {
            return new JsonResponse([
                'code' => 406,
                'message' => 'На вашем счету недостаточно средств.'
            ], Response::HTTP_OK);
        }
        return null;
    }

    public function validateDto(ConstraintViolationListInterface $errors): ?JsonResponse
    {
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
        return null;
    }

    public function validateCodeUnique(Course $course = null): ?JsonResponse
    {
        if ($course !== null) {
            return new JsonResponse([
                'code' => 401,
                'errors' => [
                    "unique" => "Курс с таким кодом уже существует!"
                ]
            ], Response::HTTP_UNAUTHORIZED);
        }
        return null;
    }

    public function validateRegistrationUnique(User $user = null): ?JsonResponse
    {
        if ($user !== null) {
            return new JsonResponse([
                'code' => 401,
                'errors' => [
                    "unique" => "Пользователь с такой электронной почтой уже существует!"
                ]
            ], Response::HTTP_UNAUTHORIZED);
        }
        return null;
    }

    public function validateGetCourse(Course $course = null): ?JsonResponse
    {
        if ($course === null) {
            return new JsonResponse([
                'code' => 401,
                'message' => 'Не найден курс с данным кодом.'
            ], Response::HTTP_UNAUTHORIZED);
        }
        return null;
    }

    public function validateGetCurrentUser(User $user = null): ?JsonResponse
    {
        if ($user === null) {
            return new JsonResponse([
                'code' => 401,
                "message" => "JWT Token не найден"
            ], Response::HTTP_OK);
        }
        return null;
    }

    public function validateGetTransactions(User $user = null): ?JsonResponse
    {
        if ($user === null) {
            return new JsonResponse([
                'code' => 401,
                'message' => 'Требуется токен авторизации!'
            ], Response::HTTP_UNAUTHORIZED);
        }
        return null;
    }

    public function validateCoursePrice(string $type = null, float $price = null): ?JsonResponse
    {
        if (($type === 'rent' || $type === 'buy') && $price === null) {
            return new JsonResponse([
                'code' => 401,
                'message' => 'Измените курсу тип или добавьте цену!'
            ], Response::HTTP_UNAUTHORIZED);
        }
        return null;
    }

    public function validateAdmin(User $user = null): ?JsonResponse
    {
        if ($user === null) {
            return new JsonResponse([
                'code' => 401,
                'message' => 'Требуется токен авторизации!'
            ], Response::HTTP_UNAUTHORIZED);
        }
        if (!in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return new JsonResponse([
                'code' => 401,
                'message' => 'У вас недостаточно прав для проведения данной операции!'
            ], Response::HTTP_UNAUTHORIZED);
        }
        return null;
    }

    public function validateCourse(
        ConstraintViolationListInterface $errors,
        CourseDto $courseDto,
        Course $course = null
    ): ?JsonResponse {
        $dataErrorResponse = $this->validateDto($errors);
        if ($dataErrorResponse !== null) {
            return $dataErrorResponse;
        }
        $priceErrorResponse = $this->validateCoursePrice($courseDto->type, $courseDto->price);
        if ($priceErrorResponse !== null) {
            return $priceErrorResponse;
        }
        if ($course !== null) {
            if ($course->getCode() === $courseDto->code) {
                return null;
            }
        }
        $duplicateCourse = $this->courseRepository->findOneBy(['code' => $courseDto->code]);
        $uniqueErrorResponse = $this->validateCodeUnique($duplicateCourse);
        return $uniqueErrorResponse ?? null;
    }
}
