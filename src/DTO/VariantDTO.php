<?php

namespace App\DTO;

use App\Enum\TableTypeEnum;
use App\Enum\BettingTypeEnum;

use App\DTO\Phase\PhaseDTO;

use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Range;
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
    #[Range(min: 1, max: 1_000_000)]
    public int $minBuyIn;

    #[NotBlank()]
    #[Range(min: 1, max: 1_000_000)]
    public int $maxBuyIn;
}