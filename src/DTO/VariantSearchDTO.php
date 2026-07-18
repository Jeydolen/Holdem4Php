<?php

namespace App\DTO;

use App\Enum\TableTypeEnum;
use App\Enum\BettingTypeEnum;

use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Positive;

class VariantSearchDTO
{
    #[Length(min: 2, max: 100)]
    public ?string $name = null;

    #[Positive()]
    public ?int $maxPlayers = null;

    public ?TableTypeEnum $tableType = null;

    public ?BettingTypeEnum $bettingType = null;
}