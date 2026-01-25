<?php

namespace App\Game\Hand;

use App\Game\Player;

/**
 * A poker hand in this context is a complete round of poker
 * including the dealt cards for every player, the board, ...²
 */
class PokerHand
{
    /**
     * The players playing the hand
     * @var array<Player>
     */
    private array $players;

    /**
     * The players that will not play the next phase of the hand
     * @var array<Player>
     */
    private array $folded_players;

    /**
     * The phases to be played in the hand
     * @var array
     */
    private array $phases;
}