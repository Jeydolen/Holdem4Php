<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Unique;
use Symfony\Component\Validator\Constraints\Valid;

class UpdateStakesDTO
{

    #[Type(StakeDTO::class)]
    #[Valid()]
    #[NotBlank()]
    #[Unique(message: "Cannot add the same stake twice !")]
    /**
     * @var StakeDTO[]
     */
    public array $stakes = [];
}