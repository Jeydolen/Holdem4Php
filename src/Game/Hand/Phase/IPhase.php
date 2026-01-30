<?php

namespace App\Game\Hand\Phase;

use App\Game\Player;
use App\Game\CardPile\Deck;

interface IPhase
{
    /**
     * @param Player[] $players
     */
    public function play(array $players, Deck $deck): void;
}
