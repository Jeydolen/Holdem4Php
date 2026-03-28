<?php

namespace App\Enum;

enum CardRankEnum: string
{
    case DEUCE = '2';
    case THREE = '3';
    case FOUR = '4';
    case FIVE = '5';
    case SIX = '6';
    case SEVEN = '7';
    case EIGHT = '8';
    case NINE = '9';
    case TEN = '10';
    case JACK = 'J';
    case QUEEN = 'Q';
    case KING = 'K';
    case ACE = 'A';
}