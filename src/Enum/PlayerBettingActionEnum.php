<?php

namespace App\Enum;

enum PlayerBettingActionEnum: string
{
    case FOLD = "fold";

    case CHECK = "check";

    case BET = "bet";

    case CALL = "call";
}