<?php

namespace App\Enum;


enum HandRankEnum: int
{
    case HIGH_CARD = 1;
    case ONE_PAIR = 2;
    case TWO_PAIR = 3;
    case THREE_OF_A_KIND = 4;
    case STRAIGHT = 5;
    case FLUSH = 6;
    case FULL_HOUSE = 7;
    case FOUR_OF_A_KIND = 8;
    case STRAIGHT_FLUSH = 9;
}
