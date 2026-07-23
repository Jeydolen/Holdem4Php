<?php

namespace App\Game\Bet;

enum PlayerBettingActionEnum: string
{
    case FOLD = "fold";

    case CHECK = "check";

    case BET = "bet";

    case CALL = "call";

    case RAISE = "raise";

    case ALL_IN = "all_in";
}