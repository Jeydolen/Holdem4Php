<?php

namespace App\DTO\Phase;

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Serializer\Attribute\DiscriminatorMap;

#[DiscriminatorMap(
    typeProperty: "type",
    mapping: [
        "draw_cards" => DrawCardPhaseDTO::class,
        "betting_phase" => BettingPhaseDTO::class,
        "shuffle_deck_phase" => ShuffleDeckPhaseDTO::class,
        "draw_board_card_phase" => DrawBoardCardPhaseDTO::class,
        "showdown_phase" => ShowdownPhaseDTO::class,
        "ask_blind_phase" => AskBlindPhaseDTO::class,
    ]
)]
abstract class PhaseDTO
{
    #[Positive()]
    #[NotBlank()]
    public int $priority;

    #[Positive()]
    public ?int $timeout = null;

    abstract public function getType(): string;
}