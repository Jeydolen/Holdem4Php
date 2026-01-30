<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class TableRulesDTO
{
    #[NotBlank()]
    #[Positive()]
    public int $maxPlayers;

    // TODO: Make enum with different types (cash game, tournament, ...)
    #[NotBlank()]
    public string $tableType;

    #[NotBlank()]
    public DeckGenerationDTO $deckRules;

    #[NotBlank()]
    public array $phases;
}