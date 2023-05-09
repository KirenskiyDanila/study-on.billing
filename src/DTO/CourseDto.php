<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;

class CourseDto
{
    #[Serializer\Type('string')]
    #[Assert\NotBlank(message: 'Поле не должно быть пустым!')]
    public ?string $code = null;

    #[Serializer\Type('string')]
    #[Assert\Choice(choices: ['buy', 'rent', 'free'], message: 'Выберите существующий тип оплаты!')]
    public ?string $type = null;
    #[Serializer\Type('float')]
    #[Serializer\SkipWhenEmpty]
    #[Assert\PositiveOrZero(message: 'Курс не может стоить меньше 0!')]
    public ?float $price = null;

    #[Serializer\Type('string')]
    #[Assert\NotBlank(message: 'Поле не должно быть пустым!')]
    #[Assert\Length(min: 3, max: 255, minMessage: 'Название должно иметь минимум 3 символа!')]
    public ?string $title = null;
}
