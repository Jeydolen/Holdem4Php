<?php

namespace App\DTO\Phase;

class BettingPhaseDTO extends PhaseDTO
{
    public function getType(): string
    {
        return "betting_phase";
    }
}