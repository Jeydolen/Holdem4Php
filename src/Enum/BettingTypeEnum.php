<?php

namespace App\Enum;

enum BettingTypeEnum: string
{
    case FIXED_LIMIT = "FIXED_LIMIT";
    case POT_LIMIT = "POT_LIMIT";
    case NO_LIMIT = "NO_LIMIT";
}