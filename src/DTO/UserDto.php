<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;


class UserDto
{
    #[Serializer\Type('string')]
    #[Assert\NotBlank(message: 'Email пуст!')]
    #[Assert\Email(message: 'Email заполнен не по формату |почтовыйАдрес@почтовыйДомен.домен| .')]
    public ?string $username = null;

    #[Serializer\Type('string')]
    #[Assert\NotBlank(message: 'Пароль пуст!')]
    #[Assert\Length(min: 6, minMessage: 'Пароль должен содержать минимум {{ limit }} символов.')]
    public ?string $password = null;
}