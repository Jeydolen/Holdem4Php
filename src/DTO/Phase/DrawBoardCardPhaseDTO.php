<?php

namespace App\DTO\Phase;

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class DrawBoardCardPhaseDTO extends PhaseDTO
{
    #[NotBlank()]
    #[Positive()]
    public int $drawNumber;

    public function getType(): string
    {
        return "draw_board_card_phase";
    }
}