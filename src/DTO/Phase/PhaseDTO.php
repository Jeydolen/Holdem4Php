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