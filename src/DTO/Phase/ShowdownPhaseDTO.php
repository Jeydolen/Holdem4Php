<?php

namespace App\DTO\Phase;

class ShowdownPhaseDTO extends PhaseDTO
{
    public function getType(): string
    {
        return "showdown_phase";
    }
}