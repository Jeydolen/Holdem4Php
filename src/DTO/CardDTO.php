<?php

namespace App\DTO;

use App\Game\Card\Card;
use App\Enum\CardSymbolEnum;
use Symfony\Component\Validator\Constraints\NotBlank;

// Same class but using another namespace for clarity
class CardDTO extends Card
{
    #[NotBlank()]
    public string $rank;

    #[NotBlank()]
    public CardSymbolEnum $symbol;
}