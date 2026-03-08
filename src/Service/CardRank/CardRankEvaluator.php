<?php

namespace App\Service\CardRank;

use App\Game\CardPile\BoardCards;
use App\Game\CardPile\ICardPile;

class CardRankEvaluator
{
    /**
     * This method computes all possible hands from both card piles
     * and associate a weight for each hand
     * To determinate which player wins you need to compare the weight of hands
     * and depending on game rules the lowest or highest wins
     * @link https://en.wikipedia.org/wiki/List_of_poker_hands
     * @param ICardPile $playerCardPile
     * @param ICardPile $boardCardPile
     * @param int $maxBoardSize
     * @return void
     */
    public function evaluate(ICardPile $playerCardPile, ICardPile $boardCardPile, int $maxBoardSize)
    {
        // TODO: Call PokerEval
    }
}