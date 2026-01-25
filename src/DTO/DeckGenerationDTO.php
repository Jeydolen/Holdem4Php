<?php

namespace App\DTO;

use App\Enum\DeckGenerationTypeEnum;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Type;

class DeckGenerationDTO
{
    #[NotBlank()]
    #[Range(min: -1)]
    public int $maxSize;

    public bool $noDuplicate = true;

    #[NotBlank()]
    public DeckGenerationTypeEnum $generationType;

    /**
     * Array of cards if generation_type = manual
     * @var CardDTO[] $cards
     */
    #[Type([CardDTO::class])]
    public array $cards;

    public CardRangeGenerationDTO $cardGenerationConfig;
}