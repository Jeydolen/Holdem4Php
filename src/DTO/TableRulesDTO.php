<?php

namespace App\DTO;

use App\DTO\Phase\PhaseDTO;
use App\Enum\TableTypeEnum;

use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\NotBlank;

class TableRulesDTO
{
    #[NotBlank()]
    #[Positive()]
    public int $maxPlayers;

    #[NotBlank()]
    public TableTypeEnum $tableType;

    #[NotBlank()]
    public DeckGenerationDTO $deckRules;

    /** @var PhaseDTO[] */
    #[Valid()]
    public array $phases;
}