<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class SetBankrollDTO
{
    #[NotBlank()]
    #[PositiveOrZero()]
    public int $amount;
}