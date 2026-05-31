<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PasswordStrength;

class RegisterDTO
{
    #[NotBlank]
    #[Length(min: 3, max: 180)]
    public string $username;

    #[PasswordStrength(minScore: PasswordStrength::STRENGTH_MEDIUM)]
    public string $password;
}