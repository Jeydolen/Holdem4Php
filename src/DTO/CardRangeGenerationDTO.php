<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints\NotBlank;

class CardRangeGenerationDTO
{
    #[NotBlank()]
    public array $ranks;

    #[NotBlank()]
    public array $symbols;
}