<?php

namespace App\DTO\Phase;

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class BettingPhaseDTO extends PhaseDTO
{
    #[NotBlank()]
    #[Positive()]
    public int $maxBettingAmount;

    public function getType(): string
    {
        return "betting_phase";
    }
}