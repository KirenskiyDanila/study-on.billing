<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;

class UserDto
{
    #[Serializer\Type('string')]
    #[Assert\NotBlank(message: 'dto.user.email.blank')]
    #[Assert\Email(message: 'dto.user.email.format')]
    public ?string $username = null;

    #[Serializer\Type('string')]
    #[Assert\NotBlank(message: 'dto.user.password.blank')]
    #[Assert\Length(min: 6, minMessage: 'dto.user.password.minLength')]
    public ?string $password = null;
}
