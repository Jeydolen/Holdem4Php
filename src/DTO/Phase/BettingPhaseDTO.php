<?php

namespace App\DTO\Phase;

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class BettingPhaseDTO extends PhaseDTO
{
    #[Positive()]
    public ?int $maxBettingAmount = null;

    public function getType(): string
    {
        return "betting_phase";
    }
}