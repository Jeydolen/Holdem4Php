<?php

namespace App\DTO;

use App\Enum\TableTypeEnum;
use App\Enum\BettingTypeEnum;

use App\DTO\Phase\PhaseDTO;

use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\NotBlank;

class VariantDTO
{
    #[NotBlank]
    #[Length(min: 2, max: 100)]
    public string $name;

    #[NotBlank()]
    #[Positive()]
    public int $maxPlayers;

    #[Positive()]
    public ?int $minPlayerTreshold = null;

    #[Positive()]
    public ?int $startingTimer = null;

    #[NotBlank()]
    public TableTypeEnum $tableType;

    #[NotBlank()]
    public DeckGenerationDTO $deckRules;

    /** @var PhaseDTO[] */
    #[Valid()]
    public array $phases;

    #[NotBlank()]
    public BettingTypeEnum $bettingType;

    #[NotBlank()]
    public StakeDTO $stake;
}