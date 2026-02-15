<?php

namespace App\DTO\Phase;

use Symfony\Component\Validator\Constraints\Positive;

class ShuffleDeckPhaseDTO extends PhaseDTO
{
    #[Positive()]
    public ?int $rounds = 1;

    public function getType(): string
    {
        return "shuffle_deck_phase";
    }
}