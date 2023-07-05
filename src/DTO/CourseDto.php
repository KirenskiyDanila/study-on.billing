<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;

class CourseDto
{
    #[Serializer\Type('string')]
    #[Assert\NotBlank(message: 'dto.course.code.blank')]
    public ?string $code = null;

    #[Serializer\Type('string')]
    #[Assert\NotBlank(message: 'dto.course.type.blank')]
    #[Assert\Choice(choices: ['buy', 'rent', 'free'], message: 'dto.course.type.choice')]
    public ?string $type = null;
    #[Serializer\Type('float')]
    #[Serializer\SkipWhenEmpty]
    #[Assert\PositiveOrZero(message: 'dto.course.price.positiveOrZero')]
    public ?float $price = null;

    #[Serializer\Type('string')]
    #[Assert\NotBlank(message: 'dto.course.title.blank')]
    #[Assert\Length(min: 3, max: 255, minMessage: 'dto.course.title.minLength')]
    public ?string $title = null;
}
