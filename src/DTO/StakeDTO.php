<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class StakeDTO
{
    #[NotBlank()]
    #[Range(min: 1, max: 1_000_000)]
    public int $minBuyIn;

    #[NotBlank()]
    #[Range(min: 1, max: 1_000_000)]
    public int $maxBuyIn;

    public function equals(StakeDTO $stake): bool
    {
        return $this->minBuyIn === $stake->minBuyIn && $this->maxBuyIn === $stake->maxBuyIn;
    }
}