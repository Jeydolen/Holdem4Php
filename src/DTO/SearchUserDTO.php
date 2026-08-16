<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class SearchUserDTO
{
    public ?string $username = null;

    // Max 100 elements shown
    #[Positive()]
    public ?int $limit = 100;

    #[NotBlank()]
    #[Positive()]
    public int $page;
}