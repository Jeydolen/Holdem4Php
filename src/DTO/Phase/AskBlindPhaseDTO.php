<?php

namespace App\DTO\Phase;

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class AskBlindPhaseDTO extends PhaseDTO
{
    #[NotBlank()]
    #[Positive()]
    public int $nbPlayersToAsk;

    #[NotBlank()]
    #[Positive()]
    public int $baseAmount;

    // Amount modifier could be negative but never 0
    // It cannot be a float because we dont handle floating point with chips
    #[NotBlank()]
    public int $amountModifier;

    public function getType(): string
    {
        return "ask_blind_phase";
    }
}